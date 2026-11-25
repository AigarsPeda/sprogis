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

/** The stable trip ID used to match rows across uploads and to link Included and Key dates rows. */
function spro_import_row_key($data) {
    $name = $data['post name'] ?? '';
    return sanitize_title($name === '' ? trim(sanitize_text_field($data['post title'] ?? '') . ' ' . ($data['travel date from'] ?? '')) : $name);
}

/** Store an embedded or linked picture as the featured image (poster), the homepage card image or the trip page photo. */
function spro_import_photo($book, $part, $url, $post_id, $slug, $field = 'featured image') {
    $label = ucfirst($field);
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
                throw new RuntimeException("$label must be a public HTTP or HTTPS image URL.");
            }
            $response = wp_safe_remote_get($url, [
                'timeout' => 15, 'stream' => true, 'filename' => $tmp, 'limit_response_size' => 16 * MB_IN_BYTES + 1,
            ]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                throw new RuntimeException("Could not download the $field. Check the direct image URL.");
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
        if ($field === 'trip page photo') {
            update_post_meta($post_id, '_travel_photo_id', $attachment);
        } elseif ($field === 'card image') {
            update_post_meta($post_id, '_travel_card_image_id', $attachment);
        } elseif (!set_post_thumbnail($post_id, $attachment)) {
            throw new RuntimeException('WordPress could not set the featured image.');
        }
    } finally {
        if (is_file($tmp)) {
            wp_delete_file($tmp);
        }
    }
}

function spro_import_multiline_text($value) {
    // Some spreadsheet generators write the newline escape as literal cell text.
    return str_replace(['\\r\\n', '\\n', "\r\n", "\r"], "\n", $value);
}

