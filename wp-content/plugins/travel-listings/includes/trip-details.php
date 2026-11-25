<?php
defined('ABSPATH') || exit;

/*
 * Trip page details: a landscape photo, what the price includes, key dates and hand-picked related trips.
 * Workbook imports and the edit screen both store them through the cleaners below.
 */

const SPRO_DETAIL_LANGS = ['lv', 'en', 'ru'];
const SPRO_DETAIL_LIMIT = 30;

function spro_lang() {
    global $travel_listings_instance;
    return $travel_listings_instance->get_current_language();
}

/** Text in the visitor's language, falling back to another language when a translation is missing. */
function spro_pick($texts) {
    foreach (array_unique([spro_lang(), 'lv', 'en', 'ru']) as $code) {
        if (!empty($texts[$code])) {
            return $texts[$code];
        }
    }
    return '';
}

/** Parse a euro amount such as 335, "€1 300", "1,300" or "170,50". Null means blank, false means unreadable. */
function spro_detail_amount($raw) {
    if (is_int($raw) || is_float($raw)) {
        return $raw >= 0 ? (float) $raw : false;
    }
    $text = trim(str_ireplace(["\xC2\xA0", "\xE2\x80\xAF", ' ', '€', 'eur'], '', (string) $raw));
    if ($text === '') {
        return null;
    }
    if (preg_match('/^\d+(?:[.,]\d{1,2})?$/', $text)) {
        return (float) str_replace(',', '.', $text);
    }
    if (preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d{1,2})?$/', $text)) {
        return (float) str_replace(',', '', $text);
    }
    return false;
}

function spro_detail_valid_date($value) {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $value);
    return $date && $date->format('Y-m-d') === $value;
}

/** Accept 15:00, 9.30 or 15:00 CET. Returns the normalized time, '' for blank or false when unreadable. */
function spro_detail_time($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?(?:\s+([A-Za-z]{2,5}(?:[+-]\d{1,2})?))?$/', $raw, $m) && (int) $m[1] < 24 && (int) $m[2] < 60) {
        return sprintf('%02d:%02d', $m[1], $m[2]) . (!empty($m[3]) ? ' ' . strtoupper($m[3]) : '');
    }
    return false;
}

function spro_detail_texts($values) {
    $out = [];
    foreach (SPRO_DETAIL_LANGS as $code) {
        $out[$code] = sanitize_text_field((string) ($values[$code] ?? ''));
    }
    return $out;
}

function spro_clean_included($rows) {
    $out = [];
    foreach ((array) $rows as $row) {
        $item = spro_detail_texts($row['item'] ?? []);
        if (!array_filter($item, 'strlen')) {
            continue;
        }
        $from = spro_detail_amount($row['from'] ?? '');
        $to = spro_detail_amount($row['to'] ?? '');
        $from = is_float($from) ? $from : null;
        $to = is_float($to) ? $to : null;
        if ($from !== null && $to !== null && $from > $to) {
            list($from, $to) = [$to, $from];
        }
        $out[] = ['item' => $item, 'from' => $from, 'to' => $to];
        if (count($out) >= SPRO_DETAIL_LIMIT) {
            break;
        }
    }
    return $out;
}

function spro_clean_key_dates($rows) {
    $out = [];
    foreach ((array) $rows as $row) {
        $date = (string) ($row['date'] ?? '');
        $label = spro_detail_texts($row['label'] ?? []);
        if (!spro_detail_valid_date($date) || !array_filter($label, 'strlen')) {
            continue;
        }
        $to = (string) ($row['to'] ?? '');
        $to = spro_detail_valid_date($to) && $to > $date ? $to : '';
        $time = spro_detail_time($row['time'] ?? '');
        $out[] = ['date' => $date, 'to' => $to, 'time' => $time === false ? '' : $time, 'label' => $label];
    }
    usort($out, static function ($a, $b) {
        return strcmp($a['date'] . $a['time'], $b['date'] . $b['time']);
    });
    return array_slice($out, 0, SPRO_DETAIL_LIMIT);
}

function spro_trip_included($id) {
    $rows = get_post_meta($id, '_travel_included', true);
    return is_array($rows) ? $rows : [];
}

function spro_trip_key_dates($id) {
    $rows = get_post_meta($id, '_travel_key_dates', true);
    return is_array($rows) ? $rows : [];
}

function spro_trip_photo($id) {
    $photo = (int) get_post_meta($id, '_travel_photo_id', true);
    return $photo && wp_attachment_is_image($photo) ? $photo : 0;
}

