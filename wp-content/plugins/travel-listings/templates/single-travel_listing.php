<?php
/**
 * Template for displaying single travel listing
 */

get_header();

while (have_posts()) :
    the_post();
    
    $post_id = get_the_ID();
    $date_from = get_post_meta($post_id, '_travel_date_from', true);
    $date_to = get_post_meta($post_id, '_travel_date_to', true);
    $location = get_post_meta($post_id, '_travel_location', true);
    $price = get_post_meta($post_id, '_travel_price', true);
    $contact_email = get_post_meta($post_id, '_travel_contact_email', true);
    $contact_phone = get_post_meta($post_id, '_travel_contact_phone', true);
    $website_url = get_post_meta($post_id, '_travel_website_url', true);
    $categories = get_the_terms($post_id, 'listing_category');
?>

<style>
/* Hide theme header elements on single listing */
body.single-travel_listing #header,
body.single-travel_listing #headerimg,
body.single-travel_listing .site-header,
body.single-travel_listing .wp-site-blocks > header,
body.single-travel_listing .site-title,
body.single-travel_listing .site-branding,
body.single-travel_listing .entry-header,
body.single-travel_listing .page-header,
body.single-travel_listing .wp-block-post-title,
body.single-travel_listing header.wp-block-template-part {
    display: none !important;
}

/* Remove top spacing */
body.single-travel_listing .wp-site-blocks,
body.single-travel_listing .site-content,
body.single-travel_listing .content-area,
body.single-travel_listing main,
body.single-travel_listing article {
    padding-top: 0 !important;
    margin-top: 0 !important;
}

/* Hide footer */
body.single-travel_listing #footer,
body.single-travel_listing .site-footer,
body.single-travel_listing footer {
    display: none !important;
}

/* Hide hr elements */
body.single-travel_listing > hr,
body.single-travel_listing hr {
    display: none !important;
}

.single-travel-listing {
    max-width: 900px;
    margin: 0 auto;
    padding: 40px 20px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

.listing-header {
    margin-bottom: 32px;
}

.listing-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #0073aa;
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 20px;
}

.listing-back-link:hover {
    text-decoration: underline;
}

.single-listing-title {
    font-size: 32px;
    margin: 0 0 16px 0;
    color: #1a1a1a;
    line-height: 1.3;
}

.listing-header-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    color: #666;
    font-size: 15px;
}

.listing-header-meta > div {
    display: flex;
    align-items: center;
    gap: 8px;
}

.listing-header-meta svg {
    color: #888;
}

.single-listing-categories {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 16px;
}

.single-listing-categories a {
    background: #e8f4fd;
    color: #0073aa;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
}

.single-listing-categories a:hover {
    background: #0073aa;
    color: #fff;
}

.listing-featured-image {
    margin-bottom: 32px;
    border-radius: 12px;
    overflow: hidden;
}

.listing-featured-image img {
    width: 100%;
    height: auto;
    display: block;
}

.listing-main-content {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 40px;
}

.listing-description {
    line-height: 1.8;
    font-size: 16px;
    color: #333;
}

.listing-description p {
    margin-bottom: 1.5em;
}

.listing-sidebar {
    position: sticky;
    top: 20px;
    align-self: start;
}

.listing-info-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 24px;
}

.listing-info-card h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    color: #1a1a1a;
}

.listing-info-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid #e0e0e0;
}

.listing-info-item:last-child {
    border-bottom: none;
}

.listing-info-item svg {
    flex-shrink: 0;
    color: #0073aa;
    margin-top: 2px;
}

.listing-info-item .info-content {
    flex-grow: 1;
}

