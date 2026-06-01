<?php
/**
 * Template for displaying single travel listing
 */

get_header();

// Get Travel_Listings instance for language functions
global $travel_listings_instance;
if (!isset($travel_listings_instance)) {
    $travel_listings_instance = new Travel_Listings();
}
$current_lang = $travel_listings_instance->get_current_language();
$languages = $travel_listings_instance->get_languages();

while (have_posts()) :
    the_post();

    $post_id = get_the_ID();
    $date_from = get_post_meta($post_id, '_travel_date_from', true);
    $date_to = get_post_meta($post_id, '_travel_date_to', true);
    $location = get_post_meta($post_id, '_travel_location', true);
    $price = $travel_listings_instance->get_listing_price($post_id);
    $contact_email = get_post_meta($post_id, '_travel_contact_email', true);
    $contact_phone = get_post_meta($post_id, '_travel_contact_phone', true);
    $website_url = get_post_meta($post_id, '_travel_website_url', true);

    // Get language-specific content
    $listing_title = $travel_listings_instance->get_listing_title($post_id, $current_lang);
    $description = $travel_listings_instance->get_listing_description($post_id, $current_lang);
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

body.single-travel_listing {
    background: #edf3f8 !important;
    color: #1a1a1a !important;
}

body.single-travel_listing .wp-site-blocks,
body.single-travel_listing .site-content,
body.single-travel_listing .content-area,
body.single-travel_listing main {
    background: transparent !important;
}

.single-travel-listing {
    max-width: 1180px;
    margin: 0 auto;
    padding: 36px 28px 40px;
    background: transparent;
    border-radius: 0;
    box-shadow: none;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
}

.listing-header {
    margin-bottom: 26px;
}

.listing-back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #0073aa;
    text-decoration: none;
    font-size: 14px;
    margin-bottom: 20px;
    padding-top: 16px;
}

.listing-back-link:hover {
    text-decoration: underline;
}

.listing-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

/* Language Switcher Dropdown */
.travel-lang-dropdown {
    position: relative;
    display: inline-block;
}

.lang-dropdown-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    background: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}

.lang-dropdown-toggle:hover {
    background: #e2e8f0;
    color: #1e293b;
}

.lang-dropdown-arrow {
    transition: transform 0.2s ease;
}

.travel-lang-dropdown.open .lang-dropdown-arrow {
    transform: rotate(180deg);
}

.lang-dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    min-width: 140px;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    border: 1px solid #e2e8f0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-8px);
    transition: all 0.2s ease;
    z-index: 100;
    overflow: hidden;
}

.travel-lang-dropdown.open .lang-dropdown-menu {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.lang-dropdown-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 14px;
    text-decoration: none;
    color: #475569;
    font-size: 13px;
    transition: all 0.15s ease;
}

.lang-dropdown-item:hover {
    background: #f1f5f9;
    color: #1e293b;
}

.lang-dropdown-item.active {
    background: linear-gradient(135deg, #00a8e8 0%, #0077b6 100%);
    color: #fff;
}

.lang-dropdown-item .lang-code {
    font-weight: 600;
    min-width: 24px;
}

.lang-dropdown-item .lang-name {
    font-weight: 400;
}

.listing-featured-image {
    margin-bottom: 28px;
    border-radius: 24px;
    overflow: hidden;
    position: relative;
    aspect-ratio: 16 / 7;
}

.listing-featured-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.listing-main-content {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(280px, 340px);
    gap: 44px;
    align-items: start;
}

.listing-content-main {
    min-width: 0;
}

.listing-overview {
    margin-bottom: 28px;
    padding-right: 8px;
}

.single-listing-title {
    margin: 0 0 18px 0;
    color: #0f172a;
    font-size: clamp(2rem, 3.4vw, 3.35rem);
    font-weight: 800;
    line-height: 1.02;
    letter-spacing: -0.04em;
}

.listing-header-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 20px;
}

.listing-header-meta > div {
    display: inline-flex;
    align-items: center;
    gap: 9px;
    padding: 10px 14px;
    border-radius: 14px;
    background: rgba(148, 163, 184, 0.08);
    color: #475569;
    font-size: 14px;
    font-weight: 600;
}

.listing-header-meta svg {
    color: #0ea5e9;
}