/** The homepage card image. Without one, the card keeps showing the featured image (poster). */
function spro_trip_card_image($id) {
    $image = (int) get_post_meta($id, '_travel_card_image_id', true);
    return $image && wp_attachment_is_image($image) ? $image : 0;
}

/* ---------- Formatting in the visitor's language ---------- */

function spro_format_date($ymd) {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $ymd);
    if (!$date) {
        return (string) $ymd;
    }
    if (spro_lang() === 'en') {
        $months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        return $date->format('j ') . $months[(int) $date->format('n') - 1] . $date->format(' Y');
    }
    return $date->format('d.m.Y');
}

function spro_date_range($from, $to) {
    $a = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $from);
    $b = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $to);
    if (!$a) {
        return $b ? spro_format_date($to) : '';
    }
    if (!$b || $b <= $a) {
        return spro_format_date($from);
    }
    $lang = spro_lang();
    $same_year = $a->format('Y') === $b->format('Y');
    $same_month = $same_year && $a->format('m') === $b->format('m');
    if ($lang === 'en') {
        if ($same_month) {
            return $a->format('j') . '–' . spro_format_date($to);
        }
        $start = spro_format_date($from);
        return ($same_year ? substr($start, 0, -5) : $start) . ' – ' . spro_format_date($to);
    }
    $dot = $lang === 'lv' ? '.' : '';
    if ($same_month) {
        return $a->format('d') . $dot . '–' . $b->format('d.m.Y');
    }
    if ($same_year) {
        return $a->format('d.m') . $dot . '–' . $b->format('d.m.Y');
    }
    return $a->format('d.m.Y') . ' – ' . $b->format('d.m.Y');
}

function spro_nights($from, $to) {
    $a = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $from);
    $b = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $to);
    return $a && $b && $b > $a ? (int) $a->diff($b)->days : 0;
}

function spro_nights_label($n) {
    switch (spro_lang()) {
        case 'en':
            return $n === 1 ? '1 night' : "$n nights";
        case 'ru':
            $word = $n % 10 === 1 && $n % 100 !== 11 ? 'ночь' : (in_array($n % 10, [2, 3, 4], true) && !in_array($n % 100, [12, 13, 14], true) ? 'ночи' : 'ночей');
            return "$n $word";
        default:
            return $n . ($n % 10 === 1 && $n % 100 !== 11 ? ' nakts' : ' naktis');
    }
}

function spro_money($value, $symbol = true) {
    $decimals = floor($value) == $value ? 0 : 2;
    if (spro_lang() === 'en') {
        return ($symbol ? '€' : '') . number_format($value, $decimals, '.', ',');
    }
    $number = number_format($value, $decimals, ',', "\u{00A0}");
    if (!$symbol) {
        return $number;
    }
    return spro_lang() === 'ru' ? $number . "\u{00A0}€" : '€' . $number;
}

function spro_included_price($row) {
    $from = $row['from'] ?? null;
    $to = $row['to'] ?? null;
    if ($from === null && $to === null) {
        return spro_text('Iekļauts', 'Included', 'Включено');
    }
    if ($from !== null && $to !== null) {
        if ($from == $to) {
            return spro_money($from);
        }
        return spro_lang() === 'ru' ? spro_money($from, false) . '–' . spro_money($to) : spro_money($from) . '–' . spro_money($to, false);
    }
    if ($from !== null) {
        return spro_text('no ', 'from ', 'от ') . spro_money($from);
    }
    return spro_text('līdz ', 'up to ', 'до ') . spro_money($to);
}

/** Split a free-text price such as "≈250 €/personai (aplēse)" into the amount and its bracketed note. */
function spro_price_parts($text) {
    $text = trim((string) $text);
    if (preg_match('/^(.+?)\s*\((.+)\)\s*$/us', $text, $m)) {
        return [trim($m[1]), trim($m[2])];
    }
    return [$text, ''];
}

function spro_key_date_when($row) {
    return $row['to'] ? spro_date_range($row['date'], $row['to']) : spro_format_date($row['date']);
}

/** Which part of the trip photo stays visible when the header crops it. */
function spro_photo_positions() {
    return ['top' => ['Top', '50% 15%'], 'center' => ['Centre', '50% 45%'], 'bottom' => ['Bottom', '50% 80%']];
}

function spro_trip_photo_position($id) {
    $positions = spro_photo_positions();
    $key = get_post_meta($id, '_travel_photo_position', true);
    return $positions[$key][1] ?? $positions['center'][1];
}

