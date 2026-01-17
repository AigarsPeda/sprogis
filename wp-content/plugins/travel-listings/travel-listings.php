<?php
/**
 * Plugin Name: Travel Listings
 * Description: A custom plugin to display travel/event listings with date range filtering
 * Version: 1.0.0
 * Author: Sprogis
 * Text Domain: travel-listings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Travel_Listings {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_shortcode('travel_listings', array($this, 'display_listings_shortcode'));
        add_action('wp_ajax_filter_travel_listings', array($this, 'ajax_filter_listings'));
        add_action('wp_ajax_nopriv_filter_travel_listings', array($this, 'ajax_filter_listings'));
        
        // Add custom columns to admin
        add_filter('manage_travel_listing_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_travel_listing_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
        add_filter('manage_edit-travel_listing_sortable_columns', array($this, 'sortable_columns'));
        
        // Template loading
        add_filter('single_template', array($this, 'load_single_template'));
        add_filter('archive_template', array($this, 'load_archive_template'));
        
        // Admin settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add Settings Page
     */
    public function add_settings_page() {
        add_submenu_page(
            'edit.php?post_type=travel_listing',
            __('Hero Settings', 'travel-listings'),
            __('Hero Settings', 'travel-listings'),
            'manage_options',
            'travel-listings-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register Settings
     */
    public function register_settings() {
        register_setting('travel_listings_settings', 'travel_listings_hero_title');
        register_setting('travel_listings_settings', 'travel_listings_hero_subtitle');
        register_setting('travel_listings_settings', 'travel_listings_hero_image');
    }
    
    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Travel Listings - Hero Settings', 'travel-listings'); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields('travel_listings_settings'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="travel_listings_hero_title"><?php _e('Hero Title', 'travel-listings'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="travel_listings_hero_title" name="travel_listings_hero_title" 
                                   value="<?php echo esc_attr(get_option('travel_listings_hero_title')); ?>" 
                                   class="regular-text" style="width: 100%; max-width: 500px;">
                            <p class="description"><?php _e('The main headline displayed on the hero section.', 'travel-listings'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="travel_listings_hero_subtitle"><?php _e('Hero Subtitle', 'travel-listings'); ?></label>
                        </th>
                        <td>
                            <textarea id="travel_listings_hero_subtitle" name="travel_listings_hero_subtitle" 
                                      rows="3" class="large-text" style="max-width: 500px;"><?php echo esc_textarea(get_option('travel_listings_hero_subtitle')); ?></textarea>
                            <p class="description"><?php _e('A short description below the title.', 'travel-listings'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="travel_listings_hero_image"><?php _e('Hero Background Image', 'travel-listings'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="travel_listings_hero_image" name="travel_listings_hero_image" 
                                   value="<?php echo esc_url(get_option('travel_listings_hero_image')); ?>" 
                                   class="regular-text" style="width: 100%; max-width: 500px;">
                            <button type="button" class="button" id="upload_hero_image_button"><?php _e('Select Image', 'travel-listings'); ?></button>
                            <p class="description"><?php _e('URL of the background image. Use the "Select Image" button to choose from media library.', 'travel-listings'); ?></p>
                            
                            <?php if (get_option('travel_listings_hero_image')): ?>
                            <div style="margin-top: 10px;">
                                <img src="<?php echo esc_url(get_option('travel_listings_hero_image')); ?>" style="max-width: 300px; height: auto; border-radius: 8px;">
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            
            <h2><?php _e('Usage', 'travel-listings'); ?></h2>
            <p><?php _e('Use this shortcode to display listings with the hero section:', 'travel-listings'); ?></p>
            <code style="display: block; padding: 15px; background: #f0f0f0; border-radius: 4px; margin: 10px 0;">[travel_listings]</code>
            <p><?php _e('The hero settings above will be automatically applied.', 'travel-listings'); ?></p>
            
            <p><?php _e('Or override with shortcode attributes:', 'travel-listings'); ?></p>
            <code style="display: block; padding: 15px; background: #f0f0f0; border-radius: 4px; margin: 10px 0;">[travel_listings hero_title="Your Title" hero_subtitle="Your subtitle" hero_image="https://example.com/image.jpg"]</code>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#upload_hero_image_button').on('click', function(e) {
                e.preventDefault();
                
                var mediaUploader = wp.media({
                    title: '<?php _e('Select Hero Image', 'travel-listings'); ?>',
                    button: { text: '<?php _e('Use this image', 'travel-listings'); ?>' },
                    multiple: false
                });
                
                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#travel_listings_hero_image').val(attachment.url);
                });
                
                mediaUploader.open();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Load custom single template
     */
    public function load_single_template($template) {
        global $post;
        
        if ($post->post_type === 'travel_listing') {
            $plugin_template = plugin_dir_path(__FILE__) . 'templates/single-travel_listing.php';
            
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Load custom archive template
     */
    public function load_archive_template($template) {
        if (is_post_type_archive('travel_listing')) {
            $plugin_template = plugin_dir_path(__FILE__) . 'templates/archive-travel_listing.php';
            
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        
        return $template;
    }
    
    /**
     * Register Custom Post Type
     */
    public function register_post_type() {
        $labels = array(
            'name'                  => __('Travel Listings', 'travel-listings'),
            'singular_name'         => __('Travel Listing', 'travel-listings'),
            'menu_name'             => __('Travel Listings', 'travel-listings'),
            'add_new'               => __('Add New', 'travel-listings'),
            'add_new_item'          => __('Add New Listing', 'travel-listings'),
            'edit_item'             => __('Edit Listing', 'travel-listings'),
            'new_item'              => __('New Listing', 'travel-listings'),
            'view_item'             => __('View Listing', 'travel-listings'),
            'search_items'          => __('Search Listings', 'travel-listings'),
            'not_found'             => __('No listings found', 'travel-listings'),
            'not_found_in_trash'    => __('No listings found in Trash', 'travel-listings'),
            'all_items'             => __('All Listings', 'travel-listings'),
        );
        
        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => true,
            'rewrite'             => array('slug' => 'travel-listing'),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'menu_position'       => 5,
            'menu_icon'           => 'dashicons-airplane',
            'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
            'show_in_rest'        => false,
        );
        
        register_post_type('travel_listing', $args);
        
        // Register taxonomy for categories
        register_taxonomy('listing_category', 'travel_listing', array(
            'labels' => array(
                'name'              => __('Categories', 'travel-listings'),
                'singular_name'     => __('Category', 'travel-listings'),
                'search_items'      => __('Search Categories', 'travel-listings'),
                'all_items'         => __('All Categories', 'travel-listings'),
                'edit_item'         => __('Edit Category', 'travel-listings'),
                'update_item'       => __('Update Category', 'travel-listings'),
                'add_new_item'      => __('Add New Category', 'travel-listings'),
                'new_item_name'     => __('New Category Name', 'travel-listings'),
                'menu_name'         => __('Categories', 'travel-listings'),
            ),
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_rest'      => true,
            'rewrite'           => array('slug' => 'listing-category'),
        ));
    }
    
    /**
     * Add Meta Boxes for date fields
     */
    public function add_meta_boxes() {
        add_meta_box(
            'travel_listing_dates',
            __('📅 Listing Dates (Required)', 'travel-listings'),
            array($this, 'render_date_meta_box'),
            'travel_listing',
            'normal',
            'high'
        );
        
        add_meta_box(
            'travel_listing_details',
            __('Listing Details', 'travel-listings'),
            array($this, 'render_details_meta_box'),
            'travel_listing',
            'normal',
            'high'
        );
    }
    
    /**
     * Render Date Meta Box
     */
    public function render_date_meta_box($post) {
        wp_nonce_field('travel_listing_dates_nonce', 'travel_listing_dates_nonce');
        
        $date_from = get_post_meta($post->ID, '_travel_date_from', true);
        $date_to = get_post_meta($post->ID, '_travel_date_to', true);
        
        ?>
        <style>
            .travel-dates-wrapper {
                display: flex;
                gap: 20px;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 8px;
                margin: 10px 0;
            }
            .travel-date-field {
                flex: 1;
            }
            .travel-date-field label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
            }
            .travel-date-field input[type="date"] {
                width: 100%;
                padding: 10px;
                font-size: 14px;
                border: 1px solid #8c8f94;
                border-radius: 4px;
            }
        </style>
        <div class="travel-dates-wrapper">
            <div class="travel-date-field">
                <label for="travel_date_from"><?php _e('Date From:', 'travel-listings'); ?></label>
                <input type="date" id="travel_date_from" name="travel_date_from" value="<?php echo esc_attr($date_from); ?>">
            </div>
            <div class="travel-date-field">
                <label for="travel_date_to"><?php _e('Date To:', 'travel-listings'); ?></label>
                <input type="date" id="travel_date_to" name="travel_date_to" value="<?php echo esc_attr($date_to); ?>">
            </div>
        </div>
        <?php
    }
    
    /**
     * Render Details Meta Box
     */
    public function render_details_meta_box($post) {
        $location = get_post_meta($post->ID, '_travel_location', true);
        $price = get_post_meta($post->ID, '_travel_price', true);
        $price_on_image = get_post_meta($post->ID, '_travel_price_on_image', true);
        $contact_email = get_post_meta($post->ID, '_travel_contact_email', true);
        $contact_phone = get_post_meta($post->ID, '_travel_contact_phone', true);
        $website_url = get_post_meta($post->ID, '_travel_website_url', true);

        ?>
        <table class="form-table">
            <tr>
                <th><label for="travel_location"><?php _e('Location:', 'travel-listings'); ?></label></th>
                <td><input type="text" id="travel_location" name="travel_location" value="<?php echo esc_attr($location); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="travel_price"><?php _e('Price:', 'travel-listings'); ?></label></th>
                <td>
                    <input type="text" id="travel_price" name="travel_price" value="<?php echo esc_attr($price); ?>" class="regular-text" placeholder="e.g., €99 or Free">
                </td>
            </tr>
            <tr>
                <th><label for="travel_price_on_image"><?php _e('Price Display:', 'travel-listings'); ?></label></th>
                <td>
                    <label>
                        <input type="checkbox" id="travel_price_on_image" name="travel_price_on_image" value="1" <?php checked($price_on_image, '1'); ?>>
                        <?php _e('Show price badge on image', 'travel-listings'); ?>
                    </label>
                    <p class="description"><?php _e('If checked, price will display as a badge on the image. Otherwise, it will appear with other listing details.', 'travel-listings'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="travel_contact_email"><?php _e('Contact Email:', 'travel-listings'); ?></label></th>
                <td><input type="email" id="travel_contact_email" name="travel_contact_email" value="<?php echo esc_attr($contact_email); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="travel_contact_phone"><?php _e('Contact Phone:', 'travel-listings'); ?></label></th>
                <td><input type="tel" id="travel_contact_phone" name="travel_contact_phone" value="<?php echo esc_attr($contact_phone); ?>" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="travel_website_url"><?php _e('Website URL:', 'travel-listings'); ?></label></th>
                <td><input type="url" id="travel_website_url" name="travel_website_url" value="<?php echo esc_attr($website_url); ?>" class="regular-text"></td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Save Meta Box Data
     */
    public function save_meta_boxes($post_id) {
        // Check nonce
        if (!isset($_POST['travel_listing_dates_nonce']) || !wp_verify_nonce($_POST['travel_listing_dates_nonce'], 'travel_listing_dates_nonce')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save date fields
        if (isset($_POST['travel_date_from'])) {
            update_post_meta($post_id, '_travel_date_from', sanitize_text_field($_POST['travel_date_from']));
        }
        
        if (isset($_POST['travel_date_to'])) {
            update_post_meta($post_id, '_travel_date_to', sanitize_text_field($_POST['travel_date_to']));
        }
        
        // Save detail fields
        if (isset($_POST['travel_location'])) {
            update_post_meta($post_id, '_travel_location', sanitize_text_field($_POST['travel_location']));
        }
        
        if (isset($_POST['travel_price'])) {
            update_post_meta($post_id, '_travel_price', sanitize_text_field($_POST['travel_price']));
        }

        // Save price display option (checkbox)
        $price_on_image = isset($_POST['travel_price_on_image']) ? '1' : '0';
        update_post_meta($post_id, '_travel_price_on_image', $price_on_image);

        if (isset($_POST['travel_contact_email'])) {
            update_post_meta($post_id, '_travel_contact_email', sanitize_email($_POST['travel_contact_email']));
        }
        
        if (isset($_POST['travel_contact_phone'])) {
            update_post_meta($post_id, '_travel_contact_phone', sanitize_text_field($_POST['travel_contact_phone']));
        }
        
        if (isset($_POST['travel_website_url'])) {
            update_post_meta($post_id, '_travel_website_url', esc_url_raw($_POST['travel_website_url']));
        }
    }
    
    /**
     * Enqueue Frontend Scripts and Styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'travel-listings-style',
            plugin_dir_url(__FILE__) . 'assets/css/travel-listings.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'travel-listings-script',
            plugin_dir_url(__FILE__) . 'assets/js/travel-listings.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('travel-listings-script', 'travelListings', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('travel_listings_filter'),
        ));
    }
    
    /**
     * Enqueue Admin Scripts/Users/aigarspeda/Library/Application Support/CleanShot/media/media_k4AtKaOfWV/CleanShot 2026-01-17 at 18.28.28@2x.png
     */
    public function admin_enqueue_scripts($hook) {
        global $post_type;
        
        if ($post_type === 'travel_listing') {
            wp_enqueue_style(
                'travel-listings-admin-style',
                plugin_dir_url(__FILE__) . 'assets/css/admin.css',
                array(),
                '1.0.0'
            );
        }
        
        // Enqueue media uploader on settings page
        if ($hook === 'travel_listing_page_travel-listings-settings') {
            wp_enqueue_media();
        }
    }
    
    /**
     * Add Admin Columns
     */
    public function add_admin_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            if ($key === 'title') {
                $new_columns['date_from'] = __('Date From', 'travel-listings');
                $new_columns['date_to'] = __('Date To', 'travel-listings');
                $new_columns['location'] = __('Location', 'travel-listings');
                $new_columns['price'] = __('Price', 'travel-listings');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render Admin Columns
     */
    public function render_admin_columns($column, $post_id) {
        switch ($column) {
            case 'date_from':
                $date = get_post_meta($post_id, '_travel_date_from', true);
                echo $date ? date_i18n(get_option('date_format'), strtotime($date)) : '—';
                break;
            case 'date_to':
                $date = get_post_meta($post_id, '_travel_date_to', true);
                echo $date ? date_i18n(get_option('date_format'), strtotime($date)) : '—';
                break;
            case 'location':
                echo esc_html(get_post_meta($post_id, '_travel_location', true)) ?: '—';
                break;
            case 'price':
                echo esc_html(get_post_meta($post_id, '_travel_price', true)) ?: '—';
                break;
        }
    }
    
    /**
     * Sortable Columns
     */
    public function sortable_columns($columns) {
        $columns['date_from'] = 'date_from';
        $columns['date_to'] = 'date_to';
        return $columns;
    }
    
    /**
     * Display Listings Shortcode
     */
    public function display_listings_shortcode($atts) {
        // Get saved settings as defaults
        $default_title = get_option('travel_listings_hero_title', '');
        $default_subtitle = get_option('travel_listings_hero_subtitle', '');
        $default_image = get_option('travel_listings_hero_image', '');
        
        $atts = shortcode_atts(array(
            'posts_per_page' => 12,
            'category'       => '',
            'show_filter'    => 'yes',
            'hero_title'     => $default_title,
            'hero_subtitle'  => $default_subtitle,
            'hero_image'     => $default_image,
            'show_hero'      => 'yes',
        ), $atts);
        
        ob_start();
        
        // Render hero section if enabled and title or image is provided
        if ($atts['show_hero'] === 'yes' && (!empty($atts['hero_title']) || !empty($atts['hero_image']))) {
            $this->render_hero_section($atts);
        }
        
        $this->render_listings($atts);
        
        return ob_get_clean();
    }
    
    /**
     * Render Hero Section
     */
    public function render_hero_section($atts) {
        $hero_style = '';
        if (!empty($atts['hero_image'])) {
            $hero_style = 'background-image: url(' . esc_url($atts['hero_image']) . ');';
        }
        ?>
        <div class="travel-hero-wrapper">
            <section class="travel-hero-section" style="<?php echo esc_attr($hero_style); ?>">
                <div class="travel-hero-overlay"></div>
                <div class="travel-hero-content">
                    <?php if (!empty($atts['hero_title'])): ?>
                    <h1 class="travel-hero-title"><?php echo esc_html($atts['hero_title']); ?></h1>
                    <?php endif; ?>
                    <?php if (!empty($atts['hero_subtitle'])): ?>
                    <p class="travel-hero-subtitle"><?php echo esc_html($atts['hero_subtitle']); ?></p>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <?php
    }
    
    /**
     * Render Listings
     */
    public function render_listings($atts, $ajax = false) {
        $args = array(
            'post_type'      => 'travel_listing',
            'posts_per_page' => intval($atts['posts_per_page']),
            'post_status'    => 'publish',
            'orderby'        => 'meta_value',
            'meta_key'       => '_travel_date_from',
            'order'          => 'ASC',
            'meta_query'     => array(
                'relation' => 'AND',
            ),
        );
        
        // Filter by date from
        if (!empty($atts['date_from'])) {
            $args['meta_query'][] = array(
                'key'     => '_travel_date_from',
                'value'   => $atts['date_from'],
                'compare' => '>=',
                'type'    => 'DATE',
            );
        }
        
        // Filter by date to
        if (!empty($atts['date_to'])) {
            $args['meta_query'][] = array(
                'key'     => '_travel_date_to',
                'value'   => $atts['date_to'],
                'compare' => '<=',
                'type'    => 'DATE',
            );
        }
        
        // Filter by category
        if (!empty($atts['category'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'listing_category',
                    'field'    => 'slug',
                    'terms'    => $atts['category'],
                ),
            );
        }
        
        $listings = new WP_Query($args);
        
        // Get categories for filter
        $categories = get_terms(array(
            'taxonomy'   => 'listing_category',
            'hide_empty' => true,
        ));
        
        if (!$ajax) {
            ?>
            <div class="travel-listings-wrapper">
                <?php if ($atts['show_filter'] === 'yes'): ?>
                <div class="travel-listings-filter">
                    <form id="travel-filter-form" class="filter-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="filter-date-from"><?php _e('Date From', 'travel-listings'); ?></label>
                                <input type="date" id="filter-date-from" name="date_from" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label for="filter-date-to"><?php _e('Date To', 'travel-listings'); ?></label>
                                <input type="date" id="filter-date-to" name="date_to" class="filter-input">
                            </div>
                            <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                            <div class="filter-group">
                                <label for="filter-category"><?php _e('Category', 'travel-listings'); ?></label>
                                <select id="filter-category" name="category" class="filter-input">
                                    <option value=""><?php _e('All Categories', 'travel-listings'); ?></option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->slug); ?>"><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="filter-group filter-buttons">
                                <button type="submit" class="filter-btn filter-btn-primary"><?php _e('Filter', 'travel-listings'); ?></button>
                                <button type="button" id="reset-filter" class="filter-btn filter-btn-secondary"><?php _e('Reset', 'travel-listings'); ?></button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
                
                <div id="travel-listings-container" class="travel-listings-grid">
            <?php
        }
        
        if ($listings->have_posts()) {
            while ($listings->have_posts()) {
                $listings->the_post();
                $this->render_single_listing(get_the_ID());
            }
            wp_reset_postdata();
        } else {
            echo '<div class="no-listings-found">';
            echo '<p>' . __('No listings found matching your criteria.', 'travel-listings') . '</p>';
            echo '</div>';
        }
        
        if (!$ajax) {
            ?>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render Single Listing Card
     */
    public function render_single_listing($post_id) {
        $date_from = get_post_meta($post_id, '_travel_date_from', true);
        $date_to = get_post_meta($post_id, '_travel_date_to', true);
        $location = get_post_meta($post_id, '_travel_location', true);
        $price = get_post_meta($post_id, '_travel_price', true);
        $price_on_image = get_post_meta($post_id, '_travel_price_on_image', true);
        $contact_email = get_post_meta($post_id, '_travel_contact_email', true);
        $contact_phone = get_post_meta($post_id, '_travel_contact_phone', true);
        $website_url = get_post_meta($post_id, '_travel_website_url', true);

        $categories = get_the_terms($post_id, 'listing_category');

        ?>
        <article class="travel-listing-card" data-id="<?php echo esc_attr($post_id); ?>">
            <?php if (has_post_thumbnail($post_id)): ?>
            <div class="listing-image">
                <a href="<?php echo get_permalink($post_id); ?>">
                    <?php echo get_the_post_thumbnail($post_id, 'medium_large', array('class' => 'listing-thumbnail')); ?>
                </a>
                <?php if ($price && $price_on_image === '1'): ?>
                <span class="listing-price-badge"><?php echo esc_html($price); ?></span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="listing-content">
                <?php if ($categories && !is_wp_error($categories)): ?>
                <div class="listing-categories">
                    <?php foreach ($categories as $cat): ?>
                    <span class="listing-category"><?php echo esc_html($cat->name); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <h3 class="listing-title">
                    <a href="<?php echo get_permalink($post_id); ?>"><?php echo get_the_title($post_id); ?></a>
                </h3>
                
                <div class="listing-meta">
                    <?php if ($price): ?>
                    <div class="listing-price-meta">
                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M15 18.5c-2.51 0-4.68-1.42-5.76-3.5H15v-2H8.58c-.05-.33-.08-.66-.08-1s.03-.67.08-1H15V9H9.24C10.32 6.92 12.5 5.5 15 5.5c1.61 0 3.09.59 4.23 1.57L21 5.3C19.41 3.87 17.3 3 15 3c-3.92 0-7.24 2.51-8.48 6H3v2h3.06c-.04.33-.06.66-.06 1s.02.67.06 1H3v2h3.52c1.24 3.49 4.56 6 8.48 6 2.31 0 4.41-.87 6-2.3l-1.78-1.77c-1.13.98-2.6 1.57-4.22 1.57z"/>
                        </svg>
                        <span><?php echo esc_html($price); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($date_from || $date_to): ?>
                    <div class="listing-dates">
                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM7 11h5v5H7z"/>
                        </svg>
                        <span><?php
                            if ($date_from && $date_to) {
                                echo date_i18n('d.m.Y', strtotime($date_from)) . ' – ' . date_i18n('d.m.Y', strtotime($date_to));
                            } elseif ($date_from) {
                                echo date_i18n('d.m.Y', strtotime($date_from));
                            } elseif ($date_to) {
                                echo __('Until', 'travel-listings') . ' ' . date_i18n('d.m.Y', strtotime($date_to));
                            }
                        ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ($location): ?>
                    <div class="listing-location">
                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <span><?php echo esc_html($location); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <?php if (has_excerpt($post_id)): ?>
                <div class="listing-excerpt">
                    <?php echo wp_trim_words(get_the_excerpt($post_id), 20); ?>
                </div>
                <?php endif; ?>
                
                <div class="listing-actions">
                    <a href="<?php echo get_permalink($post_id); ?>" class="listing-btn listing-btn-primary"><?php _e('View Details', 'travel-listings'); ?></a>
                    <?php if ($contact_phone): ?>
                    <a href="tel:<?php echo esc_attr($contact_phone); ?>" class="listing-btn listing-btn-secondary">
                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                        <?php _e('Call', 'travel-listings'); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
    }
    
    /**
     * AJAX Filter Listings
     */
    public function ajax_filter_listings() {
        check_ajax_referer('travel_listings_filter', 'nonce');
        
        $atts = array(
            'posts_per_page' => isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 12,
            'date_from'      => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to'        => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
            'category'       => isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '',
            'show_filter'    => 'no',
        );
        
        ob_start();
        $this->render_listings($atts, true);
        $html = ob_get_clean();
        
        wp_send_json_success(array('html' => $html));
    }
}

// Initialize the plugin
new Travel_Listings();

// Activation hook
register_activation_hook(__FILE__, 'travel_listings_activate');
function travel_listings_activate() {
    $plugin = new Travel_Listings();
    $plugin->register_post_type();
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'travel_listings_deactivate');
function travel_listings_deactivate() {
    flush_rewrite_rules();
}
