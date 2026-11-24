<?php
defined('ABSPATH') || exit;

// Only advertise translated listings when their main content has a translation.
function spro_search_languages($post_id) {
    global $travel_listings_instance;
    $languages = ['lv'];
    $has_description = get_post_meta($post_id, '_travel_description_lv', true) || get_post_field('post_content', $post_id);
    foreach (array_keys($travel_listings_instance->get_languages()) as $code) {
        if ($code !== 'lv' && get_post_meta($post_id, '_travel_title_' . $code, true)
            && (!$has_description || get_post_meta($post_id, '_travel_description_' . $code, true))) {
            $languages[] = $code;
        }
    }
    return $languages;
}

function spro_search_language_url($url, $lang) {
    return $lang === 'lv' ? $url : add_query_arg('lang', $lang, $url);
}

add_action('wp', static function () {
    global $travel_listings_instance;
    if (is_feed() || is_embed() || is_preview() || is_404() || !spro_catalogue_surface() || is_singular('post')) {
        return;
    }

    $id = get_queried_object_id();
    $lang = $travel_listings_instance->get_current_language();
    $languages = array_keys($travel_listings_instance->get_languages());
    $home = home_url('/');
    $noindex = false;
    $is_listing = is_singular('travel_listing');
    if ($is_listing) {
        if (post_password_required($id)) return;
        $base = get_permalink($id);
        $languages = spro_search_languages($id);
        $title = $travel_listings_instance->get_listing_title($id, $lang);
        $facts = [$title, get_post_meta($id, '_travel_location', true), spro_price($id)];
        $description = implode(' · ', array_filter($facts, 'strlen'));
        $noindex = (bool) get_post_meta($id, '_spro_sample_offer', true);
    } else {
        $category = spro_get_param('trip_category');
        $term = is_tax('listing_category') ? get_queried_object() : get_term_by('slug', $category, 'listing_category');
        $base = is_singular('page') ? get_permalink($id) : $home;
        $title = spro_text('Ceļojumi un pasākumi', 'Travel and events', 'Путешествия и события');
        $description = spro_text(
            'Atrodi ceļojumus un pasākumus pēc galamērķa, datuma un budžeta. Apskati piedāvājumus un ceļojumu aprakstus vietnē SproTravel.',
            'Browse trips and events by destination, date and budget. Explore the offers and itineraries on SproTravel.',
            'Ищите путешествия и события по направлению, датам и бюджету. Смотрите предложения и описания поездок на SproTravel.'
        );
        if ($term instanceof WP_Term) {
            $base = get_term_link($term);
            if (is_wp_error($base)) return;
            $title = $term->name;
            $description = $title . ' · ' . $description;
            // Category names are shared across the language views.
            $languages = ['lv'];
        } elseif ($category !== '') {
            $noindex = true;
        }
        foreach (array_keys($_GET) as $key) {
            if (strpos((string) $key, 'trip_') === 0 && !in_array($key, ['trip_category','trip_page'], true)) {
                $noindex = true;
            }
        }
        $page = (int) spro_get_param('trip_page');
        if ($page > 1) {
            $base = add_query_arg('trip_page', $page, $base);
            // Even one offer per page cannot reach beyond the number of matching posts.
            $upper_bound = $term instanceof WP_Term ? $term->count : wp_count_posts('travel_listing')->publish;
            if ($page > $upper_bound) $noindex = true;
        }
    }
    $canonical = spro_search_language_url($base, in_array($lang, $languages, true) ? $lang : 'lv');
    remove_action('wp_head', 'rel_canonical');
    add_filter('pre_get_document_title', static function () use ($title) {
        return esc_html(wp_strip_all_tags($title) . ' | SproTravel');
    });
    if ($noindex) add_filter('wp_robots', 'wp_robots_no_robots');
    add_action('wp_head', static function () use ($canonical, $base, $description, $languages, $home, $noindex) {
        printf('<link rel="canonical" href="%s">' . "\n", esc_url($canonical));
        printf('<meta name="description" content="%s">' . "\n", esc_attr(wp_strip_all_tags($description)));
        if ($noindex) return;
        if (count($languages) > 1) {
            foreach ($languages as $language) {
                printf('<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr($language), esc_url(spro_search_language_url($base, $language)));
            }
            printf('<link rel="alternate" hreflang="x-default" href="%s">' . "\n", esc_url($base));
        }
        if (is_front_page() && $base === $home) {
            echo '<script type="application/ld+json">' . wp_json_encode([
                '@context'=>'https://schema.org', '@type'=>'WebSite', 'name'=>'SproTravel', 'url'=>$home
            ], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
        }
    }, 2);
});

add_filter('wp_sitemaps_posts_query_args', static function ($args, $type) {
    if ($type === 'travel_listing') {
        $args['meta_query'][] = ['key'=>'_spro_sample_offer', 'compare'=>'NOT EXISTS'];
    }
    return $args;
}, 10, 2);
