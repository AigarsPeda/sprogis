<?php
defined('ABSPATH') || exit;

require_once __DIR__ . '/import-categories.php';

add_action('init', static function () {
    register_post_type('spro_import_run', ['public' => false, 'rewrite' => false, 'supports' => []]);
});

function spro_import_page_url($args = []) {
    return add_query_arg(array_merge(['post_type' => 'travel_listing', 'page' => 'spro-excel-import'], $args), admin_url('edit.php'));
}

add_action('admin_menu', static function () {
    if (!current_user_can('upload_files')) {
        return;
    }
    $page = add_submenu_page('edit.php?post_type=travel_listing', 'Excel and Numbers import', 'Excel and Numbers import',
        'edit_others_posts', 'spro-excel-import', 'spro_import_page');
    add_action('load-' . $page, static function () {
        wp_enqueue_script('spro-excel-import', plugins_url('assets/js/excel-import.js', dirname(__DIR__) . '/travel-listings.php'), [],
            filemtime(dirname(__DIR__) . '/assets/js/excel-import.js'), true);
    });
});

function spro_import_save_report($id, $report) {
    update_post_meta($id, '_spro_import_report', wp_slash($report));
}

function spro_convert_numbers($source, $destination) {
    $python = defined('SPRO_NUMBERS_PYTHON') ? SPRO_NUMBERS_PYTHON
        : dirname(untrailingslashit(ABSPATH)) . '/spro-numbers/bin/python';
    if (!function_exists('proc_open') || !is_executable($python)) {
        throw new RuntimeException('The Numbers reader is unavailable. Ask the site administrator to install it, or upload an .xlsx copy.');
    }
    $process = proc_open([$python, '-I', __DIR__ . '/numbers/read.py', $source, $destination],
        [0 => ['file', '/dev/null', 'r'], 1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'a']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('The server could not start the Numbers reader. Contact the site administrator.');
    }
    $message = trim(stream_get_contents($pipes[1], 4096));
    fclose($pipes[1]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException($message ?: 'Could not read the Numbers file. Save a fresh, unencrypted Single File copy. Split large tables into smaller uploads.');
    }
}

