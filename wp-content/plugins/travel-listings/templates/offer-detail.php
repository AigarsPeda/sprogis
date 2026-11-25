<?php
defined('ABSPATH') || exit;

$id = get_the_ID();
$title = $travel_listings_instance->get_listing_title($id, $lang);
$description = $travel_listings_instance->get_listing_description($id, $lang) ?: get_the_content();
[$price, $price_note] = spro_price_parts(spro_price($id));
$location = get_post_meta($id, '_travel_location', true);
$categories = get_the_terms($id, 'listing_category');
$photo = spro_trip_photo($id);
$included = spro_trip_included($id);
$key_dates = spro_trip_key_dates($id);
$today = current_time('Y-m-d');

$from = get_post_meta($id, '_travel_date_from', true);
$to = get_post_meta($id, '_travel_date_to', true);
$nights = spro_nights($from, $to);
$concert_start = get_post_meta($id, '_travel_concert_start', true);
$concert = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $concert_start, wp_timezone());
$has_concert = $concert && $concert->format('Y-m-d\TH:i') === $concert_start;

// Some workbook descriptions use literal newline escapes and end with a labelled official URL.
$description = str_replace(['\\r\\n', '\\n'], "\n", $description);
$website = get_post_meta($id, '_travel_website_url', true);
$website_kind = 'site';
if (preg_match('~(?:^|\R)(Oficiālā biļešu lapa|Official ticket page|Официальная страница|Oficiālā informācija|Official information|Официальная информация):\h*(https?://[^\s<>"]+)\s*\z~iu', $description, $match)) {
    if (!$website || $website === $match[2]) {
        $website = $match[2];
        $website_kind = preg_match('~biļešu|ticket|страница~iu', $match[1]) ? 'tickets' : 'site';
        $description = trim(substr($description, 0, -strlen($match[0])));
    }
}
$website = esc_url_raw($website, ['http', 'https']);
$host = $website ? wp_parse_url($website, PHP_URL_HOST) : '';
$own_site = $host && $host === wp_parse_url(home_url(), PHP_URL_HOST);
$website_label = $own_site ? spro_text('Rezervēt', 'Reserve', 'Забронировать')
    : ($website_kind === 'tickets' ? spro_text('Oficiālās biļetes', 'Official tickets', 'Официальные билеты') : spro_text('Oficiālā lapa', 'Official website', 'Официальный сайт'));

// The trip's own contact details win over the site-wide ones from Travel Listings → Site and footer.
$email = get_post_meta($id, '_travel_contact_email', true) ?: spro_site('contact_email');
$phone = get_post_meta($id, '_travel_contact_phone', true) ?: spro_site('contact_phone');
$ask_label = spro_text('Jautāt SproTravel par šo ceļojumu', 'Ask SproTravel about this trip', 'Спросить SproTravel об этой поездке');
$primary = null;
if ($email) {
    $primary = ['mailto:' . $email . '?subject=' . rawurlencode($title), $ask_label, false];
} elseif ($phone) {
    $primary = ['tel:' . preg_replace('/[^\d+]/', '', $phone), $ask_label, false];
} elseif ($host) {
    $primary = [$website, $website_label, !$own_site];
}
// The Reserve button under Key dates: the trip's reservation link, otherwise the main action.
$reserve_url = esc_url_raw(get_post_meta($id, '_travel_reserve_url', true), ['http', 'https']);
$reserve = null;
if (spro_site('reserve_enabled') && ($reserve_url || $primary)) {
    $reserve_external = $reserve_url ? wp_parse_url($reserve_url, PHP_URL_HOST) !== wp_parse_url(home_url(), PHP_URL_HOST) : $primary[2];
    $reserve = [$reserve_url ?: $primary[0], spro_site('reserve_label'), $reserve_external];
}

$facts = [];
if ($from) {
    $facts[] = [$from === $to || !$to ? spro_text('Datums', 'Date', 'Дата') : spro_text('Ceļojums', 'Trip', 'Поездка'), spro_date_range($from, $to)];
}
if ($nights) {
    $facts[] = [spro_text('Ilgums', 'Length', 'Длительность'), spro_nights_label($nights)];
}
if ($has_concert) {
    $facts[] = [spro_text('Koncerts', 'Concert', 'Концерт'), spro_format_date($concert->format('Y-m-d')) . ', ' . $concert->format('H:i')];
}
if ($location) {
    $facts[] = [spro_text('Kur', 'Where', 'Где'), $location];
}
$more = spro_more_trips($id);

