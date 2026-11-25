<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/listing-workbook.php';

function spro_import_date($raw, $date1904, $numeric = false, $iso_date = false) {
    if ($raw === '') {
        return '';
    }
    if ($numeric && is_numeric($raw)) {
        $day = (int) floor((float) $raw);
        if ((float) $raw < ($date1904 ? 0 : 1) || (float) $raw >= ($date1904 ? 2957004 : 2958466) || (!$date1904 && $day === 60)) {
            throw new RuntimeException('This Excel date is outside the calendar. Enter a real date such as 2027-03-12.');
        }
        // The Windows date system counts a fictitious 1900-02-29 as serial 60.
        $base = $date1904 ? '1904-01-01' : ($day < 60 ? '1899-12-31' : '1899-12-30');
        return (new DateTimeImmutable($base, new DateTimeZone('UTC')))->modify("+$day days")->format('Y-m-d');
    }
    if ($iso_date && preg_match('/^(\d{4}-\d{2}-\d{2})T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})?$/', $raw, $match)) {
        $raw = $match[1];
    } elseif (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $raw, $match)) {
        $raw = "$match[3]-$match[2]-$match[1]";
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
    if (!$date || $date->format('Y-m-d') !== $raw || substr($raw, 0, 4) === '0000') {
        throw new RuntimeException('Enter a real Excel date, YYYY-MM-DD or DD.MM.YYYY, for example 2027-03-12.');
    }
    return $raw;
}

function spro_import_existing($key, $meta_key) {
    global $wpdb;
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} m ON m.post_id=p.ID
         WHERE p.post_type='travel_listing' AND m.meta_key=%s AND m.meta_value=%s LIMIT 1",
        $meta_key, $key
    ));
}

function spro_import_remove_partial($id) {
    // Only the importer's own unfinished draft and its newly sideloaded attachments.
    if (!metadata_exists('post', $id, '_spro_import_pending') || metadata_exists('post', $id, '_spro_import_key')) {
        return;
    }
    foreach (get_children(['post_parent' => $id, 'post_type' => 'attachment', 'post_status' => 'any', 'fields' => 'ids']) as $attachment) {
        wp_delete_attachment($attachment, true);
    }
    wp_delete_post($id, true);
}

function spro_import_photo($book, $part, $url, $post_id, $slug) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $tmp = wp_tempnam('listing-picture');
    if (!$tmp) {
        throw new RuntimeException('Cannot create a temporary image file.');
    }
    try {
        if ($part !== '') {
            if (file_put_contents($tmp, $book->bytes($part)) === false) {
                throw new RuntimeException('Cannot save the embedded picture.');
            }
        } else {
            if (!wp_http_validate_url($url)) {
                throw new RuntimeException('Featured image must be a public HTTP or HTTPS image URL.');
            }
            $response = wp_safe_remote_get($url, [
                'timeout' => 15, 'stream' => true, 'filename' => $tmp, 'limit_response_size' => 16 * MB_IN_BYTES + 1,
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                throw new RuntimeException('Could not download the featured image. Check the direct image URL.');
            }
        }
        clearstatcache(true, $tmp);
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mime = wp_get_image_mime($tmp);
        $size = wp_getimagesize($tmp);
        if (!isset($types[$mime]) || !$size || $size[0] * $size[1] > 50000000 || filesize($tmp) > 16 * MB_IN_BYTES) {
            throw new RuntimeException('Use a JPEG, PNG, GIF or WebP image up to 16 MB and 50 megapixels.');
        }
        $attachment = media_handle_sideload(['name' => $slug . '.' . $types[$mime], 'tmp_name' => $tmp], $post_id);
        if (is_wp_error($attachment)) {
            throw new RuntimeException($attachment->get_error_message());
        }
        if (!set_post_thumbnail($post_id, $attachment)) {
            throw new RuntimeException('WordPress could not set the featured image.');
        }
    } finally {
        if (is_file($tmp)) {
            wp_delete_file($tmp);
        }
    }
}

