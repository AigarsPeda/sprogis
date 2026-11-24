<?php
defined('ABSPATH') || exit;

function spro_text($lv, $en, $ru) {
    global $travel_listings_instance;
    $lang = $travel_listings_instance->get_current_language();
    return ['lv'=>$lv, 'en'=>$en, 'ru'=>$ru][$lang];
}

function spro_icon($name, $size = 20) {
    static $paths = [
        'compass'=>'<circle cx="12" cy="12" r="9"/><path d="m16 8-2.5 5.5L8 16l2.5-5.5Z"/>',
        'pin'=>'<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/><circle cx="12" cy="10" r="2.2"/>',
        'plane'=>'<path d="m21 3-6 18-4-8-8-4 18-6ZM11 13l6-6"/>',
        'mountain'=>'<path d="m2 20 7-13 4 7 3-5 6 11Zm4-7 3 2 2-3"/>',
        'music'=>'<path d="M9 18V5l11-2v13M9 9l11-2"/><ellipse cx="6" cy="18" rx="3" ry="2.5"/><ellipse cx="17" cy="16" rx="3" ry="2.5"/>',
        'trophy'=>'<path d="M8 3h8v5c0 4-2 6-4 6s-4-2-4-6V3Zm0 2H4v2a4 4 0 0 0 5 4m7-6h4v2a4 4 0 0 1-5 4m-3 3v5m-4 2h8"/>',
        'party'=>'<path d="m5 3 7 9 7-9H5Zm7 9v8m-4 1h8M8 7h8"/>',
        'calendar'=>'<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 10h18m-13 5h3"/>',
        'wallet'=>'<rect x="3" y="6" width="18" height="14" rx="3"/><path d="m5 6 11-3 1 3m4 5h-6v4h6"/>',
        'search'=>'<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'globe'=>'<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="3.8" ry="9"/><path d="M3 12h18"/>',
        'arrow'=>'<path d="M4 12h15m-6-6 6 6-6 6"/>',
        'back'=>'<path d="M20 12H5m6-6-6 6 6 6"/>',
        'chevron'=>'<path d="m7 10 5 5 5-5"/>',
        'sort'=>'<path d="M4 6h14M4 12h10M4 18h6"/>',
        'more'=>'<circle cx="4" cy="12" r="1.3"/><circle cx="12" cy="12" r="1.3"/><circle cx="20" cy="12" r="1.3"/>',
        'grid'=>'<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    ];
    return sprintf('<svg aria-hidden="true" focusable="false" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">%2$s</svg>', absint($size), $paths[$name] ?? $paths['compass']);
}
function spro_term_icon($term) {
    $icons = ['concerts'=>'music','sports'=>'trophy','hockey'=>'trophy','football'=>'trophy','lidojumi'=>'plane','pargajieni'=>'mountain','ballites'=>'party'];
    return $term ? ($icons[$term->slug] ?? 'compass') : 'grid';
}
function spro_price($id) {
    global $travel_listings_instance;
    $raw = $travel_listings_instance->get_listing_price($id);
    return preg_match('/^\d+([.,]\d+)?$/', $raw) ? $raw . ' €' : $raw;
}
function spro_get_param($key) {
    return isset($_GET[$key]) && is_string($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
}
function spro_catalogue_surface() {
    if (is_singular(['travel_listing', 'post']) || is_post_type_archive('travel_listing') || is_tax('listing_category')) {
        return true;
    }
    $post = get_queried_object();
    return is_page() && $post instanceof WP_Post && (has_block('travel-listings/listings', $post) || has_shortcode($post->post_content, 'travel_listings'));
}
add_filter('template_include', static function ($template) {
    return spro_catalogue_surface() ? __DIR__ . '/../templates/travel-site.php' : $template;
}, 50);

function spro_catalogue_data($atts) {
    global $travel_listings_instance;
    $fixed = is_tax('listing_category') ? get_queried_object()->slug : $atts['category'];
    $slug = $fixed ?: sanitize_title(spro_get_param('trip_category'));
    $term = $slug ? get_term_by('slug', $slug, 'listing_category') : null;
    $schema = spro_category_filters($term ? $term->term_id : 0);
    $args = ['post_type'=>'travel_listing','post_status'=>'publish','posts_per_page'=>-1,'orderby'=>['date'=>'DESC','ID'=>'DESC']];
    if ($slug) {
        $args['tax_query'] = [['taxonomy'=>'listing_category','field'=>'slug','terms'=>[$slug]]];
    }
    $posts = get_posts($args);
    $choice_keys = [];
    if (in_array('location', $schema['enabled'], true)) {
        $choice_keys['location'] = '_travel_location';
    }
    foreach ($schema['custom'] as $key => $label) {
        $choice_keys['f_' . $key] = '_travel_filter_' . $key;
    }
    $choices = array_fill_keys(array_keys($choice_keys), []);
    $values = [];
    foreach (array_merge($schema['enabled'], array_keys($choice_keys)) as $key) {
        $values[$key] = $atts['show_filter'] === 'yes' ? spro_get_param('trip_' . $key) : '';
    }
    $result = [];
    foreach ($posts as $post) {
        $id = $post->ID;
        $match = true;
        foreach ($choice_keys as $key => $meta) {
            $value = (string) get_post_meta($id, $meta, true);
            if ($value !== '') {
                $choices[$key][$value] = $value;
            }
            if ($values[$key] !== '' && $value !== $values[$key]) {
                $match = false;
            }
        }
        foreach (['date_from','date_to'] as $key) {
            if (empty($values[$key])) {
                continue;
            }
            $date = get_post_meta($id, '_travel_' . $key, true);
            if (!$date || ($key === 'date_from' && $date < $values[$key]) || ($key === 'date_to' && $date > $values[$key])) {
                $match = false;
            }
        }
        $price = $travel_listings_instance->get_listing_price($id);
        $number = $price === '' ? null : $travel_listings_instance->extract_price_value($price);
        foreach (['price_from','price_to'] as $key) {
            if (isset($values[$key]) && is_numeric($values[$key]) && ($number === null || ($key === 'price_from' ? $number < $values[$key] : $number > $values[$key]))) {
                $match = false;
            }
        }
        if ($match) {
            $result[] = ['id'=>$id, 'price'=>$number];
        }
    }
    foreach ($choices as &$options) {
        natcasesort($options);
    }
    unset($options);
    $sort = spro_get_param('trip_sort');
    if ($sort === 'price') {
        usort($result, static function ($a, $b) {
            return (($a['price'] ?? INF) <=> ($b['price'] ?? INF)) ?: ($b['id'] <=> $a['id']);
        });
    }
    $count = count($result);
    $limit = (int) $atts['posts_per_page'];
    $limit = $limit > 0 ? $limit : max(1, $count);
    $pages = max(1, (int) ceil($count / $limit));
    $page = min($pages, max(1, absint(spro_get_param('trip_page'))));
    $ids = array_column(array_slice($result, ($page - 1) * $limit, $limit), 'id');
    return compact('fixed','term','slug','schema','choices','values','sort','count','pages','page','ids');
}