$button = static function ($action, $class) {
    [$href, $label, $external] = $action;
    printf('<a class="%s" href="%s"%s>%s%s</a>', esc_attr($class), esc_url($href, ['http', 'https', 'mailto', 'tel']),
        $external ? ' target="_blank" rel="noopener"' : '', esc_html($label), $external ? spro_icon('external', 17) : '');
};
?>
<article class="sp-trip<?php if (!$photo) echo ' sp-trip-no-photo'; ?>">
    <header class="sp-trip-hero">
        <?php if ($photo): ?>
            <?php echo wp_get_attachment_image($photo, 'full', false, ['class' => 'sp-trip-photo', 'alt' => '', 'fetchpriority' => 'high', 'sizes' => '100vw',
                'style' => 'object-position:' . spro_trip_photo_position($id)]); ?>
        <?php endif; ?>
        <div class="sp-wrap sp-trip-hero-inner">
            <nav class="sp-offer-trail" aria-label="<?php echo esc_attr(spro_text('Piedāvājumu navigācija', 'Offer navigation', 'Навигация по предложениям')); ?>">
                <a href="<?php echo esc_url($home . '#offers'); ?>"><?php echo esc_html($offers); ?></a>
                <?php if ($categories && !is_wp_error($categories)): ?>
                    <span aria-hidden="true">/</span>
                    <ul>
                        <?php foreach ($categories as $category): ?>
                            <li><a href="<?php echo esc_url(add_query_arg('trip_category', $category->slug, $home) . '#offers'); ?>"><?php echo esc_html($category->name); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </nav>
            <h1><?php echo esc_html($title); ?></h1>
        </div>
    </header>

    <div class="sp-wrap">
        <section class="sp-trip-bar" aria-label="<?php echo esc_attr(spro_text('Piedāvājuma informācija', 'Offer details', 'Информация о предложении')); ?>">
            <?php if ($facts): ?>
                <dl class="sp-trip-facts">
                    <?php foreach ($facts as [$label, $value]): ?>
                        <div><dt><?php echo esc_html($label); ?></dt><dd><?php echo esc_html($value); ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
            <?php if ($price !== ''): ?>
                <p class="sp-trip-price"><b><?php echo esc_html($price); ?></b><?php if ($price_note): ?><small><?php echo esc_html($price_note); ?></small><?php endif; ?></p>
            <?php endif; ?>
        </section>

        <div class="sp-trip-body">
            <div class="sp-trip-main">
                <?php if (trim($description) !== ''): ?>
                    <section>
                        <h2><?php echo esc_html(spro_text('Par ceļojumu', 'About this trip', 'О поездке')); ?></h2>
                        <div class="sp-prose"><?php echo wp_kses_post(wpautop($description)); ?></div>
                    </section>
                <?php endif; ?>
                <?php if ($included): ?>
                    <section>
                        <h2><?php echo esc_html(spro_text('Kas iekļauts', 'What’s included', 'Что включено')); ?></h2>
                        <table class="sp-included">
                            <?php foreach ($included as $row): ?>
                                <tr><th scope="row"><?php echo esc_html(spro_pick($row['item'])); ?></th><td><?php echo esc_html(spro_included_price($row)); ?></td></tr>
                            <?php endforeach; ?>
                        </table>
                    </section>
                <?php endif; ?>
            </div>

            <aside class="sp-trip-side">
                <?php if (has_post_thumbnail()): ?>
                    <a class="sp-trip-flyer" href="<?php echo esc_url(get_the_post_thumbnail_url($id, 'full')); ?>" data-sp-lightbox="sp-flyer" aria-haspopup="dialog">
                        <?php the_post_thumbnail('medium', ['alt' => '']); ?>
                        <span><b><?php echo esc_html(spro_text('Ceļojuma afiša', 'Trip flyer', 'Афиша поездки')); ?></b><small><?php echo esc_html(spro_text('Atvērt pilnā izmērā', 'Open full size', 'Открыть в полном размере')); ?></small></span>
                    </a>
                <?php endif; ?>
                <?php if ($key_dates): ?>
                    <section class="sp-trip-box">
                        <h2><?php echo esc_html(spro_text('Svarīgi datumi', 'Key dates', 'Важные даты')); ?></h2>
                        <ol class="sp-key-dates">
                            <?php foreach ($key_dates as $row): ?>
                                <li<?php if (($row['to'] ?: $row['date']) < $today) echo ' class="is-past"'; ?>>
                                    <span><?php echo esc_html(spro_pick($row['label'])); ?></span>
                                    <time datetime="<?php echo esc_attr($row['date']); ?>"><?php echo esc_html(spro_key_date_when($row)); ?><?php if ($row['time']): ?><small><?php echo esc_html($row['time']); ?></small><?php endif; ?></time>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </section>
                <?php endif; ?>
                <?php if ($reserve) $button($reserve, 'sp-trip-cta sp-trip-reserve'); ?>
                <?php if ($host && (!$reserve || $reserve[0] !== $website)) $button([$website, $website_label, !$own_site], 'sp-trip-link'); ?>
                <?php if ($email || $phone): ?>
                    <section class="sp-trip-box sp-trip-contact">
                        <h2><?php echo esc_html(spro_text('Jautājumi?', 'Questions?', 'Есть вопросы?')); ?></h2>
                        <?php if ($email): ?><a href="mailto:<?php echo esc_attr($email); ?>?subject=<?php echo rawurlencode($title); ?>"><?php echo esc_html($email); ?></a><?php endif; ?>
                        <?php if ($phone): ?><a href="tel:<?php echo esc_attr(preg_replace('/[^\d+]/', '', $phone)); ?>"><?php echo esc_html($phone); ?></a><?php endif; ?>
                    </section>
                <?php endif; ?>
            </aside>
        </div>

        <?php if ($more): ?>
            <section class="sp-trip-more">
                <h2><?php echo esc_html(spro_site('more_title')); ?></h2>
                <div class="sp-more-row">
                    <?php foreach ($more as $other):
                        $other_photo = spro_trip_card_image($other) ?: spro_trip_photo($other);
                        $other_price = spro_price_parts(spro_price($other))[0];
                        $other_from = get_post_meta($other, '_travel_date_from', true); ?>
                        <a class="sp-more-card" href="<?php echo esc_url(add_query_arg('lang', $lang, get_permalink($other))); ?>">
                            <span class="sp-more-image">
                                <?php if ($other_photo) {
                                    echo wp_get_attachment_image($other_photo, 'medium', false, ['alt' => '']);
                                } elseif (has_post_thumbnail($other)) {
                                    echo get_the_post_thumbnail($other, 'medium', ['alt' => '']);
                                } ?>
                            </span>
                            <span class="sp-more-text">
                                <?php if ($other_from): ?><small><?php echo esc_html(spro_date_range($other_from, get_post_meta($other, '_travel_date_to', true))); ?></small><?php endif; ?>
                                <b><?php echo esc_html($travel_listings_instance->get_listing_title($other, $lang)); ?></b>
                                <?php if ($other_price !== ''): ?><span><?php echo esc_html($other_price); ?></span><?php endif; ?>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php if (has_post_thumbnail()): ?>
        <dialog class="sp-lightbox" id="sp-flyer" aria-label="<?php echo esc_attr(spro_text('Ceļojuma afiša', 'Trip flyer', 'Афиша поездки')); ?>">
            <button type="button" class="sp-lightbox-close" data-sp-close aria-label="<?php echo esc_attr(spro_text('Aizvērt', 'Close', 'Закрыть')); ?>">&times;</button>
            <img data-src="<?php echo esc_url(get_the_post_thumbnail_url($id, 'full')); ?>" alt="<?php echo esc_attr($title . ' – ' . spro_text('ceļojuma afiša', 'trip flyer', 'афиша поездки')); ?>">
        </dialog>
    <?php endif; ?>
    <?php $sticky = $reserve ?: $primary; ?>
    <?php if ($sticky): ?>
        <div class="sp-trip-sticky">
            <?php if ($price !== ''): ?><p class="sp-trip-price"><b><?php echo esc_html($price); ?></b><?php if ($price_note): ?><small><?php echo esc_html($price_note); ?></small><?php endif; ?></p><?php endif; ?>
            <?php $button($sticky, 'sp-trip-cta'); ?>
        </div>
    <?php endif; ?>
</article>