.listing-overview-divider {
    width: 72px;
    height: 3px;
    border-radius: 999px;
    background: linear-gradient(90deg, #0ea5e9 0%, rgba(14, 165, 233, 0.14) 100%);
}

.listing-description {
    padding: 6px 0 0;
    line-height: 1.85;
    font-size: 17px;
    color: #334155;
}

.listing-description p {
    margin: 0 0 1.3em;
}

.listing-description p:last-child {
    margin-bottom: 0;
}

.listing-description p:first-child {
    color: #0f172a;
    font-size: 1.18em;
    line-height: 1.72;
}

.listing-sidebar {
    position: sticky;
    top: 28px;
    align-self: start;
}

.listing-sidebar-stack {
    display: flex;
    flex-direction: column;
    gap: 14px;
    align-items: stretch;
}

.price-showcase {
    display: flex;
    flex-direction: column;
    gap: 14px;
    align-items: flex-end;
}

.listing-price-display {
    position: relative;
    overflow: hidden;
    min-height: 122px;
    padding: 18px 20px 20px;
    border-radius: 24px;
    box-shadow: none;
}

.listing-price-display .price-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.16em;
    margin-bottom: 10px;
    font-weight: 700;
}

.listing-price-display .price-value {
    font-size: clamp(2rem, 2.6vw, 2.7rem);
    font-weight: 800;
    line-height: 0.98;
    letter-spacing: -0.05em;
}

.listing-price-display--c {
    background: rgba(148, 163, 184, 0.08);
    border: 1px solid rgba(148, 163, 184, 0.14);
    color: #0f172a;
    display: grid;
    grid-template-columns: 1fr;
    align-items: end;
    gap: 10px;
    min-height: 110px;
    padding: 18px 20px;
    width: min(100%, 210px);
}

.listing-price-display--c .price-label {
    margin-bottom: 0;
    color: #0ea5e9;
    letter-spacing: 0.16em;
    width: 100%;
    text-align: right;
}

.listing-price-display--c .price-value {
    justify-self: end;
    text-align: right;
    font-size: clamp(2.6rem, 3.3vw, 3.4rem);
    color: #0f172a;
}

