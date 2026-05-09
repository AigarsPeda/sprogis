<?php
/**
 * Plugin Name: Travel Listings
 * Description: A custom plugin to display travel/event listings with date range filtering
 * Version: 1.0.0
 * Author: A.Pēda
 * Text Domain: travel-listings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Travel_Listings {

    /**
     * Supported languages: Latvian (default), English, Russian
     */
    private $languages = array(
        'lv' => 'Latviešu',
        'en' => 'English',
        'ru' => 'Русский',
    );

    private $default_language = 'lv';

    /**
     * Frontend translations for supported languages
     */
    private $translations = array(
        'Date From' => array(
            'lv' => 'Datums no',
            'en' => 'Date From',
            'ru' => 'Дата с',
        ),
        'Date To' => array(
            'lv' => 'Datums līdz',
            'en' => 'Date To',
            'ru' => 'Дата до',
        ),
        'Price From' => array(
            'lv' => 'Cena no',
            'en' => 'Price From',
            'ru' => 'Цена от',
        ),
        'Price To' => array(
            'lv' => 'Cena līdz',
            'en' => 'Price To',
            'ru' => 'Цена до',
        ),
        'Category' => array(
            'lv' => 'Kategorija',
            'en' => 'Category',
            'ru' => 'Категория',
        ),
        'All Categories' => array(
            'lv' => 'Visas kategorijas',
            'en' => 'All Categories',
            'ru' => 'Все категории',
        ),
        'All' => array(
            'lv' => 'Viss',
            'en' => 'All',
            'ru' => 'Все',
        ),
        'Filter' => array(
            'lv' => 'Filtrēt',
            'en' => 'Filter',
            'ru' => 'Фильтр',
        ),
        'Reset' => array(
            'lv' => 'Atiestatīt',
            'en' => 'Reset',
            'ru' => 'Сбросить',
        ),
        'View Details' => array(
            'lv' => 'Skatīt vairāk',
            'en' => 'View Details',
            'ru' => 'Подробнее',
        ),
        'Call' => array(
            'lv' => 'Zvanīt',
            'en' => 'Call',
            'ru' => 'Позвонить',
        ),
        'No listings found matching your criteria.' => array(
            'lv' => 'Nav atrasti ieraksti, kas atbilst jūsu kritērijiem.',
            'en' => 'No listings found matching your criteria.',
            'ru' => 'Записи, соответствующие вашим критериям, не найдены.',
        ),
        'Loading more listings...' => array(
            'lv' => 'Ielādē vairāk ierakstus...',
            'en' => 'Loading more listings...',
            'ru' => 'Загрузка...',
        ),
        'All listings loaded' => array(
            'lv' => 'Visi ieraksti ielādēti',
            'en' => 'All listings loaded',
            'ru' => 'Все записи загружены',
        ),
        'Until' => array(
            'lv' => 'Līdz',
            'en' => 'Until',
            'ru' => 'До',
        ),
        'From' => array(
            'lv' => 'No',
            'en' => 'From',
            'ru' => 'С',
        ),
        'Back to listings' => array(
            'lv' => 'Atpakaļ uz sarakstu',
            'en' => 'Back to listings',
            'ru' => 'Назад к списку',
        ),
        'Price' => array(
            'lv' => 'Cena',
            'en' => 'Price',
            'ru' => 'Цена',
        ),
        'Details' => array(
            'lv' => 'Detaļas',
            'en' => 'Details',
            'ru' => 'Детали',
        ),
        'Start Date' => array(
            'lv' => 'Sākuma datums',
            'en' => 'Start Date',
            'ru' => 'Дата начала',
        ),
        'End Date' => array(
            'lv' => 'Beigu datums',
            'en' => 'End Date',
            'ru' => 'Дата окончания',
        ),
        'Location' => array(
            'lv' => 'Vieta',
            'en' => 'Location',
            'ru' => 'Место',
        ),
        'Email' => array(
            'lv' => 'E-pasts',
            'en' => 'Email',
            'ru' => 'Эл. почта',
        ),
        'Phone' => array(
            'lv' => 'Tālrunis',
            'en' => 'Phone',
            'ru' => 'Телефон',
        ),
        'Website' => array(
            'lv' => 'Mājaslapa',
            'en' => 'Website',
            'ru' => 'Сайт',
        ),
        'Call Now' => array(
            'lv' => 'Zvanīt tagad',
            'en' => 'Call Now',
            'ru' => 'Позвонить',
        ),
        'Send Email' => array(
            'lv' => 'Sūtīt e-pastu',
            'en' => 'Send Email',
            'ru' => 'Отправить письмо',
        ),
        'Visit Website' => array(
            'lv' => 'Apmeklēt mājaslapu',
            'en' => 'Visit Website',
            'ru' => 'Посетить сайт',
        ),
    );

    public function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('init', array($this, 'register_blocks'));
        add_action('init', array($this, 'handle_language_switch'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_shortcode('travel_listings', array($this, 'display_listings_shortcode'));
        add_action('wp_ajax_filter_travel_listings', array($this, 'ajax_filter_listings'));
        add_action('wp_ajax_nopriv_filter_travel_listings', array($this, 'ajax_filter_listings'));
        add_action('wp_ajax_load_more_travel_listings', array($this, 'ajax_load_more_listings'));
        add_action('wp_ajax_nopriv_load_more_travel_listings', array($this, 'ajax_load_more_listings'));
        
        // Add custom columns to admin
        add_filter('manage_travel_listing_posts_columns', array($this, 'add_admin_columns'));
        add_action('manage_travel_listing_posts_custom_column', array($this, 'render_admin_columns'), 10, 2);
        add_filter('manage_edit-travel_listing_sortable_columns', array($this, 'sortable_columns'));
        
        // Template loading
        add_filter('single_template', array($this, 'load_single_template'));
        add_filter('archive_template', array($this, 'load_archive_template'));

        // Language switcher shortcode
        add_shortcode('travel_language_switcher', array($this, 'language_switcher_shortcode'));
        
        // Admin settings page
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));

        // Category term meta
        add_action('listing_category_add_form_fields', array($this, 'render_category_featured_add_field'));
        add_action('listing_category_edit_form_fields', array($this, 'render_category_featured_edit_field'));
        add_action('created_listing_category', array($this, 'save_category_featured_meta'));
        add_action('edited_listing_category', array($this, 'save_category_featured_meta'));
    }

    /**
     * Use file modification times so local CSS/JS changes show up immediately.
     */
    private function get_asset_version($relative_path) {
        $asset_path = plugin_dir_path(__FILE__) . ltrim($relative_path, '/');

        if (file_exists($asset_path)) {
            return (string) filemtime($asset_path);
        }

        return '1.0.0';
    }

    /**
     * Register Gutenberg blocks for the editor.
     */
    public function register_blocks() {
        if (!function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'travel-listings-block',
            plugin_dir_url(__FILE__) . 'assets/js/travel-listings-block.js',
            array('wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n'),
            $this->get_asset_version('assets/js/travel-listings-block.js'),
            true
        );

        wp_register_style(
            'travel-listings-style',
            plugin_dir_url(__FILE__) . 'assets/css/travel-listings.css',
            array(),
            $this->get_asset_version('assets/css/travel-listings.css')
        );

        register_block_type('travel-listings/listings', array(
            'api_version'     => 2,
            'editor_script'   => 'travel-listings-block',
            'style'           => 'travel-listings-style',
            'editor_style'    => 'travel-listings-style',
            'render_callback' => array($this, 'render_listings_block'),
            'attributes'      => array(
                'postsPerPage' => array(
                    'type'    => 'number',
                    'default' => 12,
                ),
                'category' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'showFilter' => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
                'showHero' => array(
                    'type'    => 'boolean',
                    'default' => true,
                ),
                'heroTitle' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'heroSubtitle' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
                'heroImage' => array(
                    'type'    => 'string',
                    'default' => '',
                ),
            ),
        ));
    }

    /**
     * Render the Travel Listings Gutenberg block.
     */
    public function render_listings_block($attributes) {
        $shortcode_atts = array(
            'posts_per_page' => isset($attributes['postsPerPage']) ? intval($attributes['postsPerPage']) : 12,
            'category'       => isset($attributes['category']) ? sanitize_title($attributes['category']) : '',
            'show_filter'    => !empty($attributes['showFilter']) ? 'yes' : 'no',
            'show_hero'      => !empty($attributes['showHero']) ? 'yes' : 'no',
        );

        if (!empty($attributes['heroTitle'])) {
            $shortcode_atts['hero_title'] = sanitize_text_field($attributes['heroTitle']);
        }

        if (!empty($attributes['heroSubtitle'])) {
            $shortcode_atts['hero_subtitle'] = sanitize_text_field($attributes['heroSubtitle']);
        }

        if (!empty($attributes['heroImage'])) {
            $shortcode_atts['hero_image'] = esc_url_raw($attributes['heroImage']);
        }

        return $this->display_listings_shortcode($shortcode_atts);
    }
    
    /**
     * Handle language switch via URL parameter
     */
    public function handle_language_switch() {
        if (isset($_GET['lang']) && array_key_exists($_GET['lang'], $this->languages)) {
            setcookie('travel_listings_lang', sanitize_text_field($_GET['lang']), time() + (365 * 24 * 60 * 60), '/');
            $_COOKIE['travel_listings_lang'] = sanitize_text_field($_GET['lang']);
        }
    }

    /**
     * Get current language
     */
    public function get_current_language() {
        if (isset($_COOKIE['travel_listings_lang']) && array_key_exists($_COOKIE['travel_listings_lang'], $this->languages)) {
            return $_COOKIE['travel_listings_lang'];
        }
        return $this->default_language;
    }

    /**
     * Get all supported languages
     */
    public function get_languages() {
        return $this->languages;
    }

    /**
     * Translate a string based on current language
     */
    public function translate($string, $lang = null) {
        if ($lang === null) {
            $lang = $this->get_current_language();
        }

        if (isset($this->translations[$string][$lang])) {
            return $this->translations[$string][$lang];
        }

        // Fallback to English, then original string
        if (isset($this->translations[$string]['en'])) {
            return $this->translations[$string]['en'];
        }

        return $string;
    }

    /**
     * Language switcher shortcode (dropdown style)
     */
    public function language_switcher_shortcode($atts) {
        $current_lang = $this->get_current_language();
        $current_url = remove_query_arg('lang');

        ob_start();
        ?><div class="travel-language-switcher travel-lang-dropdown"><button type="button" class="lang-dropdown-toggle" aria-expanded="false" aria-haspopup="true"><span class="lang-current"><?php echo esc_html(strtoupper($current_lang)); ?></span><svg class="lang-dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button><div class="lang-dropdown-menu"><?php foreach ($this->languages as $code => $name): ?><a href="<?php echo esc_url(add_query_arg('lang', $code, $current_url)); ?>" class="lang-dropdown-item <?php echo $current_lang === $code ? 'active' : ''; ?>"><span class="lang-code"><?php echo esc_html(strtoupper($code)); ?></span><span class="lang-name"><?php echo esc_html($name); ?></span></a><?php endforeach; ?></div></div><?php
        return ob_get_clean();
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
        // Register settings for each language
        foreach ($this->languages as $code => $name) {
            register_setting('travel_listings_settings', 'travel_listings_hero_title_' . $code);
            register_setting('travel_listings_settings', 'travel_listings_hero_subtitle_' . $code);
        }
        register_setting('travel_listings_settings', 'travel_listings_hero_image');
    }
    
    /**
     * Render Settings Page
     */
    public function render_settings_page() {
        $flags = array(
            'lv' => '🇱🇻',
            'en' => '🇬🇧',
            'ru' => '🇷🇺',
        );
        ?>
        <div class="wrap">
            <h1><?php _e('Travel Listings - Hero Settings', 'travel-listings'); ?></h1>

            <style>
                .hero-lang-tabs {
                    display: flex;
                    gap: 0;
                    border-bottom: 2px solid #0073aa;
                    margin: 20px 0;
                }
                .hero-lang-tab {
                    padding: 12px 24px;
                    cursor: pointer;
                    background: #f0f0f0;
                    border: 1px solid #ddd;
                    border-bottom: none;
                    border-radius: 4px 4px 0 0;
                    font-weight: 500;
                    color: #555;
                    transition: all 0.2s;
                    margin-right: 4px;
                }
                .hero-lang-tab:hover {
                    background: #e0e0e0;
                }
                .hero-lang-tab.active {
                    background: #0073aa;
                    color: #fff;
                    border-color: #0073aa;
                }
                .hero-lang-content {
                    display: none;
                    padding: 20px;
                    background: #fff;
                    border: 1px solid #ddd;
                    border-top: none;
                    margin-bottom: 20px;
                }
                .hero-lang-content.active {
                    display: block;
                }
            </style>

            <form method="post" action="options.php">
                <?php settings_fields('travel_listings_settings'); ?>

                <h2><?php _e('Hero Content (Multi-language)', 'travel-listings'); ?></h2>
                <p class="description"><?php _e('Enter hero title and subtitle for each language.', 'travel-listings'); ?></p>

                <div class="hero-lang-tabs">
                    <?php $first = true; foreach ($this->languages as $code => $name): ?>
                    <div class="hero-lang-tab <?php echo $first ? 'active' : ''; ?>" data-lang="<?php echo esc_attr($code); ?>">
                        <?php echo $flags[$code]; ?> <?php echo esc_html($name); ?>
                    </div>
                    <?php $first = false; endforeach; ?>
                </div>

                <?php $first = true; foreach ($this->languages as $code => $name): ?>
                <div class="hero-lang-content <?php echo $first ? 'active' : ''; ?>" data-lang="<?php echo esc_attr($code); ?>">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="travel_listings_hero_title_<?php echo esc_attr($code); ?>">
                                    <?php printf(__('Hero Title (%s)', 'travel-listings'), $name); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="travel_listings_hero_title_<?php echo esc_attr($code); ?>"
                                       name="travel_listings_hero_title_<?php echo esc_attr($code); ?>"
                                       value="<?php echo esc_attr(get_option('travel_listings_hero_title_' . $code)); ?>"
                                       class="regular-text" style="width: 100%; max-width: 500px;"
                                       placeholder="<?php printf(__('Enter title in %s...', 'travel-listings'), $name); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="travel_listings_hero_subtitle_<?php echo esc_attr($code); ?>">
                                    <?php printf(__('Hero Subtitle (%s)', 'travel-listings'), $name); ?>
                                </label>
                            </th>
                            <td>
                                <textarea id="travel_listings_hero_subtitle_<?php echo esc_attr($code); ?>"
                                          name="travel_listings_hero_subtitle_<?php echo esc_attr($code); ?>"
                                          rows="3" class="large-text" style="max-width: 500px;"
                                          placeholder="<?php printf(__('Enter subtitle in %s...', 'travel-listings'), $name); ?>"><?php echo esc_textarea(get_option('travel_listings_hero_subtitle_' . $code)); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php $first = false; endforeach; ?>

                <h2><?php _e('Hero Background Image', 'travel-listings'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="travel_listings_hero_image"><?php _e('Background Image', 'travel-listings'); ?></label>
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
            <p><?php _e('The hero settings above will be automatically applied based on the current language.', 'travel-listings'); ?></p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Language tabs for hero settings
            $('.hero-lang-tab').on('click', function() {
                var lang = $(this).data('lang');
                $('.hero-lang-tab').removeClass('active');
                $(this).addClass('active');
                $('.hero-lang-content').removeClass('active');
                $('.hero-lang-content[data-lang="' + lang + '"]').addClass('active');
            });

            // Media uploader for hero image
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
            'supports'            => array('title', 'thumbnail'),
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
     * Render featured toggle on add category form.
     */
    public function render_category_featured_add_field() {
        ?>
        <div class="form-field term-featured-wrap">
            <label for="travel_listing_featured_category"><?php _e('Featured category', 'travel-listings'); ?></label>
            <input type="checkbox" id="travel_listing_featured_category" name="travel_listing_featured_category" value="1">
            <p><?php _e('Show this category as a quick filter below the main filters.', 'travel-listings'); ?></p>
        </div>
        <?php
    }

    /**
     * Render featured toggle on edit category form.
     */
    public function render_category_featured_edit_field($term) {
        $is_featured = get_term_meta($term->term_id, '_travel_featured_category', true);
        ?>
        <tr class="form-field term-featured-wrap">
            <th scope="row">
                <label for="travel_listing_featured_category"><?php _e('Featured category', 'travel-listings'); ?></label>
            </th>
            <td>
                <label for="travel_listing_featured_category">
                    <input type="checkbox" id="travel_listing_featured_category" name="travel_listing_featured_category" value="1" <?php checked($is_featured, '1'); ?>>
                    <?php _e('Show this category as a quick filter below the main filters.', 'travel-listings'); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    /**
     * Save featured category term meta.
     */
    public function save_category_featured_meta($term_id) {
        if (!current_user_can('manage_categories')) {
            return;
        }

        $is_featured = isset($_POST['travel_listing_featured_category']) ? '1' : '0';
        update_term_meta($term_id, '_travel_featured_category', $is_featured);
    }

    /**
     * Get featured categories for the quick filter row.
     */
    private function get_featured_categories() {
        $terms = get_terms(array(
            'taxonomy'   => 'listing_category',
            'hide_empty' => true,
            'meta_key'   => '_travel_featured_category',
            'meta_value' => '1',
            'orderby'    => 'name',
            'order'      => 'ASC',
        ));

        if (is_wp_error($terms)) {
            return array();
        }

        return $terms;
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
            'travel_listing_descriptions',
            __('📝 Descriptions (Multi-language)', 'travel-listings'),
            array($this, 'render_descriptions_meta_box'),
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
     * Render Multi-language Descriptions Meta Box
     */
    public function render_descriptions_meta_box($post) {
        ?>
        <style>
            .travel-lang-tabs {
                display: flex;
                gap: 0;
                border-bottom: 2px solid #0073aa;
                margin-bottom: 20px;
            }
            .travel-lang-tab {
                padding: 12px 24px;
                cursor: pointer;
                background: #f0f0f0;
                border: 1px solid #ddd;
                border-bottom: none;
                border-radius: 4px 4px 0 0;
                font-weight: 500;
                color: #555;
                transition: all 0.2s;
            }
            .travel-lang-tab:hover {
                background: #e8e8e8;
            }
            .travel-lang-tab.active {
                background: #0073aa;
                color: #fff;
                border-color: #0073aa;
            }
            .travel-lang-content {
                display: none;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 0 0 8px 8px;
            }
            .travel-lang-content.active {
                display: block;
            }
            .travel-lang-content > label {
                display: block;
                font-weight: 600;
                margin-bottom: 12px;
                color: #1d2327;
                font-size: 14px;
            }
            .lang-flag {
                margin-right: 6px;
            }
            .travel-lang-content .wp-editor-wrap {
                border: 1px solid #ddd;
                border-radius: 4px;
            }
        </style>

        <div class="travel-lang-tabs">
            <?php foreach ($this->languages as $code => $name): ?>
                <div class="travel-lang-tab <?php echo $code === 'lv' ? 'active' : ''; ?>" data-lang="<?php echo esc_attr($code); ?>">
                    <span class="lang-flag"><?php echo $this->get_lang_flag($code); ?></span>
                    <?php echo esc_html($name); ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php foreach ($this->languages as $code => $name):
            $title = get_post_meta($post->ID, '_travel_title_' . $code, true);
            $description = get_post_meta($post->ID, '_travel_description_' . $code, true);
            $excerpt = get_post_meta($post->ID, '_travel_excerpt_' . $code, true);
            $editor_id = 'travel_description_' . $code;
        ?>
            <div class="travel-lang-content <?php echo $code === 'lv' ? 'active' : ''; ?>" data-lang="<?php echo esc_attr($code); ?>">
                <div class="travel-title-field" style="margin-bottom: 20px;">
                    <label for="travel_title_<?php echo esc_attr($code); ?>" style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; font-size: 14px;">
                        <?php echo $this->get_lang_flag($code); ?>
                        <?php printf(__('Title (%s)', 'travel-listings'), $name); ?>
                    </label>
                    <input
                        type="text"
                        id="travel_title_<?php echo esc_attr($code); ?>"
                        name="travel_title_<?php echo esc_attr($code); ?>"
                        value="<?php echo esc_attr($title); ?>"
                        style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 16px; font-weight: 500;"
                        placeholder="<?php printf(__('Enter title in %s...', 'travel-listings'), $name); ?>"
                    >
                </div>

                <div class="travel-excerpt-field" style="margin-bottom: 20px;">
                    <label for="travel_excerpt_<?php echo esc_attr($code); ?>" style="display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; font-size: 14px;">
                        <?php echo $this->get_lang_flag($code); ?>
                        <?php printf(__('Short Excerpt (%s)', 'travel-listings'), $name); ?>
                    </label>
                    <textarea
                        id="travel_excerpt_<?php echo esc_attr($code); ?>"
                        name="travel_excerpt_<?php echo esc_attr($code); ?>"
                        rows="3"
                        style="width: 100%; padding: 10px; border: 1px solid #8c8f94; border-radius: 4px; font-size: 14px;"
                        placeholder="<?php printf(__('Short description for listing cards (%s)...', 'travel-listings'), $name); ?>"
                    ><?php echo esc_textarea($excerpt); ?></textarea>
                    <p class="description" style="margin-top: 5px; color: #646970; font-size: 13px;">
                        <?php _e('This short text appears on listing cards in the grid view.', 'travel-listings'); ?>
                    </p>
                </div>

                <label>
                    <?php echo $this->get_lang_flag($code); ?>
                    <?php printf(__('Full Description (%s)', 'travel-listings'), $name); ?>
                </label>
                <?php
                wp_editor($description, $editor_id, array(
                    'textarea_name' => $editor_id,
                    'media_buttons' => true,
                    'textarea_rows' => 15,
                    'teeny'         => false,
                    'quicktags'     => true,
                    'tinymce'       => array(
                        'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,fullscreen,wp_adv',
                        'toolbar2' => 'strikethrough,hr,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                    ),
                ));
                ?>
            </div>
        <?php endforeach; ?>

        <script>
        jQuery(document).ready(function($) {
            $('.travel-lang-tab').on('click', function() {
                var lang = $(this).data('lang');
                $('.travel-lang-tab').removeClass('active');
                $(this).addClass('active');
                $('.travel-lang-content').removeClass('active');
                $('.travel-lang-content[data-lang="' + lang + '"]').addClass('active');

                // Refresh TinyMCE editor when tab is shown
                var editorId = 'travel_description_' + lang;
                if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
                    tinymce.get(editorId).fire('focus');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Get flag emoji for language code
     */
    private function get_lang_flag($code) {
        $flags = array(
            'lv' => '🇱🇻',
            'en' => '🇬🇧',
            'ru' => '🇷🇺',
        );
        return isset($flags[$code]) ? $flags[$code] : '';
    }

    /**
     * Get title for current language (with fallback)
     */
    public function get_listing_title($post_id, $lang = null) {
        if ($lang === null) {
            $lang = $this->get_current_language();
        }

        $title = get_post_meta($post_id, '_travel_title_' . $lang, true);

        // Fallback to default language if empty
        if (empty($title) && $lang !== $this->default_language) {
            $title = get_post_meta($post_id, '_travel_title_' . $this->default_language, true);
        }

        // Fallback to post title if still empty
        if (empty($title)) {
            $title = get_the_title($post_id);
        }

        return $title;
    }

    /**
     * Get description for current language (with fallback)
     */
    public function get_listing_description($post_id, $lang = null) {
        if ($lang === null) {
            $lang = $this->get_current_language();
        }

        $description = get_post_meta($post_id, '_travel_description_' . $lang, true);

        // Fallback to default language if empty
        if (empty($description) && $lang !== $this->default_language) {
            $description = get_post_meta($post_id, '_travel_description_' . $this->default_language, true);
        }

        return $description;
    }

    /**
     * Get excerpt for current language (with fallback)
     */
    public function get_listing_excerpt($post_id, $lang = null) {
        if ($lang === null) {
            $lang = $this->get_current_language();
        }

        $excerpt = get_post_meta($post_id, '_travel_excerpt_' . $lang, true);

        // Fallback to default language if empty
        if (empty($excerpt) && $lang !== $this->default_language) {
            $excerpt = get_post_meta($post_id, '_travel_excerpt_' . $this->default_language, true);
        }

        return $excerpt;
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

        // Save multi-language titles, descriptions and excerpts
        foreach ($this->languages as $code => $name) {
            // Save title
            $title_field = 'travel_title_' . $code;
            if (isset($_POST[$title_field])) {
                update_post_meta($post_id, '_travel_title_' . $code, sanitize_text_field($_POST[$title_field]));
            }

            // Save description
            $field_name = 'travel_description_' . $code;
            if (isset($_POST[$field_name])) {
                update_post_meta($post_id, '_travel_description_' . $code, wp_kses_post($_POST[$field_name]));
            }

            // Save excerpt
            $excerpt_field = 'travel_excerpt_' . $code;
            if (isset($_POST[$excerpt_field])) {
                update_post_meta($post_id, '_travel_excerpt_' . $code, sanitize_textarea_field($_POST[$excerpt_field]));
            }
        }
    }
    
    /**
     * Enqueue Frontend Scripts and Styles
     */
    public function enqueue_scripts() {
        wp_register_style(
            'travel-listings-style',
            plugin_dir_url(__FILE__) . 'assets/css/travel-listings.css',
            array(),
            $this->get_asset_version('assets/css/travel-listings.css')
        );

        wp_enqueue_style(
            'travel-listings-style',
            plugin_dir_url(__FILE__) . 'assets/css/travel-listings.css',
            array(),
            $this->get_asset_version('assets/css/travel-listings.css')
        );
        
        wp_enqueue_script(
            'travel-listings-script',
            plugin_dir_url(__FILE__) . 'assets/js/travel-listings.js',
            array('jquery'),
            $this->get_asset_version('assets/js/travel-listings.js'),
            true
        );
        
        wp_localize_script('travel-listings-script', 'travelListings', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('travel_listings_filter'),
            'loadMoreText' => __('Loading...', 'travel-listings'),
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
                $this->get_asset_version('assets/css/admin.css')
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
        // Get current language for hero content
        $current_lang = $this->get_current_language();

        // Get saved settings for current language (with fallback to default language)
        $default_title = get_option('travel_listings_hero_title_' . $current_lang, '');
        if (empty($default_title)) {
            $default_title = get_option('travel_listings_hero_title_' . $this->default_language, '');
        }

        $default_subtitle = get_option('travel_listings_hero_subtitle_' . $current_lang, '');
        if (empty($default_subtitle)) {
            $default_subtitle = get_option('travel_listings_hero_subtitle_' . $this->default_language, '');
        }

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

        $show_filter_lab = $atts['show_hero'] === 'yes' && (!empty($atts['hero_title']) || !empty($atts['hero_image']));

        if ($show_filter_lab) {
            $this->render_filter_variant_gallery($atts);
            $atts['show_filter'] = 'no';
            $atts['show_featured_categories'] = 'no';
        } else {
            $atts['show_featured_categories'] = 'yes';
        }

        $this->render_listings($atts);

        $output = ob_get_clean();

        // Remove empty paragraph tags that WordPress adds
        $output = preg_replace('/<p>\s*<\/p>/', '', $output);
        $output = preg_replace('/<p><br\s*\/?>\s*<\/p>/', '', $output);
        $output = preg_replace('/<br\s*\/?>\s*(?=<)/', '', $output);

        return $output;
    }

    /**
     * Build quick-filter labels used in the concept gallery.
     */
    private function get_preview_filter_labels() {
        $featured_categories = $this->get_featured_categories();
        $labels = array();

        if (!empty($featured_categories)) {
            foreach ($featured_categories as $featured_category) {
                $labels[] = array(
                    'label' => $featured_category->name,
                    'slug'  => $featured_category->slug,
                );
            }
        }

        if (empty($labels)) {
            $categories = get_terms(array(
                'taxonomy'   => 'listing_category',
                'hide_empty' => true,
                'number'     => 3,
            ));

            if (!empty($categories) && !is_wp_error($categories)) {
                foreach ($categories as $category) {
                    $labels[] = array(
                        'label' => $category->name,
                        'slug'  => $category->slug,
                    );
                }
            }
        }

        if (empty($labels)) {
            $labels = array(
                array(
                    'label' => __('Opera', 'travel-listings'),
                    'slug'  => 'opera',
                ),
                array(
                    'label' => __('Sports', 'travel-listings'),
                    'slug'  => 'sports',
                ),
                array(
                    'label' => __('Weekend', 'travel-listings'),
                    'slug'  => 'weekend',
                ),
            );
        }

        return array_slice($labels, 0, 3);
    }

    /**
     * Render concept gallery for comparing filter UX directions.
     */
    public function render_filter_variant_gallery($atts) {
        $categories = get_terms(array(
            'taxonomy'   => 'listing_category',
            'hide_empty' => true,
        ));
        $quick_filters = $this->get_preview_filter_labels();
        $selected_category = isset($atts['category']) ? sanitize_title($atts['category']) : '';
        $hero_style = '';
        $current_lang = $this->get_current_language();
        $current_url = remove_query_arg('lang');

        if (!empty($quick_filters)) {
            array_unshift($quick_filters, array(
                'label' => $this->translate('All'),
                'slug'  => '',
                'reset' => true,
            ));
        }

        if (!empty($atts['hero_image'])) {
            $hero_style = sprintf('--travel-filter-hero-image: url(%s);', esc_url($atts['hero_image']));
        }

        $variants = array(
            array(
                'slug' => 'rail',
                'summary' => __('More filters', 'travel-listings'),
                'open' => false,
            ),
        );
        ?>
        <div class="travel-hero-wrapper">
        <section class="travel-filter-lab" <?php echo $hero_style !== '' ? 'style="' . esc_attr($hero_style) . '"' : ''; ?>>
            <div class="travel-filter-lab__stack">
                <?php foreach ($variants as $index => $variant): ?>
                    <?php $field_suffix = 'variant-' . ($index + 1); ?>
                    <section class="travel-filter-variant travel-filter-variant--<?php echo esc_attr($variant['slug']); ?>">
                        <form class="travel-filter-variant-form travel-filter-variant-form--<?php echo esc_attr($variant['slug']); ?>" data-filter-form data-variant="<?php echo esc_attr($variant['slug']); ?>">
                            <div class="travel-filter-variant-form__hero">
                                <div class="travel-hero-language-switcher travel-language-switcher travel-lang-dropdown"><button type="button" class="lang-dropdown-toggle" aria-expanded="false" aria-haspopup="true"><span class="lang-current"><?php echo esc_html(strtoupper($current_lang)); ?></span><svg class="lang-dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button><div class="lang-dropdown-menu"><?php foreach ($this->languages as $code => $name): ?><a href="<?php echo esc_url(add_query_arg('lang', $code, $current_url)); ?>" class="lang-dropdown-item <?php echo $current_lang === $code ? 'active' : ''; ?>"><span class="lang-code"><?php echo esc_html(strtoupper($code)); ?></span><span class="lang-name"><?php echo esc_html($name); ?></span></a><?php endforeach; ?></div></div>
                                <div class="travel-filter-variant-form__hero-copy">
                                    <strong class="travel-filter-variant-form__hero-title"><?php echo esc_html($atts['hero_title']); ?></strong>
                                    <?php if (!empty($atts['hero_subtitle'])): ?>
                                        <p class="travel-filter-variant-form__hero-text"><?php echo esc_html($atts['hero_subtitle']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="travel-filter-variant-form__body">
                                <div class="travel-filter-shell">
                                    <details class="travel-filter-advanced travel-filter-advanced--<?php echo esc_attr($variant['slug']); ?>" <?php echo $variant['open'] ? 'open' : ''; ?>>
                                        <summary class="travel-filter-advanced__summary">
                                            <span class="travel-filter-advanced__summary-left">
                                                <span class="travel-filter-variant-form__chips" aria-label="<?php esc_attr_e('Quick filters', 'travel-listings'); ?>">
                                                    <?php foreach ($quick_filters as $chip): ?>
                                                        <button
                                                            type="button"
                                                            class="travel-filter-chip <?php echo empty($chip['slug']) ? 'travel-filter-chip--all ' : ''; ?><?php echo empty($chip['slug']) ? ($selected_category === '' ? 'is-active' : '') : ($selected_category === $chip['slug'] ? 'is-active' : ''); ?>"
                                                            data-category-slug="<?php echo esc_attr($chip['slug']); ?>"
                                                            <?php echo !empty($chip['reset']) ? 'data-reset-filters="true"' : ''; ?>
                                                            aria-pressed="<?php echo empty($chip['slug']) ? ($selected_category === '' ? 'true' : 'false') : ($selected_category === $chip['slug'] ? 'true' : 'false'); ?>"
                                                        >
                                                            <?php echo esc_html($chip['label']); ?>
                                                        </button>
                                                    <?php endforeach; ?>
                                                    <span class="travel-filter-variant-form__chips-indicator" aria-hidden="true"></span>
                                                </span>
                                            </span>
                                            <span class="travel-filter-advanced__summary-right">
                                                <span class="travel-filter-advanced__summary-label"><?php echo esc_html($variant['summary']); ?></span>
                                                <button
                                                    type="button"
                                                    class="travel-filter-advanced__toggle"
                                                    aria-expanded="<?php echo $variant['open'] ? 'true' : 'false'; ?>"
                                                    aria-label="<?php esc_attr_e('Toggle filters', 'travel-listings'); ?>"
                                                >
                                                    <svg class="travel-filter-advanced__toggle-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                        <path d="M10 20a1 1 0 0 0 .553.895l2 1A1 1 0 0 0 14 21v-7a2 2 0 0 1 .517-1.341L21.74 4.67A1 1 0 0 0 21 3H3a1 1 0 0 0-.742 1.67l7.225 7.989A2 2 0 0 1 10 14z"/>
                                                    </svg>
                                                </button>
                                            </span>
                                        </summary>

                                        <div class="travel-filter-advanced__content">
                                            <div class="travel-filter-advanced__grid">
                                                <div class="filter-group">
                                                    <label for="filter-date-from-<?php echo esc_attr($field_suffix); ?>"><?php echo esc_html($this->translate('Date From')); ?></label>
                                                    <input type="date" id="filter-date-from-<?php echo esc_attr($field_suffix); ?>" name="date_from" class="filter-input" value="">
                                                </div>
                                                <div class="filter-group">
                                                    <label for="filter-date-to-<?php echo esc_attr($field_suffix); ?>"><?php echo esc_html($this->translate('Date To')); ?></label>
                                                    <input type="date" id="filter-date-to-<?php echo esc_attr($field_suffix); ?>" name="date_to" class="filter-input" value="">
                                                </div>
                                                <div class="filter-group">
                                                    <label for="filter-price-from-<?php echo esc_attr($field_suffix); ?>"><?php echo esc_html($this->translate('Price From')); ?></label>
                                                    <input type="number" id="filter-price-from-<?php echo esc_attr($field_suffix); ?>" name="price_from" class="filter-input" min="0" step="0.01" placeholder="€">
                                                </div>
                                                <div class="filter-group">
                                                    <label for="filter-price-to-<?php echo esc_attr($field_suffix); ?>"><?php echo esc_html($this->translate('Price To')); ?></label>
                                                    <input type="number" id="filter-price-to-<?php echo esc_attr($field_suffix); ?>" name="price_to" class="filter-input" min="0" step="0.01" placeholder="€">
                                                </div>
                                                <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                                                <div class="filter-group travel-filter-advanced__category">
                                                    <label for="filter-category-<?php echo esc_attr($field_suffix); ?>"><?php echo esc_html($this->translate('Category')); ?></label>
                                                    <select id="filter-category-<?php echo esc_attr($field_suffix); ?>" name="category" class="filter-input">
                                                        <option value=""><?php echo esc_html($this->translate('All Categories')); ?></option>
                                                        <?php foreach ($categories as $cat): ?>
                                                        <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($selected_category, $cat->slug); ?>><?php echo esc_html($cat->name); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <?php else: ?>
                                                <input type="hidden" name="category" value="<?php echo esc_attr($selected_category); ?>">
                                                <?php endif; ?>
                                                <div class="travel-filter-advanced__actions">
                                                    <button type="submit" class="filter-btn filter-btn-primary"><?php echo esc_html($this->translate('Filter')); ?></button>
                                                    <button type="button" class="filter-btn filter-btn-secondary travel-filter-reset"><?php echo esc_html($this->translate('Reset')); ?></button>
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </form>
                    </section>
                <?php endforeach; ?>
            </div>
        </section>
        </div>
        <?php
    }
    
    /**
     * Render Hero Section
     */
    public function render_hero_section($atts) {
        $hero_style = '';
        if (!empty($atts['hero_image'])) {
            $hero_style = 'background-image: url(' . esc_url($atts['hero_image']) . ');';
        }
        $current_lang = $this->get_current_language();
        $current_url = remove_query_arg('lang');
        ?>
        <div class="travel-hero-wrapper">
            <section class="travel-hero-section" style="<?php echo esc_attr($hero_style); ?>">
                <div class="travel-hero-overlay"></div>
                <div class="travel-hero-language-switcher travel-language-switcher travel-lang-dropdown"><button type="button" class="lang-dropdown-toggle" aria-expanded="false" aria-haspopup="true"><span class="lang-current"><?php echo esc_html(strtoupper($current_lang)); ?></span><svg class="lang-dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button><div class="lang-dropdown-menu"><?php foreach ($this->languages as $code => $name): ?><a href="<?php echo esc_url(add_query_arg('lang', $code, $current_url)); ?>" class="lang-dropdown-item <?php echo $current_lang === $code ? 'active' : ''; ?>"><span class="lang-code"><?php echo esc_html(strtoupper($code)); ?></span><span class="lang-name"><?php echo esc_html($name); ?></span></a><?php endforeach; ?></div></div>
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
        $paged = isset($atts['paged']) ? intval($atts['paged']) : 1;
        $selected_category = isset($atts['category']) ? sanitize_title($atts['category']) : '';

            $args = array(
                'post_type'      => 'travel_listing',
                'posts_per_page' => intval($atts['posts_per_page']),
                'paged'          => $paged,
                'post_status'    => 'publish',
                'orderby'        => 'date', // Order by created date
                'order'          => 'DESC', // Newest first
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

        // Store price filter values for post-query filtering
        $price_from = isset($atts['price_from']) && $atts['price_from'] !== '' ? floatval($atts['price_from']) : null;
        $price_to = isset($atts['price_to']) && $atts['price_to'] !== '' ? floatval($atts['price_to']) : null;

        $listings = new WP_Query($args);

        // If price filtering is needed, filter the results
        if ($price_from !== null || $price_to !== null) {
            $filtered_posts = array();
            while ($listings->have_posts()) {
                $listings->the_post();
                $price_raw = get_post_meta(get_the_ID(), '_travel_price', true);
                // Extract numeric value from price string (e.g., "€99" -> 99, "Free" -> 0)
                $price_numeric = $this->extract_price_value($price_raw);

                $include = true;
                if ($price_from !== null && $price_numeric < $price_from) {
                    $include = false;
                }
                if ($price_to !== null && $price_numeric > $price_to) {
                    $include = false;
                }

                if ($include) {
                    $filtered_posts[] = get_the_ID();
                }
            }
            wp_reset_postdata();

            // Re-query with filtered IDs
            if (!empty($filtered_posts)) {
                $args['post__in'] = $filtered_posts;
                $args['orderby'] = 'post__in';
                unset($args['meta_key']);
                $listings = new WP_Query($args);
            } else {
                // No posts match the filter
                $listings = new WP_Query(array('post__in' => array(0)));
            }
        }
        
        // Get categories for filter
        $categories = get_terms(array(
            'taxonomy'   => 'listing_category',
            'hide_empty' => true,
        ));
        $featured_categories = $this->get_featured_categories();
        
        // Calculate total pages
        $total_posts = $listings->found_posts;
        $max_pages = $listings->max_num_pages;

        if (!$ajax) {
            ?>
            <div class="travel-listings-wrapper">
                <?php if ($atts['show_filter'] === 'yes'): ?>
                <div class="travel-listings-filter">
                    <form id="travel-filter-form" class="filter-form">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="filter-date-from"><?php echo esc_html($this->translate('Date From')); ?></label>
                                <input type="date" id="filter-date-from" name="date_from" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label for="filter-date-to"><?php echo esc_html($this->translate('Date To')); ?></label>
                                <input type="date" id="filter-date-to" name="date_to" class="filter-input">
                            </div>
                            <div class="filter-group">
                                <label for="filter-price-from"><?php echo esc_html($this->translate('Price From')); ?></label>
                                <input type="number" id="filter-price-from" name="price_from" class="filter-input" min="0" step="0.01" placeholder="€">
                            </div>
                            <div class="filter-group">
                                <label for="filter-price-to"><?php echo esc_html($this->translate('Price To')); ?></label>
                                <input type="number" id="filter-price-to" name="price_to" class="filter-input" min="0" step="0.01" placeholder="€">
                            </div>
                            <?php if (!empty($categories) && !is_wp_error($categories)): ?>
                            <div class="filter-group">
                                <label for="filter-category"><?php echo esc_html($this->translate('Category')); ?></label>
                                <select id="filter-category" name="category" class="filter-input">
                                    <option value=""><?php echo esc_html($this->translate('All Categories')); ?></option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo esc_attr($cat->slug); ?>" <?php selected($selected_category, $cat->slug); ?>><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php endif; ?>
                            <div class="filter-group filter-buttons">
                                <button type="submit" class="filter-btn filter-btn-primary"><?php echo esc_html($this->translate('Filter')); ?></button>
                                <button type="button" id="reset-filter" class="filter-btn filter-btn-secondary"><?php echo esc_html($this->translate('Reset')); ?></button>
                            </div>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (($atts['show_featured_categories'] ?? 'yes') === 'yes' && !empty($featured_categories)): ?>
                <div class="featured-categories-row" aria-label="<?php esc_attr_e('Featured categories', 'travel-listings'); ?>">
                    <?php foreach ($featured_categories as $featured_category): ?>
                    <button
                        type="button"
                        class="featured-category-chip <?php echo $selected_category === $featured_category->slug ? 'is-active' : ''; ?>"
                        data-category-slug="<?php echo esc_attr($featured_category->slug); ?>"
                        aria-pressed="<?php echo $selected_category === $featured_category->slug ? 'true' : 'false'; ?>"
                    >
                        <?php echo esc_html($featured_category->name); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div id="travel-listings-container"
                     class="travel-listings-grid"
                     data-page="1"
                     data-max-pages="<?php echo esc_attr($max_pages); ?>"
                     data-posts-per-page="<?php echo esc_attr($atts['posts_per_page']); ?>">
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
            echo '<p>' . esc_html($this->translate('No listings found matching your criteria.')) . '</p>';
            echo '</div>';
        }

        if (!$ajax) {
            ?>
                <?php if ($max_pages > 1): ?>
                <div class="travel-listings-infinite-scroll">
                    <div id="travel-listings-sentinel" class="infinite-scroll-sentinel" data-max-pages="<?php echo esc_attr($max_pages); ?>"></div>
                    <div id="travel-listings-loader" class="infinite-scroll-loader" style="display: none;">
                        <div class="loader-spinner"></div>
                        <span><?php echo esc_html($this->translate('Loading more listings...')); ?></span>
                    </div>
                    <div id="travel-listings-end" class="infinite-scroll-end" style="display: none;">
                        <span><?php echo esc_html($this->translate('All listings loaded')); ?></span>
                    </div>
                </div>
                <?php endif; ?>
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
        $listing_title = $this->get_listing_title($post_id);
        $listing_permalink = get_permalink($post_id);

        ?>
        <article class="travel-listing-card" data-id="<?php echo esc_attr($post_id); ?>">
            <a
                href="<?php echo esc_url($listing_permalink); ?>"
                class="listing-card-link"
                aria-label="<?php echo esc_attr(sprintf(__('View details for %s', 'travel-listings'), $listing_title)); ?>"
            ></a>
            <?php if (has_post_thumbnail($post_id)): ?>
            <div class="listing-image">
                <a href="<?php echo esc_url($listing_permalink); ?>">
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
                    <a href="<?php echo esc_url($listing_permalink); ?>"><?php echo esc_html($listing_title); ?></a>
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
                                echo esc_html($this->translate('Until')) . ' ' . date_i18n('d.m.Y', strtotime($date_to));
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
                
                <?php
                $listing_excerpt = $this->get_listing_excerpt($post_id);
                if (!empty($listing_excerpt)):
                ?>
                <div class="listing-excerpt">
                    <?php echo wp_trim_words(esc_html($listing_excerpt), 20); ?>
                </div>
                <?php endif; ?>
                
                <div class="listing-actions">
                    <a href="<?php echo esc_url($listing_permalink); ?>" class="listing-btn listing-btn-primary"><?php echo esc_html($this->translate('View Details')); ?></a>
                    <?php if ($contact_phone): ?>
                    <a href="tel:<?php echo esc_attr($contact_phone); ?>" class="listing-btn listing-btn-secondary">
                        <svg class="icon" viewBox="0 0 24 24" width="16" height="16">
                            <path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                        <?php echo esc_html($this->translate('Call')); ?>
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
            'paged'          => 1,
            'date_from'      => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to'        => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
            'price_from'     => isset($_POST['price_from']) && $_POST['price_from'] !== '' ? sanitize_text_field($_POST['price_from']) : '',
            'price_to'       => isset($_POST['price_to']) && $_POST['price_to'] !== '' ? sanitize_text_field($_POST['price_to']) : '',
            'category'       => isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '',
            'show_filter'    => 'no',
        );

        // Get max pages for the filtered query
        $max_pages = $this->get_max_pages($atts);

        ob_start();
        $this->render_listings($atts, true);
        $html = ob_get_clean();

        wp_send_json_success(array(
            'html' => $html,
            'max_pages' => $max_pages,
        ));
    }

    /**
     * AJAX Load More Listings (Infinite Scroll)
     */
    public function ajax_load_more_listings() {
        check_ajax_referer('travel_listings_filter', 'nonce');

        $paged = isset($_POST['paged']) ? intval($_POST['paged']) : 1;

        $atts = array(
            'posts_per_page' => isset($_POST['posts_per_page']) ? intval($_POST['posts_per_page']) : 12,
            'paged'          => $paged,
            'date_from'      => isset($_POST['date_from']) ? sanitize_text_field($_POST['date_from']) : '',
            'date_to'        => isset($_POST['date_to']) ? sanitize_text_field($_POST['date_to']) : '',
            'price_from'     => isset($_POST['price_from']) && $_POST['price_from'] !== '' ? sanitize_text_field($_POST['price_from']) : '',
            'price_to'       => isset($_POST['price_to']) && $_POST['price_to'] !== '' ? sanitize_text_field($_POST['price_to']) : '',
            'category'       => isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '',
            'show_filter'    => 'no',
        );

        ob_start();
        $this->render_listings($atts, true);
        $html = ob_get_clean();

        // Get max pages to know if there are more
        $max_pages = $this->get_max_pages($atts);

        wp_send_json_success(array(
            'html'      => $html,
            'paged'     => $paged,
            'max_pages' => $max_pages,
            'has_more'  => $paged < $max_pages,
        ));
    }

    /**
     * Get max pages for a query
     */
    private function get_max_pages($atts) {
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

        if (!empty($atts['date_from'])) {
            $args['meta_query'][] = array(
                'key'     => '_travel_date_from',
                'value'   => $atts['date_from'],
                'compare' => '>=',
                'type'    => 'DATE',
            );
        }

        if (!empty($atts['date_to'])) {
            $args['meta_query'][] = array(
                'key'     => '_travel_date_to',
                'value'   => $atts['date_to'],
                'compare' => '<=',
                'type'    => 'DATE',
            );
        }

        if (!empty($atts['category'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'listing_category',
                    'field'    => 'slug',
                    'terms'    => $atts['category'],
                ),
            );
        }

        $query = new WP_Query($args);
        return $query->max_num_pages;
    }

    /**
     * Extract numeric value from price string
     * Examples: "€99" -> 99, "Free" -> 0, "150.50" -> 150.50, "$200" -> 200
     */
    private function extract_price_value($price_string) {
        if (empty($price_string)) {
            return 0;
        }

        // Check for "free" (case-insensitive)
        if (strtolower(trim($price_string)) === 'free' || strtolower(trim($price_string)) === 'bezmaksas') {
            return 0;
        }

        // Remove currency symbols and non-numeric characters except . and ,
        $clean = preg_replace('/[^0-9.,]/', '', $price_string);

        // Handle European format (comma as decimal separator)
        // If there's a comma after the last dot, or no dot at all, treat comma as decimal
        if (strpos($clean, ',') !== false) {
            $last_comma = strrpos($clean, ',');
            $last_dot = strrpos($clean, '.');
            if ($last_dot === false || $last_comma > $last_dot) {
                // Comma is the decimal separator
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                // Dot is the decimal separator, comma is thousand separator
                $clean = str_replace(',', '', $clean);
            }
        }

        return floatval($clean);
    }
}

// Initialize the plugin
global $travel_listings_instance;
$travel_listings_instance = new Travel_Listings();

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
