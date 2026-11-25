<?php
defined('ABSPATH') || exit;

function spro_import_lock_name() {
    global $wpdb;
    return 'spro-import-' . md5(DB_NAME . ':' . $wpdb->posts);
}

function spro_import_pending_file($pending) {
    $name = $pending['file'] ?? '';
    if (!is_string($name) || !preg_match('/^spro-categories-[A-Za-z0-9]+$/D', $name)) {
        return '';
    }
    $path = trailingslashit(sys_get_temp_dir()) . $name;
    return !is_link($path) && is_file($path) ? $path : '';
}

function spro_import_clear_pending($run) {
    $pending = get_post_meta($run, '_spro_pending_categories', true);
    if (empty($pending)) {
        return;
    }
    $path = spro_import_pending_file($pending);
    if ($path) {
        wp_delete_file($path);
    }
    delete_post_meta($run, '_spro_pending_categories');
    wp_clear_scheduled_hook('spro_expire_category_choices', [(int) $run]);
}

add_action('before_delete_post', static function ($id, $post) {
    if ($post->post_type === 'spro_import_run') {
        spro_import_clear_pending($id);
    }
}, 10, 2);

add_action('spro_expire_category_choices', static function ($id) {
    global $wpdb;
    $lock = spro_import_lock_name();
    if (!(int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) {
        wp_schedule_single_event(time() + 600, 'spro_expire_category_choices', [$id]);
        return;
    }
    try {
        spro_import_clear_pending($id);
        $report = get_post_meta($id, '_spro_import_report', true);
        if ($report && $report['state'] === 'Needs category choices') {
            $report['state'] = 'Workbook expired';
            $report['warnings'][] = 'Upload the workbook again to choose categories.';
            spro_import_save_report($id, $report);
        }
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
});

function spro_import_hold_workbook($id, $path, $missing, $rows) {
    $expires = time() + DAY_IN_SECONDS;
    $file = tempnam(sys_get_temp_dir(), 'spro-categories-');
    if (!$file || !copy($path, $file)) {
        if ($file) {
            wp_delete_file($file);
        }
        throw new RuntimeException('Could not retain the workbook. Correct its categories and upload it again.');
    }
    $pending = ['file' => basename($file), 'expires' => $expires, 'names' => array_keys($missing), 'rows' => $rows];
    if (!add_post_meta($id, '_spro_pending_categories', wp_slash($pending), true)) {
        wp_delete_file($file);
        throw new RuntimeException('Could not save category choices. Upload the workbook again.');
    }
    if (!wp_schedule_single_event($expires, 'spro_expire_category_choices', [(int) $id])) {
        spro_import_clear_pending($id);
        throw new RuntimeException('Could not retain the workbook for review. Correct its categories and upload it again.');
    }
}

function spro_import_choose_categories($names, $choices) {
    $taxonomy = get_taxonomy('listing_category');
    if (!current_user_can($taxonomy->cap->assign_terms)) {
        throw new RuntimeException('Your account cannot assign listing categories.');
    }
    $map = [];
    // Validate all choices before creating categories.
    foreach ($names as $i => $name) {
        $choice = $choices[$i] ?? '';
        if ($choice === 'create' && current_user_can($taxonomy->cap->manage_terms)) {
            $map[$name] = 0;
        } elseif (is_string($choice) && ctype_digit($choice) && get_term_by('id', (int) $choice, 'listing_category')) {
            $map[$name] = (int) $choice;
        } else {
            throw new RuntimeException('Choose an available listing category for each workbook value.');
        }
    }
    foreach ($map as $name => $term_id) {
        if ($term_id) {
            continue;
        }
        $term = get_term_by('slug', (string) $name, 'listing_category') ?: get_term_by('name', (string) $name, 'listing_category');
        if ($term) {
            $map[$name] = (int) $term->term_id;
            continue;
        }
        $created = wp_insert_term((string) $name, 'listing_category');
        if (is_wp_error($created)) {
            if ($created->get_error_code() !== 'term_exists') {
                throw new RuntimeException($created->get_error_message());
            }
            $map[$name] = (int) $created->get_error_data();
        } else {
            $map[$name] = (int) $created['term_id'];
        }
    }
    return $map;
}

add_action('admin_post_spro_resolve_categories', static function () {
    global $wpdb;
    $id = absint($_POST['run'] ?? 0);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !current_user_can('edit_others_posts') || !current_user_can('upload_files') || get_post_type($id) !== 'spro_import_run') {
        wp_die('You cannot resolve this import.', '', ['response' => 403]);
    }
    check_admin_referer('spro_categories_' . $id);
    $lock = spro_import_lock_name();
    if (!(int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock))) {
        wp_die('Another import is running. Return to this report and retry.', '', ['back_link' => true]);
    }
    try {
        $pending = get_post_meta($id, '_spro_pending_categories', true);
        $report = get_post_meta($id, '_spro_import_report', true);
        $path = spro_import_pending_file($pending);
        if ($pending && $pending['expires'] > time() && $path) {
            $map = spro_import_choose_categories($pending['names'], wp_unslash($_POST['category'] ?? []));
            $book = new Spro_Listing_Workbook($path);
            $seen = [];
            $missing = [];
            foreach ($book->rows() as $n => $row) {
                if (!isset($pending['rows'][$n])) {
                    continue;
                }
                $index = $pending['rows'][$n];
                try {
                    $result = spro_import_row($book, $row, $seen, $map, $missing);
                    $report[$result]++;
                    unset($report['errors'][$index]);
                } catch (Throwable $error) {
                    $report['errors'][$index] = spro_import_row_error($n, $row, $error);
                }
            }
            $report['errors'] = array_values($report['errors']);
            $report['state'] = $report['errors'] ? 'Completed with errors' : 'Completed';
            spro_import_save_report($id, $report);
            spro_import_clear_pending($id);
        } elseif ($pending) {
            throw new RuntimeException('The saved workbook expired. Upload it again to resolve categories.');
        }
    } catch (Throwable $error) {
        $message = $error->getMessage();
    } finally {
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
    }
    if (isset($message)) {
        wp_die(esc_html($message), '', ['back_link' => true]);
    }
    wp_safe_redirect(spro_import_page_url(['run' => $id]), 303);
    exit;
});