.listing-contact-buttons {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.contact-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 50px;
    padding: 0 20px;
    border-radius: 16px;
    font-size: 14px;
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

@media (prefers-color-scheme: dark) {
    body.single-travel_listing {
        background: #0f172a !important;
        color: #e2e8f0 !important;
    }

    .single-travel-listing {
        background: transparent;
        box-shadow: none;
    }

    .lang-dropdown-toggle {
        background: #1e293b;
        color: #cbd5e1;
        border-color: rgba(148, 163, 184, 0.22);
    }

    .lang-dropdown-toggle:hover {
        background: #334155;
        color: #f8fafc;
    }

    .lang-dropdown-menu {
        background: #0f172a;
        border-color: rgba(148, 163, 184, 0.22);
        box-shadow: 0 20px 45px rgba(2, 6, 23, 0.5);
    }

    .lang-dropdown-item {
        color: #cbd5e1;
    }

    .lang-dropdown-item:hover {
        background: #1e293b;
        color: #f8fafc;
    }

    .single-listing-title {
        color: #f8fafc;
    }

    .listing-header-meta {
        color: #94a3b8;
    }

    .listing-header-meta > div {
        background: rgba(30, 41, 59, 0.76);
        color: #cbd5e1;
        border: 1px solid rgba(148, 163, 184, 0.14);
    }

    .listing-header-meta svg {
        color: #38bdf8;
    }

    .listing-overview-divider {
        background: linear-gradient(90deg, #38bdf8 0%, rgba(56, 189, 248, 0.14) 100%);
    }

    .listing-description {
        color: #cbd5e1;
    }

    .listing-description p:first-child {
        color: #f8fafc;
    }

    .listing-price-display--c .price-label {
        color: #38bdf8;
    }

    .listing-price-display--c .price-value {
        color: #f8fafc;
    }

    .listing-price-display--c {
        background: rgba(30, 41, 59, 0.76) !important;
        border: 1px solid rgba(148, 163, 184, 0.14) !important;
        box-shadow: none;
    }

    .contact-btn-secondary {
        background: #1e293b;
        color: #e2e8f0;
        border-color: rgba(148, 163, 184, 0.22);
    }

    .contact-btn-secondary:hover {
        background: #334155;
        border-color: rgba(148, 163, 184, 0.32);
    }
}

@media (max-width: 768px) {
    .single-travel-listing {
        padding: 20px 16px 24px;
        border-radius: 0;
    }

    .listing-header {
        margin-bottom: 18px;
    }

    .listing-header-top {
        flex-direction: column;
        align-items: flex-start;
    }

    .listing-featured-image {
        margin-bottom: 20px;
        border-radius: 18px;
        aspect-ratio: 16 / 9;
    }

    .listing-overview {
        margin-bottom: 20px;
        padding-right: 0;
    }

    .single-listing-title {
        font-size: 32px;
    }

    .listing-header-meta {
        flex-direction: column;
        gap: 10px;
        margin-bottom: 16px;
    }

    .listing-header-meta > div {
        width: 100%;
    }

    .listing-main-content {
        grid-template-columns: 1fr;
        gap: 22px;
    }

    .listing-description {
        padding-top: 2px;
        font-size: 16px;
    }

    .listing-sidebar {
        position: static;
    }

    .listing-sidebar-stack {
        gap: 14px;
    }

    .listing-price-display {
        border-radius: 20px;
        min-height: 0;
        padding: 18px 18px 20px;
    }

    .listing-price-display--c .price-value {
        font-size: clamp(2.35rem, 9vw, 3.25rem);
    }

    .listing-price-display--c {
        gap: 8px;
        padding: 16px 18px 18px;
        width: 100%;
    }

    .listing-price-display--c .price-value {
        justify-self: end;
        text-align: right;
    }
}
</style>

<article class="single-travel-listing">
    <header class="listing-header">
        <div class="listing-header-top">
            <?php
            // Build back link with current language
            $back_url = home_url('/');
            if ($current_lang !== 'lv') {
                $back_url = add_query_arg('lang', $current_lang, $back_url);
            }
            ?>
            <a href="<?php echo esc_url($back_url); ?>" class="listing-back-link">
                <svg viewBox="0 0 24 24" width="18" height="18">
                    <path fill="currentColor" d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
                </svg>
                <?php echo esc_html($travel_listings_instance->translate('Back to listings')); ?>
            </a>
            <div class="travel-language-switcher travel-lang-dropdown"><?php $current_url = remove_query_arg('lang'); ?><button type="button" class="lang-dropdown-toggle" aria-expanded="false" aria-haspopup="true"><span class="lang-current"><?php echo esc_html(strtoupper($current_lang)); ?></span><svg class="lang-dropdown-arrow" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"></polyline></svg></button><div class="lang-dropdown-menu"><?php foreach ($languages as $code => $name): ?><a href="<?php echo esc_url(add_query_arg('lang', $code, $current_url)); ?>" class="lang-dropdown-item <?php echo $current_lang === $code ? 'active' : ''; ?>"><span class="lang-code"><?php echo esc_html(strtoupper($code)); ?></span><span class="lang-name"><?php echo esc_html($name); ?></span></a><?php endforeach; ?></div></div>
        </div>
        
    </header>
    
    <?php if (has_post_thumbnail()): ?>
    <div class="listing-featured-image">
        <?php the_post_thumbnail('large'); ?>
    </div>
    <?php endif; ?>
    
    <div class="listing-main-content">
        <div class="listing-content-main">
            <section class="listing-overview">
                <h1 class="single-listing-title"><?php echo esc_html($listing_title); ?></h1>

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
                                echo esc_html($travel_listings_instance->translate('From')) . ' ' . date_i18n('d.m.Y', strtotime($date_from));
                            } elseif ($date_to) {
                                echo esc_html($travel_listings_instance->translate('Until')) . ' ' . date_i18n('d.m.Y', strtotime($date_to));
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

                <div class="listing-overview-divider" aria-hidden="true"></div>
            </section>

            <div class="listing-description">
            <?php
            if (!empty($description)) {
                echo wp_kses_post(wpautop($description));
            } else {
                the_content();
            }
            ?>
            </div>
        </div>
        
        <aside class="listing-sidebar">
            <div class="listing-sidebar-stack">
                <?php if ($price): ?>
                <div class="price-showcase">
                    <div class="listing-price-display listing-price-display--c">
                        <div class="price-label"><?php echo esc_html($travel_listings_instance->translate('Price')); ?></div>
                        <div class="price-value"><?php echo esc_html($price); ?></div>
                    </div>
                </div>
                <?php endif; ?>
            
                <div class="listing-contact-buttons">
                    <?php if ($contact_email): ?>
                    <a href="mailto:<?php echo esc_attr($contact_email); ?>" class="contact-btn contact-btn-secondary">
                        <svg viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                        <?php echo esc_html($travel_listings_instance->translate('Email')); ?>
                    </a>
                    <?php endif; ?>

                    <?php if ($contact_phone): ?>
                    <a href="tel:<?php echo esc_attr($contact_phone); ?>" class="contact-btn contact-btn-primary">
                        <svg viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                        </svg>
                        <?php echo esc_html($travel_listings_instance->translate('Call Now')); ?>
                    </a>
                    <?php endif; ?>
                
                    <?php if ($website_url): ?>
                    <a href="<?php echo esc_url($website_url); ?>" target="_blank" rel="noopener" class="contact-btn contact-btn-secondary">
                        <svg viewBox="0 0 24 24" width="18" height="18">
                            <path fill="currentColor" d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
                        </svg>
                        <?php echo esc_html($travel_listings_instance->translate('Visit Website')); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</article>

<?php
endwhile;

get_footer();