/* ---------- Edit screen ---------- */

add_action('add_meta_boxes_travel_listing', static function () {
    add_meta_box('spro_trip_details', 'Trip page: photo, included items, key dates and more trips', 'spro_trip_details_box', 'travel_listing', 'normal', 'default');
});

add_action('admin_enqueue_scripts', static function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true) || get_post_type() !== 'travel_listing') {
        return;
    }
    wp_enqueue_media();
    $file = dirname(__DIR__) . '/assets/js/trip-details-admin.js';
    wp_enqueue_script('spro-trip-details', plugins_url('assets/js/trip-details-admin.js', dirname(__DIR__) . '/travel-listings.php'), [], filemtime($file), true);
});

function spro_trip_details_box($post) {
    wp_nonce_field('spro_trip_details', 'spro_trip_details_nonce');
    $photo = spro_trip_photo($post->ID);
    $card = spro_trip_card_image($post->ID);
    $picker = static function ($name, $image, $noun) {
        ?>
        <div class="spro-photo" data-spro-photo data-noun="<?php echo esc_attr($noun); ?>">
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($image ?: ''); ?>">
            <div data-spro-photo-preview><?php if ($image) echo wp_get_attachment_image($image, 'medium'); ?></div>
            <button type="button" class="button" data-spro-photo-choose><?php echo esc_html(($image ? 'Replace ' : 'Choose ') . $noun); ?></button>
            <button type="button" class="button-link button-link-delete" data-spro-photo-remove <?php if (!$image) echo 'hidden'; ?>>Remove <?php echo esc_html($noun); ?></button>
        </div>
        <?php
    };
    $included = spro_trip_included($post->ID);
    $dates = spro_trip_key_dates($post->ID);
    $related = array_map('intval', (array) get_post_meta($post->ID, '_travel_related', true));
    $position = get_post_meta($post->ID, '_travel_photo_position', true) ?: 'center';
    $reserve = get_post_meta($post->ID, '_travel_reserve_url', true);
    $mode = spro_related_mode($post->ID);
    $others = get_posts(['post_type' => 'travel_listing', 'post_status' => ['publish', 'draft', 'pending', 'private', 'future'], 'posts_per_page' => 300,
        'exclude' => [$post->ID], 'orderby' => 'title', 'order' => 'ASC']);
    // Chosen trips first in their saved order, then the rest by start date.
    $rank = array_flip($related);
    usort($others, static function ($a, $b) use ($rank) {
        $ra = $rank[$a->ID] ?? PHP_INT_MAX;
        $rb = $rank[$b->ID] ?? PHP_INT_MAX;
        return ($ra <=> $rb) ?: strcmp(get_post_meta($a->ID, '_travel_date_from', true) ?: '9999', get_post_meta($b->ID, '_travel_date_from', true) ?: '9999');
    });
    $count = (int) spro_site('more_count');
    $auto = $post->post_status === 'auto-draft' ? [] : spro_auto_trips($post->ID, $count);
    $amount = static function ($value) {
        return $value === null ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    };
    $included_row = static function ($i, $row) use ($amount) {
        ob_start(); ?>
        <tr>
            <?php foreach (SPRO_DETAIL_LANGS as $code): ?>
                <td><input type="text" aria-label="Item <?php echo esc_attr(strtoupper($code)); ?>" name="spro_included[<?php echo esc_attr($i); ?>][item][<?php echo esc_attr($code); ?>]" value="<?php echo esc_attr($row['item'][$code] ?? ''); ?>"></td>
            <?php endforeach; ?>
            <td><input type="text" inputmode="decimal" aria-label="Price from" name="spro_included[<?php echo esc_attr($i); ?>][from]" value="<?php echo esc_attr($amount($row['from'] ?? null)); ?>"></td>
            <td><input type="text" inputmode="decimal" aria-label="Price to" name="spro_included[<?php echo esc_attr($i); ?>][to]" value="<?php echo esc_attr($amount($row['to'] ?? null)); ?>"></td>
            <td><button type="button" class="button-link button-link-delete" data-spro-remove>Remove</button></td>
        </tr>
        <?php return ob_get_clean();
    };
    $date_row = static function ($i, $row) {
        ob_start(); ?>
        <tr>
            <td><input type="date" aria-label="Date" name="spro_key_dates[<?php echo esc_attr($i); ?>][date]" value="<?php echo esc_attr($row['date'] ?? ''); ?>"></td>
            <td><input type="date" aria-label="Date to" name="spro_key_dates[<?php echo esc_attr($i); ?>][to]" value="<?php echo esc_attr($row['to'] ?? ''); ?>"></td>
            <td><input type="text" aria-label="Time" placeholder="e.g. 15:00" name="spro_key_dates[<?php echo esc_attr($i); ?>][time]" value="<?php echo esc_attr($row['time'] ?? ''); ?>"></td>
            <?php foreach (SPRO_DETAIL_LANGS as $code): ?>
                <td><input type="text" aria-label="Label <?php echo esc_attr(strtoupper($code)); ?>" name="spro_key_dates[<?php echo esc_attr($i); ?>][label][<?php echo esc_attr($code); ?>]" value="<?php echo esc_attr($row['label'][$code] ?? ''); ?>"></td>
            <?php endforeach; ?>
            <td><button type="button" class="button-link button-link-delete" data-spro-remove>Remove</button></td>
        </tr>
        <?php return ob_get_clean();
    };
    ?>
    <div class="spro-trip-details">
        <p class="description">Each trip can have three pictures. The <strong>Featured image</strong> (in the sidebar) is the poster, shown as the trip flyer. The two below are optional.</p>
        <h3>Homepage card image</h3>
        <p class="description">Shown on this trip’s card on the homepage and in More trips. Use a landscape picture, about twice as wide as it is tall (for example 1200 × 600). Without one, the card shows the featured image.</p>
        <?php $picker('spro_card_image_id', $card, 'card image'); ?>

        <h3>Trip page photo</h3>
        <p class="description">A wide landscape photo shown across the top of the trip page.</p>
        <?php $picker('spro_photo_id', $photo, 'photo'); ?>
        <p><label for="spro_photo_position">Keep this part of the photo visible:</label>
            <select id="spro_photo_position" name="spro_photo_position">
                <?php foreach (spro_photo_positions() as $key => [$label]): ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($position, $key); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select></p>

        <h3>Reserve button</h3>
        <p><label for="spro_reserve_url">Reservation link</label><br>
            <input type="url" id="spro_reserve_url" name="spro_reserve_url" class="regular-text" placeholder="https://" value="<?php echo esc_attr($reserve); ?>"></p>
        <p class="description">Where the Reserve button under Key dates leads, for example a booking form. Leave blank to use the trip’s contact email or phone, or the site-wide ones from Travel Listings → Site and footer, then the Website URL.</p>

        <h3>What’s included</h3>
        <p class="description">One row per item. Enter prices in euros. Fill both prices for a range, the same amount in both for a fixed price, only “Price from” for “from €335”, or leave both blank to show “Included”.</p>
        <div data-spro-repeater>
            <table class="widefat spro-detail-table">
                <thead><tr><th>Item LV</th><th>Item EN</th><th>Item RU</th><th>Price from (€)</th><th>Price to (€)</th><th><span class="screen-reader-text">Remove</span></th></tr></thead>
                <tbody><?php foreach ($included as $i => $row) echo $included_row($i, $row); ?></tbody>
            </table>
            <template><?php echo $included_row('__i__', []); ?></template>
            <p><button type="button" class="button" data-spro-add>Add item</button></p>
        </div>

        <h3>Key dates</h3>
        <p class="description">Ticket sales, registration deadlines, the event itself and travel days. Dates are shown in order. “Date to” is for multi-day events.</p>
        <div data-spro-repeater>
            <table class="widefat spro-detail-table">
                <thead><tr><th>Date</th><th>Date to</th><th>Time</th><th>Label LV</th><th>Label EN</th><th>Label RU</th><th><span class="screen-reader-text">Remove</span></th></tr></thead>
                <tbody><?php foreach ($dates as $i => $row) echo $date_row($i, $row); ?></tbody>
            </table>
            <template><?php echo $date_row('__i__', []); ?></template>
            <p><button type="button" class="button" data-spro-add>Add date</button></p>
        </div>

        <h3>More trips</h3>
        <fieldset class="spro-related" data-spro-related>
            <legend class="screen-reader-text">Which trips appear under More trips on this page</legend>
            <p><label><input type="radio" name="spro_related_mode" value="auto" <?php checked($mode, 'auto'); ?>> <strong>Automatic</strong> – trips in the same category first, then the soonest upcoming trips</label></p>
            <?php if ($auto): ?>
                <p class="description spro-related-preview" data-spro-related-show="auto">Right now this shows: <?php echo esc_html(implode(', ', array_map('get_the_title', $auto))); ?>.</p>
            <?php endif; ?>
            <p><label><input type="radio" name="spro_related_mode" value="manual" <?php checked($mode, 'manual'); ?>> <strong>Choose trips</strong> from the list</label></p>
            <div class="spro-related-list" data-spro-related-show="manual" <?php if ($mode !== 'manual') echo 'hidden'; ?>>
                <p><label class="screen-reader-text" for="spro_related_search">Search trips</label>
                    <input type="search" id="spro_related_search" placeholder="Search trips" data-spro-related-search></p>
                <ul>
                    <?php foreach ($others as $other):
                        $from = get_post_meta($other->ID, '_travel_date_from', true);
                        $terms = get_the_terms($other->ID, 'listing_category');
                        $meta = array_filter([
                            $from ? mysql2date('d.m.Y', $from) : '',
                            $terms && !is_wp_error($terms) ? implode(', ', wp_list_pluck($terms, 'name')) : '',
                            $other->post_status === 'publish' ? '' : 'Not published yet: appears once published',
                        ]);
                        $thumb = spro_trip_photo($other->ID) ?: get_post_thumbnail_id($other->ID); ?>
                        <li data-spro-related-item="<?php echo esc_attr(strtolower($other->post_title)); ?>">
                            <label>
                                <input type="checkbox" name="spro_related[]" value="<?php echo esc_attr($other->ID); ?>" <?php checked(in_array($other->ID, $related, true)); ?>>
                                <span class="spro-related-thumb"><?php if ($thumb) echo wp_get_attachment_image($thumb, [80, 80], false, ['alt' => '']); ?></span>
                                <span><strong><?php echo esc_html($other->post_title); ?></strong><?php if ($meta): ?><br><small><?php echo esc_html(implode(' · ', $meta)); ?></small><?php endif; ?></span>
                            </label>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$others): ?><li>No other trips yet.</li><?php endif; ?>
                </ul>
                <p class="description">The page shows up to <?php echo esc_html($count); ?> of the ticked trips, in this order. With none ticked, it uses the automatic choice.</p>
            </div>
            <p><label><input type="radio" name="spro_related_mode" value="none" <?php checked($mode, 'none'); ?>> <strong>Don’t show</strong> More trips on this page</label></p>
            <p class="description">The number of trips and the heading are set in <a href="<?php echo esc_url(admin_url('edit.php?post_type=travel_listing&page=spro-site-settings')); ?>">Travel Listings → Site and footer</a>.</p>
        </fieldset>
    </div>
    <?php
}