/** The named database lock is connection-scoped and is released if a request dies. */
function spro_import_upload($file, $selection_issue = '', $attempted_name = '') {
    global $wpdb;
    $filename = $file['name'] ?? '';
    $name = sanitize_file_name(wp_basename(is_string($filename) && $filename !== '' ? $filename : $attempted_name)) ?: 'Upload';
    $id = wp_insert_post(wp_slash(['post_type' => 'spro_import_run', 'post_status' => 'private', 'post_title' => $name]), true);
    if (is_wp_error($id)) {
        return $id;
    }
    $report = ['state' => 'In progress', 'created' => 0, 'skipped' => 0, 'errors' => [], 'warnings' => []];
    spro_import_save_report($id, $report);
    $active_row = 0;
    register_shutdown_function(static function () use ($id, &$report, &$active_row) {
        if ($report['state'] === 'In progress') {
            $report['state'] = 'Interrupted';
            $report['errors'][] = ($active_row ? "Row $active_row: " : '') . 'The server stopped the import. Upload the workbook again to resume.';
            spro_import_save_report($id, $report);
        }
    });
    $lock = spro_import_lock_name();
    $locked = false;
    try {
        $limit = min(wp_max_upload_size(), 32 * MB_IN_BYTES);
        $size_message = 'This workbook exceeds the upload limit of ' . size_format($limit) . '. Split the rows into smaller workbooks or reduce the image sizes.';
        $issues = ['multiple' => 'Drop one workbook at a time. Upload each file separately.', 'package' => 'Open the workbook in Numbers and save it as a Single File instead of a package, then upload it again.', 'size' => $size_message];
        if (isset($issues[$selection_issue])) {
            throw new RuntimeException($issues[$selection_issue]);
        }
        $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($code !== UPLOAD_ERR_OK) {
            if (in_array($code, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit) {
                throw new RuntimeException($size_message);
            }
            $messages = [UPLOAD_ERR_PARTIAL => 'Only part of the workbook arrived. Check your connection and upload it again.',
                UPLOAD_ERR_NO_FILE => 'No workbook arrived. Choose one .xlsx or .numbers file and try again.',
                UPLOAD_ERR_NO_TMP_DIR => 'The server has no temporary upload folder. Ask the site administrator to restore it.',
                UPLOAD_ERR_CANT_WRITE => 'The server could not save the upload. Ask the site administrator to check disk space and permissions.'];
            throw new RuntimeException($messages[$code] ?? 'The server could not accept this upload. Retry or contact the site administrator.');
        }
        $format = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($format, ['xlsx', 'numbers'], true)) {
            throw new RuntimeException('Choose an Excel (.xlsx) or Apple Numbers (.numbers) workbook. CSV, .xls and .xlsm files require conversion.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('The uploaded file is missing. Choose the workbook again.');
        }
        if (filesize($file['tmp_name']) > $limit) {
            throw new RuntimeException($size_message);
        }
        if (!filesize($file['tmp_name'])) {
            throw new RuntimeException('The uploaded file is empty (0 bytes). Save or export the workbook again.');
        }
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 0)', $lock)) === 1;
        if (!$locked) {
            throw new RuntimeException('Another import is running. Retry this file after it finishes.');
        }
        $path = $file['tmp_name'];
        if ($format === 'numbers') {
            $converted = tmpfile();
            if (!$converted) {
                throw new RuntimeException('The server could not create a temporary workbook. Contact the site administrator.');
            }
            $destination = stream_get_meta_data($converted)['uri'];
            spro_convert_numbers($path, $destination);
            $path = $destination;
        }
        $book = new Spro_Listing_Workbook($path);
        $seen = [];
        $missing = [];
        $affected = [];
        $processed = 0;
        foreach ($book->rows() as $n => $row) {
            $active_row = $n;
            $report['sheet'] = $book->sheet_name;
            $row_missing = [];
            try {
                $result = spro_import_row($book, $row, $seen, [], $row_missing);
                $report[$result]++;
            } catch (Throwable $e) {
                if ($row_missing) {
                    $missing += $row_missing;
                    $affected[$n] = count($report['errors']);
                }
                $report['errors'][] = spro_import_row_error($n, $row, $e);
            }
            if (++$processed % 20 === 0) {
                $report['warnings'] = $book->warnings;
                spro_import_save_report($id, $report);
            }
        }
        if ($affected) {
            spro_import_hold_workbook($id, $path, $missing, $affected);
            $report['state'] = 'Needs category choices';
        }
    } catch (Throwable $e) {
        $report['errors'][] = $e->getMessage();
    } finally {
        if (isset($book)) {
            $report['warnings'] = $book->warnings;
            $report['sheet'] = $book->sheet_name;
        }
        if (!empty($converted)) {
            fclose($converted);
        }
        if ($locked) {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }
    if ($report['state'] === 'In progress') {
        $report['state'] = $report['errors'] ? 'Completed with errors' : 'Completed';
    }
    spro_import_save_report($id, $report);
    return $id;
}

add_action('admin_post_spro_excel_import', static function () {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        wp_die('Use the workbook import form to upload a workbook.', '', ['response' => 405]);
    }
    if (!current_user_can('edit_others_posts') || !current_user_can('upload_files')) {
        wp_die('You do not have permission to import listings.', '', ['response' => 403]);
    }
    check_admin_referer('spro_excel_import');
    $run = spro_import_upload($_FILES['workbook'] ?? [],
        sanitize_key(wp_unslash($_POST['selection_issue'] ?? '')), sanitize_text_field(wp_unslash($_POST['attempted_name'] ?? '')));
    if (is_wp_error($run)) {
        wp_die(esc_html($run->get_error_message()));
    }
    wp_safe_redirect(spro_import_page_url(['run' => $run]), 303);
    exit;
});

function spro_import_row_error($number, $row, $error) {
    $title = sanitize_text_field($row[0]['post title'] ?? '');
    return sprintf('Row %d%s: %s', $number, $title ? ' (' . $title . ')' : '', $error->getMessage());
}

function spro_import_run_summary($report) {
    $errors = count($report['errors']);
    return sprintf('%d created, %d skipped, %d %s.', $report['created'], $report['skipped'], $errors, $errors === 1 ? 'error' : 'errors');
}

function spro_import_errors($report) {
    if (!empty($report['sheet'])) {
        echo '<p>Worksheet: ' . esc_html($report['sheet']) . '</p>';
    }
    if ($report['errors']) {
        echo '<ul class="ul-disc">';
        foreach ($report['errors'] as $error) {
            echo '<li>' . nl2br(esc_html($error)) . '</li>';
        }
        echo '</ul>';
        if ($report['state'] === 'Needs category choices') {
            echo '<p>Open this import report to resolve its categories. Correct any other errors in the workbook.</p>';
        } else {
            echo '<p>Correct the entries above and upload the workbook again. Rows already imported will be skipped.</p>';
        }
    }
    if (!empty($report['warnings'])) {
        echo '<p><strong>Warnings</strong></p><ul class="ul-disc">';
        foreach ($report['warnings'] as $warning) {
            echo '<li>' . esc_html($warning) . '</li>';
        }
        echo '</ul>';
    }
}