function spro_import_row($book, $row, &$seen = [], $category_map = [], &$missing = []) {
    list($data, $image, $errors) = $row;
    $columns = $row[3] ?? [];
    $number = $row[4] ?? 0;
    $photo = $row[5] ?? '';
    $card = $row[6] ?? '';
    $issue = static function ($key, $message) use (&$errors, $columns, $number) {
        $field = ucfirst($key);
        $errors[] = (isset($columns[$key]) ? $columns[$key] . $number . " ($field)" : $field) . ': ' . $message;
    };
    $title = sanitize_text_field($data['post title'] ?? '');
    $from = $data['travel date from'] ?? '';
    $to = $data['travel date to'] ?? '';
    $name = $data['post name'] ?? '';
    $key = spro_import_row_key($data);
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
        spro_import_fill_details($book, $existing, $data, $number);
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
        if (isset($category_map[$name])) {
            $terms[] = $category_map[$name];
            continue;
        }
        $term = get_term_by('slug', $name, 'listing_category') ?: get_term_by('name', $name, 'listing_category');
        if (!$term) {
            $missing[$name] = true;
            $issue('categories', "Unknown category “{$name}”. Choose a category in this report or correct the workbook.");
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
    $email = $data['travel contact email'] ?? '';
    $url = $data['travel website url'] ?? '';
    $reserve = $data['reserve button link'] ?? '';
    if ($reserve !== '' && (!filter_var($reserve, FILTER_VALIDATE_URL) || !in_array(strtolower((string) wp_parse_url($reserve, PHP_URL_SCHEME)), ['http', 'https'], true))) {
        $issue('reserve button link', 'Paste a complete address starting with https:// or http://, or leave blank.');
    }
    if ($reserve !== '') {
        $meta['_travel_reserve_url'] = esc_url_raw($reserve);
    }
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
    $position = str_replace('centre', 'center', strtolower($data['photo crop'] ?? ''));
    if ($position !== '' && !isset(spro_photo_positions()[$position])) {
        $issue('photo crop', 'Use top, center or bottom, or leave blank for center.');
    }
    if (in_array($position, ['top', 'bottom'], true)) {
        $meta['_travel_photo_position'] = $position;
    }
    foreach (['lv','en','ru'] as $lang) {
        foreach (['title' => 'sanitize_text_field', 'excerpt' => 'sanitize_textarea_field', 'description' => 'wp_kses_post'] as $field => $clean) {
            $value = $data["travel $field $lang"] ?? '';
            if ($field !== 'title') {
                $value = spro_import_multiline_text($value);
            }
            $meta["_travel_{$field}_{$lang}"] = $clean($value);
        }
    }
    list($meta['_travel_included'], $meta['_travel_key_dates'], $problems) = spro_import_trip_details($data);
    foreach ($problems as $field => $messages) {
        foreach ($messages as $message) {
            $issue($field, $message);
        }
    }
    foreach (['featured image' => $image, 'card image' => $card, 'trip page photo' => $photo] as $field => $part) {
        if (!$part && ($data[$field] ?? '') !== '' && !preg_match('~^https?://[^\s]+$~i', $data[$field])) {
            $issue($field, 'Paste a direct HTTP(S) image URL, insert a picture in this cell, or leave blank.');
        }
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
        foreach (['featured image' => [$image, $key], 'card image' => [$card, $key . '-card'], 'trip page photo' => [$photo, $key . '-photo']] as $field => [$part, $file]) {
            if ($part === '' && ($data[$field] ?? '') === '') {
                continue;
            }
            try {
                spro_import_photo($book, $part, $data[$field] ?? '', $id, $file, $field);
            } catch (RuntimeException $e) {
                $issue($field, $e->getMessage());
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

/** Non-empty lines of a multi-line cell, keyed by their line number. */
function spro_import_lines($value) {
    $out = [];
    foreach (explode("\n", spro_import_multiline_text((string) $value)) as $i => $line) {
        $line = trim($line);
        if ($line !== '') {
            $out[$i + 1] = $line;
        }
    }
    return $out;
}

/** Read "170–220", "335", "335+", "from 335" or "up to 220" (euro signs optional). Returns [from, to] or false. */
function spro_import_price_text($text) {
    $text = trim(preg_replace('/\s+/u', ' ', str_ireplace(['€', 'eur'], '', $text)));
    $one = static function ($value) {
        $amount = spro_detail_amount($value);
        return is_float($amount) ? $amount : false;
    };
    if (preg_match('/^(?:from|no|от)\s+(.+)$/iu', $text, $m)) {
        return ($a = $one($m[1])) !== false ? [$a, null] : false;
    }
    if (preg_match('/^(?:up to|līdz|до)\s+(.+)$/iu', $text, $m)) {
        return ($a = $one($m[1])) !== false ? [null, $a] : false;
    }
    if (preg_match('/^(.+?)\s*\+$/u', $text, $m)) {
        return ($a = $one($m[1])) !== false ? [$a, null] : false;
    }
    if (preg_match('/^(.+?)\s*[–—-]\s*(.+)$/u', $text, $m)) {
        $a = $one($m[1]);
        $b = $one($m[2]);
        return $a !== false && $b !== false && $a <= $b ? [$a, $b] : false;
    }
    $a = $one($text);
    return $a !== false ? [$a, $a] : false;
}

/** Read the date part of a key-date line: a date, an optional end date and an optional time. */
function spro_import_key_date_when($text) {
    $date = '(\d{4}-\d{1,2}-\d{1,2}|\d{1,2}\.\d{1,2}\.\d{4})\.?';
    if (!preg_match("/^$date(?:\s*[–—-]\s*$date)?(?:,?\s+(\d{1,2}[:.]\d{2}(?:\s+[A-Za-z]{2,5}(?:[+-]\d{1,2})?)?))?$/u", trim($text), $m)) {
        throw new RuntimeException('start with a date such as 13.02.2027 or 2027-02-13, optionally followed by an end date and a time.');
    }
    $normal = static function ($raw) {
        if (strpos($raw, '.') !== false) {
            list($d, $mo, $y) = explode('.', $raw);
            return spro_import_date(sprintf('%02d.%02d.%04d', $d, $mo, $y), false);
        }
        list($y, $mo, $d) = explode('-', $raw);
        return spro_import_date(sprintf('%04d-%02d-%02d', $y, $mo, $d), false);
    };
    try {
        $from = $normal($m[1]);
        $to = !empty($m[2]) ? $normal($m[2]) : '';
    } catch (RuntimeException $e) {
        throw new RuntimeException('the date does not exist. Check the day and month.');
    }
    if ($to !== '' && $to < $from) {
        throw new RuntimeException('the end date is before the start date.');
    }
    $time = !empty($m[3]) ? spro_detail_time($m[3]) : '';
    if ($time === false) {
        throw new RuntimeException('the time is not valid. Use a time such as 15:00 or 15:00 CET.');
    }
    return [$from, $to === $from ? '' : $to, $time];
}

/**
 * Read the Included LV/EN/RU and Key dates LV/EN/RU cells of a trip row.
 * Included lines match by position in each language; key-date lines match by their date and time.
 * Returns [included, key dates, problems keyed by column].
 */
function spro_import_trip_details($data) {
    $problems = [];
    $included = [];
    $languages = [];
    foreach (SPRO_DETAIL_LANGS as $code) {
        if ($lines = spro_import_lines($data["price includes $code"] ?? '')) {
            $languages[$code] = array_values($lines);
        }
    }
    $counts = array_map('count', $languages);
    if (count(array_unique($counts)) > 1) {
        $codes = array_keys($counts);
        $summary = implode(', ', array_map(static function ($code) use ($counts) {
            return strtoupper($code) . ' has ' . $counts[$code];
        }, $codes));
        $problems['price includes ' . $codes[1]][] = "Every language needs the same number of lines ($summary). Line 1 in each language is the same item.";
    } else {
        for ($i = 0; $i < ($counts ? max($counts) : 0); $i++) {
            $item = array_fill_keys(SPRO_DETAIL_LANGS, '');
            $price = null;
            $source = '';
            foreach ($languages as $code => $lines) {
                $parts = explode('|', $lines[$i], 2);
                $item[$code] = sanitize_text_field(trim($parts[0]));
                $text = trim($parts[1] ?? '');
                if ($text === '') {
                    continue;
                }
                $parsed = spro_import_price_text($text);
                if ($parsed === false) {
                    $problems["price includes $code"][] = sprintf('Line %d: “%s” is not a price. Use 170–220, 335 or 335+ (from €335), or leave it out to show “Included”.', $i + 1, $text);
                } elseif ($price !== null && $parsed !== $price) {
                    $problems["price includes $code"][] = sprintf('Line %d has a different price than in %s. Give the price once or the same in every language.', $i + 1, strtoupper($source));
                } else {
                    $price = $parsed;
                    $source = $source ?: $code;
                }
            }
            if (!array_filter($item, 'strlen')) {
                $problems['price includes ' . array_key_first($languages)][] = sprintf('Line %d has a price but no item. Write the item before |.', $i + 1);
            }
            $included[] = ['item' => $item, 'from' => $price[0] ?? null, 'to' => $price[1] ?? null];
        }
    }
    $dates = [];
    foreach (SPRO_DETAIL_LANGS as $code) {
        $seen = [];
        foreach (spro_import_lines($data["key dates $code"] ?? '') as $number => $line) {
            $parts = explode('|', $line, 2);
            if (count($parts) < 2 || trim($parts[1]) === '') {
                $problems["key dates $code"][] = "Line $number: write the date, then |, then what happens, for example 13.02.2027 17:00 CET | Worldwide ticket sale.";
                continue;
            }
            try {
                list($from, $to, $time) = spro_import_key_date_when($parts[0]);
            } catch (RuntimeException $e) {
                $problems["key dates $code"][] = "Line $number: " . $e->getMessage();
                continue;
            }
            // The same date can appear twice in a language; the nth occurrence matches the nth in other languages.
            $key = "$from|$to|$time";
            $seen[$key] = ($seen[$key] ?? 0) + 1;
            $key .= '#' . $seen[$key];
            $dates[$key] = $dates[$key] ?? ['date' => $from, 'to' => $to, 'time' => $time, 'label' => array_fill_keys(SPRO_DETAIL_LANGS, '')];
            $dates[$key]['label'][$code] = sanitize_text_field(trim($parts[1]));
        }
    }
    return [spro_clean_included($included), spro_clean_key_dates(array_values($dates)), $problems];
}

/** Add included items and key dates to an existing trip only where it has none, so WordPress edits are never overwritten. */
function spro_import_fill_details($book, $id, $data, $number) {
    list($included, $dates, $problems) = spro_import_trip_details($data);
    if ($problems) {
        $book->warnings[] = "Row $number: included items and key dates were not added to the existing trip. " . implode(' ', array_merge(...array_values($problems)));
        return;
    }
    $added = false;
    foreach (['_travel_included' => $included, '_travel_key_dates' => $dates] as $meta => $rows) {
        if ($rows && !get_post_meta($id, $meta, true)) {
            update_post_meta($id, $meta, wp_slash($rows));
            $added = true;
        }
    }
    $book->details_added += $added ? 1 : 0;
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
