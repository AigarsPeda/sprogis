<?php
defined('ABSPATH') || exit;

/*
 * Site-wide content editors change in WordPress: contact details for the "Ask" button,
 * the footer and the "More trips" row on trip pages. Footer trip links are WordPress menus.
 */

function spro_site_defaults() {
    return [
        'contact_email' => '',
        'contact_phone' => '',
        'contact_note' => ['lv' => '', 'en' => '', 'ru' => ''],
        'footer_text' => [
            'lv' => 'Ceļojumi uz koncertiem, festivāliem, sporta notikumiem un pilsētām ar datumiem un cenām vienuviet.',
            'en' => 'Concert, festival, sports and city trips with dates and prices side by side.',
            'ru' => 'Поездки на концерты, фестивали, спортивные события и в города с датами и ценами в одном месте.',
        ],
        'company' => ['lv' => '', 'en' => '', 'ru' => ''],
        'title_trips' => ['lv' => 'Ceļojumi', 'en' => 'Trips', 'ru' => 'Поездки'],
        'title_contact' => ['lv' => 'Kontakti', 'en' => 'Contact', 'ru' => 'Контакты'],
        'title_company' => ['lv' => 'Uzņēmums', 'en' => 'Company', 'ru' => 'Компания'],
        'reserve_label' => ['lv' => 'Rezervēt', 'en' => 'Reserve', 'ru' => 'Забронировать'],
        'reserve_enabled' => 1,
        'more_enabled' => 1,
        'more_count' => 3,
        'more_title' => ['lv' => 'Citi ceļojumi', 'en' => 'More trips', 'ru' => 'Другие поездки'],
    ];
}

function spro_site($key) {
    $defaults = spro_site_defaults();
    $saved = get_option('spro_site', []);
    $value = is_array($saved) && array_key_exists($key, $saved) ? $saved[$key] : $defaults[$key];
    if (!is_array($defaults[$key])) {
        return $value;
    }
    $text = spro_pick((array) $value);
    // Headings never disappear: a blank heading uses the standard wording.
    return $text === '' && (strpos($key, 'title') !== false || $key === 'reserve_label') ? spro_pick($defaults[$key]) : $text;
}

function spro_site_sanitize($input) {
    $defaults = spro_site_defaults();
    $input = is_array($input) ? $input : [];
    $out = [];
    foreach ($defaults as $key => $default) {
        $value = $input[$key] ?? null;
        if (is_array($default)) {
            $out[$key] = [];
            foreach (SPRO_DETAIL_LANGS as $code) {
                $text = (string) ($value[$code] ?? '');
                $out[$key][$code] = in_array($key, ['company', 'contact_note', 'footer_text'], true) ? sanitize_textarea_field($text) : sanitize_text_field($text);
            }
        } elseif ($key === 'contact_email') {
            $out[$key] = sanitize_email((string) $value);
        } elseif (in_array($key, ['more_enabled', 'reserve_enabled'], true)) {
            $out[$key] = empty($value) ? 0 : 1;
        } elseif ($key === 'more_count') {
            $out[$key] = min(6, max(1, absint($value ?: 3)));
        } else {
            $out[$key] = sanitize_text_field((string) $value);
        }
    }
    return $out;
}

add_action('init', static function () {
    register_nav_menus([
        'spro-footer-lv' => 'SproTravel footer trip links (Latvian)',
        'spro-footer-en' => 'SproTravel footer trip links (English)',
        'spro-footer-ru' => 'SproTravel footer trip links (Russian)',
    ]);
});

add_action('admin_init', static function () {
    register_setting('spro_site_settings', 'spro_site', ['type' => 'array', 'sanitize_callback' => 'spro_site_sanitize', 'default' => spro_site_defaults()]);
});

add_action('admin_menu', static function () {
    add_submenu_page('edit.php?post_type=travel_listing', 'Site and footer', 'Site and footer', 'manage_options', 'spro-site-settings', 'spro_site_settings_page');
});