.listing-info-item .info-label {
    font-size: 12px;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.listing-info-item .info-value {
    font-size: 15px;
    color: #333;
    font-weight: 500;
}

.listing-info-item .info-value a {
    color: #0073aa;
    text-decoration: none;
}

.listing-info-item .info-value a:hover {
    text-decoration: underline;
}

.listing-price-display {
    background: #0073aa;
    color: #fff;
    text-align: center;
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.listing-price-display .price-label {
    font-size: 12px;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.listing-price-display .price-value {
    font-size: 28px;
    font-weight: 700;
}

.listing-contact-buttons {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 20px;
}

.contact-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 20px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.contact-btn-primary {
    background: #0073aa;
    color: #fff;
}

.contact-btn-primary:hover {
    background: #005a87;
    color: #fff;
}

.contact-btn-secondary {
    background: #fff;
    color: #333;
    border: 1px solid #ddd;
}

.contact-btn-secondary:hover {
    background: #f0f0f0;
    border-color: #ccc;
}

@media (max-width: 768px) {
    .single-travel-listing {
        padding: 20px 15px;
    }
    
    .single-listing-title {
        font-size: 24px;
    }
    
    .listing-header-meta {
        flex-direction: column;
        gap: 10px;
    }
    
    .listing-main-content {
        grid-template-columns: 1fr;
    }
    
    .listing-sidebar {
        position: static;
    }
}
</style>

<article class="single-travel-listing">
    <header class="listing-header">
        <a href="javascript:history.back()" class="listing-back-link">
            <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
            </svg>
            <?php _e('Back to listings', 'travel-listings'); ?>
        </a>
        
        <h1 class="single-listing-title"><?php the_title(); ?></h1>
        
        <div class="listing-header-meta">
            <?php if ($date_from || $date_to): ?>
            <div class="meta-dates">
                <svg viewBox="0 0 24 24" width="18" height="18">
                    <path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM7 11h5v5H7z"/>
                </svg>
                <span>
                    <?php 
                    if ($date_from && $date_to) {
                        echo date_i18n('d.m.Y', strtotime($date_from)) . ' - ' . date_i18n('d.m.Y', strtotime($date_to));
                    } elseif ($date_from) {
                        echo __('From', 'travel-listings') . ' ' . date_i18n('d.m.Y', strtotime($date_from));
                    } elseif ($date_to) {
                        echo __('Until', 'travel-listings') . ' ' . date_i18n('d.m.Y', strtotime($date_to));
                    }
                    ?>
                </span>
            </div>
            <?php endif; ?>
            
            <?php if ($location): ?>
            <div class="meta-location">
                <svg viewBox="0 0 24 24" width="18" height="18">
                    <path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                </svg>
                <span><?php echo esc_html($location); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($categories && !is_wp_error($categories)): ?>
        <div class="single-listing-categories">
            <?php foreach ($categories as $cat): ?>
            <a href="<?php echo get_term_link($cat); ?>"><?php echo esc_html($cat->name); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </header>
    
    <?php if (has_post_thumbnail()): ?>
    <div class="listing-featured-image">
        <?php the_post_thumbnail('large'); ?>
    </div>
    <?php endif; ?>
    
    <div class="listing-main-content">
        <div class="listing-description">
            <?php the_content(); ?>
        </div>
        
        <aside class="listing-sidebar">
            <?php if ($price): ?>
            <div class="listing-price-display">
                <div class="price-label"><?php _e('Price', 'travel-listings'); ?></div>
                <div class="price-value"><?php echo esc_html($price); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="listing-info-card">
                <h3><?php _e('Details', 'travel-listings'); ?></h3>
                
                <?php if ($date_from): ?>
                <div class="listing-info-item">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM7 11h5v5H7z"/>
                    </svg>
                    <div class="info-content">
                        <div class="info-label"><?php _e('Start Date', 'travel-listings'); ?></div>
                        <div class="info-value"><?php echo date_i18n(get_option('date_format'), strtotime($date_from)); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($date_to): ?>
                <div class="listing-info-item">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11zM7 11h5v5H7z"/>
                    </svg>
                    <div class="info-content">
                        <div class="info-label"><?php _e('End Date', 'travel-listings'); ?></div>
                        <div class="info-value"><?php echo date_i18n(get_option('date_format'), strtotime($date_to)); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($location): ?>
                <div class="listing-info-item">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    <div class="info-content">
                        <div class="info-label"><?php _e('Location', 'travel-listings'); ?></div>
                        <div class="info-value"><?php echo esc_html($location); ?></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($contact_email): ?>
                <div class="listing-info-item">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <div class="info-content">
                        <div class="info-label"><?php _e('Email', 'travel-listings'); ?></div>
                        <div class="info-value"><a href="mailto:<?php echo esc_attr($contact_email); ?>"><?php echo esc_html($contact_email); ?></a></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($contact_phone): ?>
                <div class="listing-info-item">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                    </svg>
                    <div class="info-content">
                        <div class="info-label"><?php _e('Phone', 'travel-listings'); ?></div>
                        <div class="info-value"><a href="tel:<?php echo esc_attr($contact_phone); ?>"><?php echo esc_html($contact_phone); ?></a></div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($website_url): ?>
                <div class="listing-info-item">
                    <svg viewBox="0 0 24 24" width="20" height="20">
                        <path fill="currentColor" d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm6.93 6h-2.95c-.32-1.25-.78-2.45-1.38-3.56 1.84.63 3.37 1.91 4.33 3.56zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14C4.1 13.36 4 12.69 4 12s.1-1.36.26-2h3.38c-.08.66-.14 1.32-.14 2 0 .68.06 1.34.14 2H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56-1.84-.63-3.37-1.9-4.33-3.56zm2.95-8H5.08c.96-1.66 2.49-2.93 4.33-3.56C8.81 5.55 8.35 6.75 8.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66c-.09-.66-.16-1.32-.16-2 0-.68.07-1.35.16-2h4.68c.09.65.16 1.32.16 2 0 .68-.07 1.34-.16 2zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95c-.96 1.65-2.49 2.93-4.33 3.56zM16.36 14c.08-.66.14-1.32.14-2 0-.68-.06-1.34-.14-2h3.38c.16.64.26 1.31.26 2s-.1 1.36-.26 2h-3.38z"/>
                    </svg>
                    <div class="info-content">
                        <div class="info-label"><?php _e('Website', 'travel-listings'); ?></div>
                        <div class="info-value"><a href="<?php echo esc_url($website_url); ?>" target="_blank" rel="noopener"><?php echo esc_html(parse_url($website_url, PHP_URL_HOST)); ?></a></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="listing-contact-buttons">
                <?php if ($contact_phone): ?>
                <a href="tel:<?php echo esc_attr($contact_phone); ?>" class="contact-btn contact-btn-primary">
                    <svg viewBox="0 0 24 24" width="18" height="18">
                        <path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                    </svg>
                    <?php _e('Call Now', 'travel-listings'); ?>
                </a>
                <?php endif; ?>
                
                <?php if ($contact_email): ?>
                <a href="mailto:<?php echo esc_attr($contact_email); ?>" class="contact-btn contact-btn-secondary">
                    <svg viewBox="0 0 24 24" width="18" height="18">
                        <path fill="currentColor" d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                    <?php _e('Send Email', 'travel-listings'); ?>
                </a>
                <?php endif; ?>
                
                <?php if ($website_url): ?>
                <a href="<?php echo esc_url($website_url); ?>" target="_blank" rel="noopener" class="contact-btn contact-btn-secondary">
                    <svg viewBox="0 0 24 24" width="18" height="18">
                        <path fill="currentColor" d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
                    </svg>
                    <?php _e('Visit Website', 'travel-listings'); ?>
                </a>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</article>

<?php
endwhile;

get_footer();