function spro_import_category_form($id) {
    $pending = get_post_meta($id, '_spro_pending_categories', true);
    if (!$pending) {
        return;
    }
    if ($pending['expires'] <= time() || !spro_import_pending_file($pending)) {
        echo '<p>The saved workbook expired. Upload it again to choose categories.</p>';
        return;
    }
    $terms = get_terms(['taxonomy' => 'listing_category', 'hide_empty' => false]);
    if (is_wp_error($terms)) {
        echo '<p>Categories could not be loaded. Reload this report to try again.</p>';
        return;
    }
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <h2>Resolve missing categories</h2>
        <p>Choose how to handle each name from the workbook. Your choices apply to this upload. The workbook is available for 24 hours.</p>
        <input type="hidden" name="action" value="spro_resolve_categories">
        <input type="hidden" name="run" value="<?php echo (int) $id; ?>">
        <?php wp_nonce_field('spro_categories_' . $id); ?>
        <?php foreach ($pending['names'] as $i => $name) : ?>
            <p><label><?php echo esc_html($name); ?>
                <select required name="category[<?php echo (int) $i; ?>]">
                    <option value="">Choose a category</option>
                    <?php if (current_user_can(get_taxonomy('listing_category')->cap->manage_terms)) : ?>
                        <option value="create"><?php echo esc_html(sprintf('Create “%s”', $name)); ?></option>
                    <?php endif; ?>
                    <?php foreach ($terms as $term) : ?>
                        <option value="<?php echo (int) $term->term_id; ?>"><?php echo esc_html($term->name . ' (' . $term->slug . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </label></p>
        <?php endforeach; ?>
        <?php submit_button('Apply categories and retry rows', 'primary', 'resolve', false); ?>
    </form>
    <?php
}