function spro_site_settings_page() {
    $saved = wp_parse_args((array) get_option('spro_site', []), spro_site_defaults());
    $names = ['lv' => 'Latvian', 'en' => 'English', 'ru' => 'Russian'];
    $defaults = spro_site_defaults();
    $per_language = static function ($key, $label, $help = '', $multiline = false) use ($saved, $names, $defaults) {
        ?>
        <tr>
            <th scope="row"><?php echo esc_html($label); ?></th>
            <td>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;max-width:960px">
                <?php foreach ($names as $code => $name): $id = "spro_site_{$key}_{$code}"; ?>
                    <p style="margin:0"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($name); ?></label><br>
                    <?php if ($multiline): ?>
                        <textarea id="<?php echo esc_attr($id); ?>" name="spro_site[<?php echo esc_attr($key); ?>][<?php echo esc_attr($code); ?>]" rows="3" style="width:100%"><?php echo esc_textarea($saved[$key][$code] ?? ''); ?></textarea>
                    <?php else: ?>
                        <input id="<?php echo esc_attr($id); ?>" type="text" name="spro_site[<?php echo esc_attr($key); ?>][<?php echo esc_attr($code); ?>]" value="<?php echo esc_attr($saved[$key][$code] ?? ''); ?>" placeholder="<?php echo esc_attr($defaults[$key][$code] ?? ''); ?>" style="width:100%">
                    <?php endif; ?></p>
                <?php endforeach; ?>
                </div>
                <?php if ($help): ?><p class="description"><?php echo esc_html($help); ?></p><?php endif; ?>
            </td>
        </tr>
        <?php
    };
    ?>
    <div class="wrap">
        <h1>Site and footer</h1>
        <p>These texts appear on every catalogue and trip page. Leave a language blank to show the Latvian text there.</p>
        <form method="post" action="options.php">
            <?php settings_fields('spro_site_settings'); ?>

            <h2>Contact details</h2>
            <p>Trip pages show an “Ask SproTravel” button that uses these details unless the trip has its own contact email or phone. The footer shows them under Contact.</p>
            <table class="form-table" role="presentation">
                <tr><th scope="row"><label for="spro_site_contact_email">Email</label></th>
                    <td><input id="spro_site_contact_email" type="email" class="regular-text" name="spro_site[contact_email]" value="<?php echo esc_attr($saved['contact_email']); ?>"></td></tr>
                <tr><th scope="row"><label for="spro_site_contact_phone">Phone</label></th>
                    <td><input id="spro_site_contact_phone" type="tel" class="regular-text" name="spro_site[contact_phone]" value="<?php echo esc_attr($saved['contact_phone']); ?>">
                    <p class="description">Include the country code, for example +371 20 000 000.</p></td></tr>
                <?php $per_language('contact_note', 'Extra contact line', 'Optional, for example office hours or an address.', true); ?>
            </table>

            <h2>Footer</h2>
            <table class="form-table" role="presentation">
                <?php $per_language('footer_text', 'Short description', 'Shown under the logo.', true); ?>
                <?php $per_language('title_trips', 'Trip links heading', 'Blank headings use the wording shown in grey.'); ?>
                <?php $per_language('title_contact', 'Contact heading'); ?>
                <?php $per_language('title_company', 'Company heading'); ?>
                <?php $per_language('company', 'Company details', 'One item per line, for example the company name, registration number and address. Leave blank to hide the column.', true); ?>
                <tr><th scope="row">Trip links</th>
                    <td><p>Edit the links in <a href="<?php echo esc_url(admin_url('nav-menus.php?action=locations')); ?>">Appearance → Menus</a>. Create one menu per language and assign it to “SproTravel footer trip links”. Without a menu, the footer lists the categories that have published trips.</p></td></tr>
            </table>

            <h2>Reserve button on trip pages</h2>
            <p>Shown under Key dates. It opens the trip’s own Reservation link. Without one, it uses the trip’s contact email or phone, then the contact details on this page, then the trip’s Website URL. With none of these, the button is hidden.</p>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Show</th>
                    <td><input type="hidden" name="spro_site[reserve_enabled]" value="0"><label><input type="checkbox" name="spro_site[reserve_enabled]" value="1" <?php checked(!empty($saved['reserve_enabled'])); ?>> Show the Reserve button</label></td></tr>
                <?php $per_language('reserve_label', 'Button text'); ?>
            </table>

            <h2>More trips on trip pages</h2>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Show</th>
                    <td><input type="hidden" name="spro_site[more_enabled]" value="0"><label><input type="checkbox" name="spro_site[more_enabled]" value="1" <?php checked(!empty($saved['more_enabled'])); ?>> Show other trips at the bottom of each trip page</label></td></tr>
                <tr><th scope="row"><label for="spro_site_more_count">Number of trips</label></th>
                    <td><input id="spro_site_more_count" type="number" min="1" max="6" name="spro_site[more_count]" value="<?php echo esc_attr($saved['more_count']); ?>" class="small-text">
                    <p class="description">Each trip can pick its trips automatically, choose them from a list, or hide the row, in the “More trips” part of its edit screen.</p></td></tr>
                <?php $per_language('more_title', 'Heading'); ?>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/** How a trip picks its "More trips": auto, manual (its own list) or none. Older trips with a saved list count as manual. */
function spro_related_mode($id) {
    $mode = get_post_meta($id, '_travel_related_mode', true);
    if (in_array($mode, ['auto', 'manual', 'none'], true)) {
        return $mode;
    }
    return array_filter((array) get_post_meta($id, '_travel_related', true)) ? 'manual' : 'auto';
}

/** Trips for the "More trips" row: the trip's own list, or same category first, then the soonest. */
function spro_more_trips($id) {
    if (!spro_site('more_enabled')) {
        return [];
    }
    $count = (int) spro_site('more_count');
    $mode = spro_related_mode($id);
    if ($mode === 'none') {
        return [];
    }
    if ($mode === 'manual') {
        // Post 0 would mean "the current post" to WordPress, so drop empty values first.
        $chosen = array_values(array_filter(array_map('absint', (array) get_post_meta($id, '_travel_related', true)), static function ($other) use ($id) {
            $post = $other ? get_post($other) : null;
            return $post && $other !== $id && $post->post_type === 'travel_listing' && $post->post_status === 'publish';
        }));
        if ($chosen) {
            return array_slice($chosen, 0, $count);
        }
    }
    return spro_auto_trips($id, $count);
}

function spro_auto_trips($id, $count) {
    $terms = wp_get_post_terms($id, 'listing_category', ['fields' => 'ids']);
    $today = current_time('Y-m-d');
    $candidates = [];
    foreach (get_posts(['post_type' => 'travel_listing', 'post_status' => 'publish', 'posts_per_page' => 100, 'exclude' => [$id], 'fields' => 'ids']) as $other) {
        $from = (string) get_post_meta($other, '_travel_date_from', true);
        $shared = !is_wp_error($terms) && $terms && has_term($terms, 'listing_category', $other);
        $candidates[] = [$shared ? 0 : 1, $from !== '' && $from >= $today ? 0 : 1, $from ?: '9999', $other];
    }
    sort($candidates);
    return array_slice(array_column($candidates, 3), 0, $count);
}

/** Footer trip links: the language's WordPress menu, or the categories that have published trips. */
function spro_footer_links($home) {
    $location = 'spro-footer-' . spro_lang();
    $locations = get_nav_menu_locations();
    if (!empty($locations[$location])) {
        $items = wp_get_nav_menu_items($locations[$location]);
        if ($items) {
            return array_map(static function ($item) {
                return [$item->url, $item->title];
            }, array_filter($items, static function ($item) {
                return !$item->menu_item_parent;
            }));
        }
    }
    $links = [];
    $terms = get_terms(['taxonomy' => 'listing_category', 'hide_empty' => true]);
    foreach (is_wp_error($terms) ? [] : $terms as $term) {
        $links[] = [add_query_arg('trip_category', $term->slug, $home) . '#offers', $term->name];
    }
    return $links;
}

function spro_site_footer($home, $logo) {
    $email = spro_site('contact_email');
    $phone = spro_site('contact_phone');
    $note = spro_site('contact_note');
    $company = spro_site('company');
    $links = spro_footer_links($home);
    ?>
    <footer class="sp-footer"><div class="sp-wrap sp-footer-grid">
        <div class="sp-footer-brand">
            <a href="<?php echo esc_url($home); ?>"><img width="1600" height="585" src="<?php echo esc_url($logo); ?>" alt="SproTravel"></a>
            <?php if (spro_site('footer_text')): ?><p><?php echo nl2br(esc_html(spro_site('footer_text'))); ?></p><?php endif; ?>
        </div>
        <nav class="sp-footer-col sp-footer-links" aria-label="<?php echo esc_attr(spro_site('title_trips')); ?>">
            <h2><?php echo esc_html(spro_site('title_trips')); ?></h2>
            <ul>
                <?php foreach ($links as [$url, $label]): ?><li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li><?php endforeach; ?>
                <li><a href="<?php echo esc_url($home . '#offers'); ?>"><?php echo esc_html(spro_text('Visi piedāvājumi', 'All offers', 'Все предложения')); ?></a></li>
            </ul>
        </nav>
        <?php if ($email || $phone || $note): ?>
            <div class="sp-footer-col">
                <h2><?php echo esc_html(spro_site('title_contact')); ?></h2>
                <ul>
                    <?php if ($email): ?><li><a href="mailto:<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></a></li><?php endif; ?>
                    <?php if ($phone): ?><li><a href="tel:<?php echo esc_attr(preg_replace('/[^\d+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a></li><?php endif; ?>
                    <?php if ($note): ?><li><?php echo nl2br(esc_html($note)); ?></li><?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if ($company): ?>
            <div class="sp-footer-col">
                <h2><?php echo esc_html(spro_site('title_company')); ?></h2>
                <p><?php echo nl2br(esc_html($company)); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <div class="sp-wrap sp-footer-bottom"><span>&copy; <?php echo esc_html(wp_date('Y')); ?> SproTravel</span></div></footer>
    <?php
}