function spro_import_page() {
    $run = absint($_GET['run'] ?? 0);
    $page = max(1, absint($_GET['paged'] ?? 1));
    // PHP clears POST and FILES when post_max_size is exceeded. Keep routing and the nonce available.
    $action = add_query_arg(['action' => 'spro_excel_import', '_wpnonce' => wp_create_nonce('spro_excel_import')], admin_url('admin-post.php'));
    $runs = new WP_Query(['post_type' => 'spro_import_run', 'post_status' => 'private',
        'posts_per_page' => 10, 'paged' => $page, 'orderby' => 'ID', 'order' => 'DESC']);
    ?>
    <div class="wrap">
        <h1>Excel and Numbers import</h1>
        <?php if ($run && get_post_type($run) === 'spro_import_run' && ($report = get_post_meta($run, '_spro_import_report', true))) : ?>
            <div role="<?php echo $report['errors'] ? 'alert' : 'status'; ?>" class="notice <?php echo $report['errors'] ? 'notice-error' : (!empty($report['warnings']) ? 'notice-warning' : 'notice-success'); ?>">
                <p><strong><?php echo esc_html(get_the_title($run)); ?></strong> &mdash; <?php echo esc_html(spro_import_run_summary($report)); ?></p>
                <?php spro_import_errors($report); spro_import_category_form($run); ?>
            </div>
        <?php endif; ?>
        <p><a class="button button-secondary" href="<?php echo esc_url(plugins_url('assets/templates/sprotravel-listings-template.xlsx', dirname(__DIR__) . '/travel-listings.php')); ?>" download>Download Excel template</a></p>
        <p>Fill in the Listings tab, starting in row 2. The Instructions tab explains each field and includes examples. Check existing <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=listing_category&post_type=travel_listing')); ?>">listing categories</a>.</p>
        <form id="spro-import-form" enctype="multipart/form-data" method="post" action="<?php echo esc_url($action); ?>">
            <input type="hidden" name="selection_issue"><input type="hidden" name="attempted_name">
            <div id="spro-import-drop" style="max-width:640px;padding:24px;border:2px dashed #8c8f94;background:white;margin:16px 0">
                <label for="spro-workbook"><strong>Drop an Excel or Numbers workbook here or choose a file</strong></label>
                <p><input required id="spro-workbook" name="workbook" type="file" accept=".xlsx,.numbers" data-max-bytes="<?php echo (int) min(wp_max_upload_size(), 32 * MB_IN_BYTES); ?>" aria-describedby="spro-import-help"></p>
                <p id="spro-import-help" class="description">Choose one .xlsx or .numbers file up to <?php echo esc_html(size_format(min(wp_max_upload_size(), 32 * MB_IN_BYTES))); ?>. In Numbers, put photos inside the Featured image cells or paste direct image URLs. Only Post title is required. Leave Post status blank for a draft. Keep Post name stable to avoid duplicates.</p>
                <p aria-live="polite" id="spro-import-status"></p>
                <?php submit_button('Import workbook', 'primary', 'spro-import-submit', false); ?>
            </div>
        </form>
        <h2>Import history</h2>
        <table class="widefat striped">
            <thead><tr><th scope="col">Date</th><th scope="col">Workbook</th><th scope="col">Result</th></tr></thead>
            <tbody>
            <?php foreach ($runs->posts as $log) :
                $report = get_post_meta($log->ID, '_spro_import_report', true); ?>
                <tr><td><?php echo esc_html(get_the_date('Y-m-d H:i:s', $log)); ?></td>
                    <td><?php echo esc_html($log->post_title); ?></td><td>
                    <?php if ($report) : ?>
                        <?php echo esc_html($report['state'] . ' · ' . spro_import_run_summary($report)); ?>
                        <?php if ($report['state'] === 'Needs category choices') : ?><a href="<?php echo esc_url(spro_import_page_url(['run' => $log->ID])); ?>">Resolve categories</a><?php endif; ?>
                        <?php if ($report['errors'] || !empty($report['warnings'])) : ?><details><summary>View <?php echo $report['errors'] ? 'errors' : 'warnings'; ?></summary><?php spro_import_errors($report); ?></details><?php endif; ?>
                    <?php endif; ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$runs->posts) : ?><tr><td colspan="3">No imports yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
        <?php if ($page > 1) : ?><a href="<?php echo esc_url(spro_import_page_url(['paged' => $page - 1])); ?>">Newer imports</a><?php endif; ?>
        <?php if ($page < $runs->max_num_pages) : ?><a href="<?php echo esc_url(spro_import_page_url(['paged' => $page + 1])); ?>">Older imports</a><?php endif; ?>
    </div>
    <?php
}
