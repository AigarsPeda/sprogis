<?php
defined('ABSPATH') || exit;

function spro_filter_labels() {
    return ['location'=>'Destination', 'date_from'=>'Start date', 'date_to'=>'End date', 'price_from'=>'Minimum price', 'price_to'=>'Maximum price'];
}

function spro_filter_keys() {
    return array_keys(spro_filter_labels());
}

function spro_category_filters($term_id) {
    $saved = $term_id ? get_term_meta($term_id, '_spro_search_fields', true) : '';
    return is_array($saved) ? $saved : ['enabled' => get_option('travel_listings_default_filters', spro_filter_keys()), 'custom' => []];
}

function spro_category_controls($term = null) {
    $editing = $term instanceof WP_Term;
    $config = spro_category_filters($editing ? $term->term_id : 0);
    if ($editing) {
        echo '<tr class="form-field"><th scope="row">Search filters</th><td>';
    } else {
        echo '<div class="form-field"><strong>Search filters</strong>';
    }
    wp_nonce_field('spro_category_filters', 'spro_filter_nonce', false);
    foreach (spro_filter_labels() as $key => $label) {
        printf('<label style="display:block"><input type="checkbox" name="spro_enabled[]" value="%s" %s> %s</label>', esc_attr($key), checked(in_array($key, $config['enabled'], true), true, false), esc_html($label));
    }
    echo '<p>Custom filters</p><div data-spro-fields>';
    foreach ($config['custom'] as $key => $label) {
        printf('<p><input type="text" aria-label="Filter name" name="spro_fields[%s]" value="%s"> <button type="button" class="button-link-delete" data-remove-field>Remove</button></p>', esc_attr($key), esc_attr($label));
    }
    echo '</div><button type="button" class="button" data-add-field>Add filter</button>';
    echo '<p class="description">Name a field such as departure airport. Enter its value on each listing. Visitors can choose from the published values.</p>';
    echo $editing ? '</td></tr>' : '</div>';
}
add_action('listing_category_add_form_fields', 'spro_category_controls');
add_action('listing_category_edit_form_fields', 'spro_category_controls');

function spro_save_category_filters($term_id) {
    if (empty($_POST['spro_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spro_filter_nonce'])), 'spro_category_filters') || !current_user_can('manage_categories')) {
        return;
    }
    $enabled = array_values(array_intersect(spro_filter_keys(), (array) ($_POST['spro_enabled'] ?? [])));
    $old = spro_category_filters($term_id);
    $custom = [];
    foreach ((array) wp_unslash($_POST['spro_fields'] ?? []) as $key => $label) {
        $label = is_string($label) ? sanitize_text_field($label) : '';
        if ($label !== '') {
            $id = isset($old['custom'][$key]) ? $key : wp_generate_uuid4();
            $custom[$id] = $label;
        }
    }
    update_term_meta($term_id, '_spro_search_fields', ['enabled' => $enabled, 'custom' => $custom]);
}
add_action('created_listing_category', 'spro_save_category_filters');
add_action('edited_listing_category', 'spro_save_category_filters');

function spro_listing_filter_box($post, $box) {
    wp_nonce_field('spro_listing_filters', 'spro_listing_filter_nonce', false);
    echo '<p>Set the values for the categories selected on this listing. Create custom fields under Travel Listings → Categories.</p>';
    foreach ($box['args'] as $term_id => $group) {
        printf('<fieldset data-filter-term="%d"><legend><strong>%s</strong></legend>', $term_id, esc_html($group['name']));
        foreach ($group['fields'] as $key => $label) {
            printf('<p><label>%s<br><input class="regular-text" name="spro_values[%s]" value="%s"></label></p>', esc_html($label), esc_attr($key), esc_attr(get_post_meta($post->ID, '_travel_filter_' . $key, true)));
        }
        echo '</fieldset>';
    }
}
add_action('add_meta_boxes_travel_listing', static function () {
    $terms = get_terms(['taxonomy'=>'listing_category', 'hide_empty'=>false]);
    if (is_wp_error($terms)) return;
    $groups = [];
    foreach ($terms as $term) {
        $fields = spro_category_filters($term->term_id)['custom'];
        if ($fields) {
            $groups[$term->term_id] = ['name'=>$term->name, 'fields'=>$fields];
        }
    }
    if ($groups) {
        add_meta_box('spro-search-values', 'Category filter values', 'spro_listing_filter_box', 'travel_listing', 'advanced', 'default', $groups);
    }
});
add_action('save_post_travel_listing', static function ($post_id) {
    if (empty($_POST['spro_listing_filter_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['spro_listing_filter_nonce'])), 'spro_listing_filters') || !current_user_can('edit_post', $post_id) || wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
        return;
    }
    $values = (array) wp_unslash($_POST['spro_values'] ?? []);
    foreach (wp_get_object_terms($post_id, 'listing_category') as $term) {
        foreach (spro_category_filters($term->term_id)['custom'] as $key => $label) {
            if (isset($values[$key]) && is_string($values[$key])) {
                update_post_meta($post_id, '_travel_filter_' . $key, sanitize_text_field($values[$key]));
            }
        }
    }
});
add_action('admin_enqueue_scripts', static function () {
    $screen = get_current_screen();
    if ($screen && ($screen->taxonomy === 'listing_category' || $screen->post_type === 'travel_listing')) {
        wp_enqueue_script('spro-filter-editor', plugins_url('assets/js/category-editor.js', dirname(__DIR__) . '/travel-listings.php'), [], filemtime(__DIR__ . '/../assets/js/category-editor.js'), true);
    }
});