function spro_import_row($book, $row, &$seen = []) {
    list($data, $image, $errors) = $row;
    $columns = $row[3] ?? [];
    $number = $row[4] ?? 0;
    $issue = static function ($key, $message) use (&$errors, $columns, $number) {
        $field = ucfirst($key);
        $errors[] = (isset($columns[$key]) ? $columns[$key] . $number . " ($field)" : $field) . ': ' . $message;
    };
    $title = sanitize_text_field($data['post title'] ?? '');
    $from = $data['travel date from'] ?? '';
    $to = $data['travel date to'] ?? '';
    $name = $data['post name'] ?? '';
    $key = sanitize_title($name === '' ? trim($title . ' ' . $from) : $name);
    if ($title === '') {
        $issue('post title', 'Enter the name of the trip. Other fields may be blank.');
    }
    if (($name !== '' || $title !== '') && ($key === '' || strlen($key) > 200)) {
        $issue('post name', 'Enter a short unique ID, for example rome-spring-2027 (up to 200 URL characters).');
    }
    $existing = $key !== '' ? spro_import_existing($key, '_spro_import_key') : 0;
    if ($existing && !$errors) {
        if (isset($seen[$key])) {
            $book->warnings[] = "Row $number repeats Post name “{$key}” from row {$seen[$key]}. The later row was skipped. Give distinct trips different Post names.";
        } elseif ($title !== get_post_field('post_title', $existing, 'raw')) {
            $book->warnings[] = "Row $number was skipped because Post name “{$key}” already identifies another title. Use a new Post name only if this is a different trip.";
        }
        $seen[$key] = $seen[$key] ?? $number;
        return 'skipped';
    }
    $status = strtolower($data['post status'] ?? '');
    $status = $status === '' ? 'draft' : $status;
    if (!in_array($status, ['draft', 'pending', 'private', 'publish'], true)) {
        $issue('post status', 'Choose draft, pending, private or publish, or leave blank for draft.');
    }
    if (in_array($status, ['publish', 'private'], true) && !current_user_can('publish_posts')) {
        $issue('post status', 'Your account cannot publish this listing. Use draft or pending.');
    }
    $terms = [];
    foreach (preg_split('/[,;]+/', $data['categories'] ?? '', -1, PREG_SPLIT_NO_EMPTY) as $name) {
        $name = trim($name);
        if ($name === '') {
            continue;
        }
        $term = get_term_by('slug', $name, 'listing_category') ?: get_term_by('name', $name, 'listing_category');
        if (!$term) {
            $issue('categories', "Unknown category “{$name}”. Use a name or slug from Travel Listings → Categories, or leave blank.");
            continue;
        }
        $terms[] = (int) $term->term_id;
    }
    if ($from && $to && $from > $to) {
        $issue('travel date to', 'The end date must be on or after the start date.');
    }
    $meta = ['_travel_date_from' => $from, '_travel_date_to' => $to, '_spro_import_pending' => $key];
    foreach (['price', 'location', 'contact phone'] as $field) {
        $meta['_travel_' . str_replace(' ', '_', $field)] = sanitize_text_field($data['travel ' . $field] ?? '');
    }
    $price = preg_replace('/\s+/', ' ', $meta['_travel_price']);
    if ((function_exists('mb_strlen') ? mb_strlen($price) : strlen($price)) > 40) {
        $issue('travel price', 'Shorten the price to 40 characters so it can appear on the listing. Put longer details in the description.');
    }
    $email = $data['travel contact email'] ?? '';
    $url = $data['travel website url'] ?? '';
    if ($email !== '' && !is_email($email)) {
        $issue('travel contact email', 'Enter one address such as bookings@example.com, or leave blank.');
    }
    if ($url !== '' && (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower((string) wp_parse_url($url, PHP_URL_SCHEME)), ['http','https'], true))) {
        $issue('travel website url', 'Paste a complete address starting with https:// or http://, or leave blank.');
    }
    $meta['_travel_contact_email'] = $email;
    $meta['_travel_website_url'] = esc_url_raw($url);
    $badge = strtolower($data['travel price on image'] ?? '');
    if (!in_array($badge, ['', '0', '1', 'true', 'false', 'yes', 'no'], true)) {
        $issue('travel price on image', 'Use 1 for yes, 0 for no, or leave blank.');
    }
    $meta['_travel_price_on_image'] = in_array($badge, ['1', 'true', 'yes'], true) ? '1' : '0';
    foreach (['lv','en','ru'] as $lang) {
        foreach (['title' => 'sanitize_text_field', 'excerpt' => 'sanitize_textarea_field', 'description' => 'wp_kses_post'] as $field => $clean) {
            $meta["_travel_{$field}_{$lang}"] = $clean($data["travel $field $lang"] ?? '');
        }
    }
    if (!$image && ($data['featured image'] ?? '') !== '' && !preg_match('~^https?://[^\s]+$~i', $data['featured image'])) {
        $issue('featured image', 'Paste a direct HTTP(S) image URL, insert a picture in this cell, or leave blank.');
    }
    if ($errors) {
        throw new RuntimeException(implode("\n", array_unique($errors)));
    }
    $partial = spro_import_existing($key, '_spro_import_pending');
    if ($partial) {
        spro_import_remove_partial($partial);
    }
    if (get_page_by_path($key, OBJECT, ['travel_listing'])) {
        throw new RuntimeException('This Post name belongs to an existing listing. Choose a unique Post name.');
    }
    $id = wp_insert_post(wp_slash(['post_type' => 'travel_listing', 'post_title' => $title,
        'post_name' => $key, 'post_status' => 'draft', 'meta_input' => $meta]), true);
    if (is_wp_error($id)) {
        throw new RuntimeException($id->get_error_message());
    }
    try {
        $assigned = wp_set_object_terms($id, $terms, 'listing_category');
        if (is_wp_error($assigned)) {
            throw new RuntimeException($assigned->get_error_message());
        }
        if ($image !== '' || ($data['featured image'] ?? '') !== '') {
            try {
                spro_import_photo($book, $image, $data['featured image'] ?? '', $id, $key);
            } catch (RuntimeException $e) {
                $issue('featured image', $e->getMessage());
                throw new RuntimeException(end($errors));
            }
        }
        if ($status !== 'draft') {
            $updated = wp_update_post(['ID' => $id, 'post_status' => $status], true);
            if (is_wp_error($updated)) {
                throw new RuntimeException($updated->get_error_message());
            }
        }
        if (!add_post_meta($id, '_spro_import_key', $key, true)) {
            throw new RuntimeException('Cannot save the listing import ID.');
        }
        delete_post_meta($id, '_spro_import_pending');
    } catch (Throwable $error) {
        spro_import_remove_partial($id);
        throw $error;
    }
    $seen[$key] = $number;
    return 'created';
}

function spro_import_protected($id) {
    return get_post_type($id) === 'travel_listing' && metadata_exists('post', $id, '_spro_import_key');
}

add_filter('map_meta_cap', static function ($caps, $cap, $user, $args) {
    return $cap === 'delete_post' && !empty($args[0]) && spro_import_protected($args[0]) ? ['do_not_allow'] : $caps;
}, 10, 4);
foreach (['pre_delete_post', 'pre_trash_post'] as $hook) {
    add_filter($hook, static function ($result, $post) {
        return spro_import_protected($post->ID) ? false : $result;
    }, 10, 2);
}