add_action('save_post_travel_listing', static function ($post_id) {
    if (!isset($_POST['spro_trip_details_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spro_trip_details_nonce'])), 'spro_trip_details')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
        return;
    }
    foreach (['spro_photo_id' => '_travel_photo_id', 'spro_card_image_id' => '_travel_card_image_id'] as $field => $meta) {
        $image = absint($_POST[$field] ?? 0);
        if ($image && wp_attachment_is_image($image)) {
            update_post_meta($post_id, $meta, $image);
        } else {
            delete_post_meta($post_id, $meta);
        }
    }
    $reserve = esc_url_raw(trim(wp_unslash($_POST['spro_reserve_url'] ?? '')), ['http', 'https']);
    if ($reserve) {
        update_post_meta($post_id, '_travel_reserve_url', $reserve);
    } else {
        delete_post_meta($post_id, '_travel_reserve_url');
    }
    $mode = sanitize_key(wp_unslash($_POST['spro_related_mode'] ?? 'auto'));
    if (in_array($mode, ['manual', 'none'], true)) {
        update_post_meta($post_id, '_travel_related_mode', $mode);
    } else {
        delete_post_meta($post_id, '_travel_related_mode');
    }
    $position = sanitize_key(wp_unslash($_POST['spro_photo_position'] ?? ''));
    if (isset(spro_photo_positions()[$position]) && $position !== 'center') {
        update_post_meta($post_id, '_travel_photo_position', $position);
    } else {
        delete_post_meta($post_id, '_travel_photo_position');
    }
    $sets = [
        '_travel_included' => spro_clean_included(wp_unslash($_POST['spro_included'] ?? [])),
        '_travel_key_dates' => spro_clean_key_dates(wp_unslash($_POST['spro_key_dates'] ?? [])),
        '_travel_related' => array_values(array_unique(array_filter(array_map('absint', (array) ($_POST['spro_related'] ?? []))))),
    ];
    foreach ($sets as $key => $value) {
        if ($value) {
            update_post_meta($post_id, $key, wp_slash($value));
        } else {
            delete_post_meta($post_id, $key);
        }
    }
});
