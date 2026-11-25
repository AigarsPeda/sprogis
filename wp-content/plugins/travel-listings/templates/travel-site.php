<?php
defined('ABSPATH') || exit;
global $travel_listings_instance;
$lang = $travel_listings_instance->get_current_language();
$home = add_query_arg('lang', $lang, home_url('/'));
$section_base = is_front_page() ? '' : $home;
$logo = plugins_url('assets/images/sprotravel-logo.jpg', dirname(__DIR__) . '/travel-listings.php');
$offers = spro_text('Piedāvājumi', 'Offers', 'Предложения');
// The same listing block supplies both direct page visits and in-place filtering.
if (is_page() || is_archive()) {
    header('Vary: X-Spro-Catalogue', false);
    if (($_SERVER['HTTP_X_SPRO_CATALOGUE'] ?? '') === '1') {
        nocache_headers();
        if (is_archive()) {
            echo do_shortcode('[travel_listings]');
        } else {
            while (have_posts()) {
                the_post();
                the_content();
            }
        }
        return;
    }
}
?><!doctype html>
<html class="sp-document" lang="<?php echo esc_attr($lang); ?>">
<head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><?php wp_head(); ?></head>
<body <?php body_class('spro-site'); ?>><?php wp_body_open(); ?>
<a class="sp-skip" href="#sp-main"><?php echo esc_html(spro_text('Uz saturu','Skip to content','К содержанию')); ?></a>
<header class="sp-header" id="sp-top"><div class="sp-wrap sp-header-inner">
    <a class="sp-brand" href="<?php echo esc_url($section_base . '#sp-top'); ?>" aria-label="<?php echo esc_attr('SproTravel · ' . spro_text('Sākums','Home','Главная')); ?>"><img src="<?php echo esc_url($logo); ?>" alt="SproTravel" width="1600" height="585"></a>
    <details class="sp-lang"><summary><?php echo spro_icon('globe',18); ?> <?php echo esc_html(strtoupper($lang)); ?> <?php echo spro_icon('chevron',14); ?></summary>
        <div><?php foreach ($travel_listings_instance->get_languages() as $code => $name): ?>
            <a lang="<?php echo esc_attr($code); ?>" href="<?php echo esc_url(add_query_arg('lang', $code)); ?>" <?php if ($lang === $code) echo 'aria-current="true"'; ?>><?php echo esc_html($name); ?></a>
        <?php endforeach; ?></div>
    </details>
</div></header>
<main id="sp-main">
<?php if (is_singular('travel_listing')): while (have_posts()): the_post(); include __DIR__ . '/offer-detail.php'; endwhile;
elseif (is_archive()): echo do_shortcode('[travel_listings]');
elseif (is_singular('post')): while (have_posts()): the_post(); ?>
    <article class="sp-wrap sp-article"><a class="sp-back" href="<?php echo esc_url($home); ?>"><?php echo spro_icon('back'); ?> SproTravel</a><h1><?php the_title(); ?></h1><?php the_content(); ?></article>
<?php endwhile;
else: while (have_posts()): the_post(); the_content(); endwhile; endif; ?>
</main>
<?php spro_site_footer($home, $logo); ?>
<?php wp_footer(); ?></body></html>
