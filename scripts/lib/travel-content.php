<?php
// Called by the sync script through WP-CLI in Local or CLI PHP on the Droplet.
if (PHP_SAPI !== 'cli' || !defined('ABSPATH')) {
    exit(1);
}

function spro_check($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function spro_listing_fields($id) {
    $post = get_post($id, ARRAY_A);
    spro_check($post && $post['post_type'] === 'travel_listing', "Listing $id is missing.");
    $fields = array_intersect_key($post, array_flip(['post_name','post_title','post_content','post_excerpt','post_status']));
    foreach (get_post_meta($id) as $key => $values) {
        if (strpos($key, '_travel_') === 0) {
            $fields[$key] = get_post_meta($id, $key, true);
        }
    }
    $terms = wp_get_object_terms($id, 'listing_category', ['fields' => 'slugs']);
    spro_check(!is_wp_error($terms), 'Unable to read listing categories.');
    sort($terms);
    $fields['categories'] = $terms;
    $image = get_post_thumbnail_id($id);
    $fields['image'] = $image ? get_post_meta($image, '_wp_attached_file', true) : '';
    ksort($fields);
    return $fields;
}

global $wpdb;
$action = $args[0] ?? '';
$host = wp_parse_url(get_option('home'), PHP_URL_HOST);
$tables = ['posts','postmeta','terms','term_taxonomy','term_relationships','termmeta','comments','commentmeta'];
$options = ['show_on_front','page_on_front','page_for_posts','site_icon','blogname','blogdescription'];
foreach (['lv','en','ru'] as $lang) {
    $options[] = 'travel_listings_hero_title_' . $lang;
    $options[] = 'travel_listings_hero_subtitle_' . $lang;
}
$options[] = 'travel_listings_hero_image';
$transaction = false;
try {
    if ($action === 'export') {
        spro_check($host === 'sprotravel.com', 'Unexpected source site.');
        spro_check($wpdb->query('START TRANSACTION WITH CONSISTENT SNAPSHOT') !== false, 'Could not open source snapshot.');
        $transaction = true;
        $data = ['tables' => [], 'options' => [], 'authors' => [], 'listings' => []];
        foreach ($tables as $table) {
            $data['tables'][$table] = $wpdb->get_results("SELECT * FROM {$wpdb->$table}", ARRAY_A);
            spro_check(!$wpdb->last_error, "Could not read $table.");
        }
        foreach ($options as $key) {
            $data['options'][$key] = get_option($key, null);
        }
        foreach (get_users(['fields' => ['ID','user_login']]) as $user) {
            $data['authors'][$user->ID] = $user->user_login;
        }
        foreach ($data['tables']['posts'] as $post) {
            if ($post['post_type'] === 'travel_listing') {
                $data['listings'][$post['ID']] = spro_listing_fields($post['ID']);
            }
        }
        $data['uploads'] = wp_get_upload_dir()['basedir'];
        $result = $data;
    } elseif ($action === 'import') {
        spro_check($host === 'sprogis.local', 'Content replacement is only available on sprogis.local.');
        $data = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        spro_check(array_keys($data['tables']) === $tables && !empty($data['tables']['posts']), 'Incomplete source snapshot.');
        $authors = [0 => 0];
        $admins = get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 1, 'orderby' => 'ID']);
        spro_check(!empty($admins), 'Local needs an administrator to own imported posts.');
        foreach ($data['authors'] as $id => $login) {
            $user = get_user_by('login', $login);
            $authors[$id] = $user ? $user->ID : $admins[0];
        }
        $replacers = [];
        foreach (['https://sprotravel.com','http://sprotravel.com'] as $origin) {
            $replacers[] = new \WP_CLI\SearchReplacer($origin, untrailingslashit(home_url()));
        }
        // run() adjusts lengths in serialized metadata as well as ordinary text.
        $localize = static function ($value) use ($replacers) {
            if (is_string($value) && strpos($value, 'sprotravel.com') !== false) {
                foreach ($replacers as $replacer) {
                    $value = $replacer->run($value);
                }
            }
            return $value;
        };
        foreach (array_merge($tables, ['options']) as $table) {
            $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->$table));
            spro_check($engine === 'InnoDB', "Transactional import requires InnoDB for $table.");
        }
        spro_check($wpdb->query('START TRANSACTION') !== false, 'Could not start local import.');
        $transaction = true;
        foreach ($tables as $table) {
            spro_check($wpdb->query("DELETE FROM {$wpdb->$table}") !== false, "Cannot replace $table.");
            foreach ($data['tables'][$table] as $row) {
                if ($table === 'posts') {
                    $row['post_author'] = $authors[$row['post_author']] ?? $admins[0];
                } elseif ($table === 'comments' && $row['user_id']) {
                    $row['user_id'] = $authors[$row['user_id']] ?? 0;
                } elseif ($table === 'postmeta' && in_array($row['meta_key'], ['_edit_lock', '_edit_last'], true)) {
                    continue;
                }
                $row = array_map($localize, $row);
                spro_check($wpdb->insert($wpdb->$table, $row) !== false, "Cannot import $table: $wpdb->last_error");
            }
        }
        foreach ($options as $key) {
            $value = $data['options'][$key];
            if ($value === null) {
                delete_option($key);
            } else {
                update_option($key, $localize($value));
            }
            spro_check(!$wpdb->last_error, "Unable to update $key.");
        }
        $result = ['imported_posts' => count($data['tables']['posts'])];
    } elseif ($action === 'listings') {
        spro_check($host === 'sprogis.local', 'Unexpected local installation.');
        $result = [];
        foreach (get_posts(['post_type'=>'travel_listing','post_status'=>'any','numberposts'=>-1]) as $post) {
            $result[$post->ID] = spro_listing_fields($post->ID);
        }
    } elseif ($action === 'push' || $action === 'preview') {
        spro_check($host === 'sprotravel.com', 'Unexpected destination site.');
        $change = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
        $id = absint($change['id']);
        if ($action === 'push') {
            spro_check($wpdb->query('START TRANSACTION') !== false, 'Could not start listing update.');
            $transaction = true;
            $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE ID=%d FOR UPDATE", $id));
            clean_post_cache($id);
        }
        $current = spro_listing_fields($id);
        spro_check($current['post_name'] === $change['before']['post_name']
            || $current['post_name'] === ($change['fields']['post_name'] ?? null), 'Live listing identity changed.');
        $post = ['ID' => $id];
        $changed = false;
        foreach ($change['fields'] as $key => $value) {
            if (($current[$key] ?? null) === $value) {
                continue;
            }
            $changed = true;
            spro_check(($current[$key] ?? null) === ($change['before'][$key] ?? null), "Live field $key changed since the pull. Pull again or reconcile it first.");
            if (strpos($key, '_travel_') === 0) {
                if ($action === 'push') {
                    $value === null ? delete_post_meta($id, $key) : update_post_meta($id, $key, wp_slash($value));
                }
            } elseif ($key === 'categories') {
                $term_ids = [];
                foreach ($value as $slug) {
                    $term = get_term_by('slug', $slug, 'listing_category');
                    spro_check($term, "Category $slug does not exist on the Droplet.");
                    $term_ids[] = (int) $term->term_id;
                }
                if ($action === 'push') {
                    spro_check(!is_wp_error(wp_set_object_terms($id, $term_ids, 'listing_category')), 'Cannot set categories.');
                }
            } elseif ($key === 'image') {
                $image_id = $value ? $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_wp_attached_file' AND meta_value=%s LIMIT 1", $value)) : 0;
                spro_check(!$value || $image_id, 'Upload this image to the Droplet media library first.');
                if ($action === 'push') {
                    $image_id ? set_post_thumbnail($id, $image_id) : delete_post_thumbnail($id);
                }
            } else {
                spro_check(in_array($key, ['post_name','post_title','post_content','post_excerpt','post_status'], true), 'Unsupported listing field.');
                $post[$key] = $value;
            }
        }
        if ($action === 'push' && $changed) {
            $updated = wp_update_post(wp_slash($post), true);
            spro_check(!is_wp_error($updated), 'WordPress could not update this listing.');
            $after = spro_listing_fields($id);
            foreach ($change['fields'] as $key => $value) {
                spro_check(($after[$key] ?? null) === $value, "Verification failed for $key.");
            }
        }
        $result = ['fields' => array_keys($change['fields'])];
    } else {
        throw new RuntimeException('Unknown sync action.');
    }
    if ($transaction) {
        spro_check($wpdb->query('COMMIT') !== false, 'Could not commit.');
    }
    if ($action === 'import') {
        wp_cache_flush();
    }
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    if ($transaction) {
        $wpdb->query('ROLLBACK');
    }
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
