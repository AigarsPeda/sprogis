<?php
defined('ABSPATH') || exit;
$id = get_the_ID();
$title = $travel_listings_instance->get_listing_title($id);
$description = $travel_listings_instance->get_listing_description($id, $lang) ?: get_the_content();
$price = spro_price($id);
$location = get_post_meta($id, '_travel_location', true);
$dates = array_filter([get_post_meta($id,'_travel_date_from',true),get_post_meta($id,'_travel_date_to',true)]);
$date_text = implode(' – ', array_map(static function ($date) { return mysql2date('d.m.Y',$date); }, $dates));
if (count($dates) === 1) {
    $date_text = $travel_listings_instance->translate(isset($dates[0]) ? 'From' : 'Until') . ' ' . $date_text;
}
$categories = get_the_terms($id,'listing_category');
?>
<article class="sp-wrap sp-detail">
<nav class="sp-offer-trail" aria-label="<?php echo esc_attr(spro_text('Piedāvājumu navigācija', 'Offer navigation', 'Навигация по предложениям')); ?>">
    <a href="<?php echo esc_url($home . '#offers'); ?>"><?php echo esc_html($offers); ?></a>
    <?php if ($categories && !is_wp_error($categories)): ?>
        <span aria-hidden="true">/</span>
        <ul>
            <?php foreach ($categories as $cat): ?>
                <li><a href="<?php echo esc_url(add_query_arg('trip_category', $cat->slug, $home) . '#offers'); ?>"><?php echo esc_html($cat->name); ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</nav>
<h1><?php echo esc_html($title); ?></h1>
<?php if ($location || $date_text): ?><div class="sp-facts"><?php if ($location): ?><span><?php echo spro_icon('pin',18); ?><?php echo esc_html($location); ?></span><?php endif; ?><?php if ($date_text): ?><span><?php echo spro_icon('calendar',18); ?><?php echo esc_html($date_text); ?></span><?php endif; ?></div><?php endif; ?>
<?php if (has_post_thumbnail()): ?><div class="sp-detail-image"><?php the_post_thumbnail('large',['alt'=>'','fetchpriority'=>'high']); ?></div><?php endif; ?>
<div class="sp-detail-columns"><div class="sp-prose"><?php echo wp_kses_post(wpautop($description)); ?></div>
<aside class="sp-details-side">
<?php if ($price): ?><small><?php echo esc_html($travel_listings_instance->translate('Price')); ?></small><strong><?php echo esc_html($price); ?></strong><?php endif; ?>
<?php foreach (['email'=>['mailto:', 'Send Email'], 'phone'=>['tel:', 'Call Now'], 'website'=>['', 'Visit Website']] as $method=>$link):
    $value = get_post_meta($id, $method === 'website' ? '_travel_website_url' : '_travel_contact_' . $method, true);
    if (!$value) continue; ?>
<a class="sp-action" href="<?php echo esc_url($link[0] . $value); ?>"><?php echo esc_html($travel_listings_instance->translate($link[1])); ?><?php echo spro_icon('arrow',17); ?></a>
<?php endforeach; ?></aside></div></article>
