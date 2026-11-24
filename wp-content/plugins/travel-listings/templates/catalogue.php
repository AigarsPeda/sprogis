<?php
defined('ABSPATH') || exit;
$data = spro_catalogue_data($atts);
$base = is_tax('listing_category') ? get_term_link(get_queried_object()) : (is_post_type_archive('travel_listing') ? get_post_type_archive_link('travel_listing') : get_permalink(get_queried_object_id()));
$base = add_query_arg('lang', $this->get_current_language(), $base);
$terms = get_terms(['taxonomy'=>'listing_category','hide_empty'=>true]);
$featured = [];
$other = [];
foreach ($terms as $term) {
    if (get_term_meta($term->term_id, '_travel_featured_category', true) === '1') {
        $featured[] = $term;
    } else {
        $other[] = $term;
    }
}
$terms = array_merge($featured, $other);
$fields = $data['schema']['enabled'];
$values = $data['values'];
$filtered = $data['slug'] || count(array_filter($values, static function ($v) { return $v !== ''; }));
$hero = $atts['hero_image'];
$label_all = spro_text('Visi','All','Все');
$label_any = spro_text('Visi galamērķi','All destinations','Все направления');
$label_reset = spro_text('Notīrīt filtrus','Clear filters','Сбросить фильтры');

?>
<div class="sp-catalogue">
<?php if ($atts['show_hero'] === 'yes'): ?>
<section class="sp-hero">
    <?php if ($hero): ?><img class="sp-hero-photo" fetchpriority="high" src="<?php echo esc_url($hero); ?>" alt=""><?php endif; ?>
    <div class="sp-wrap sp-hero-inner">
        <div class="sp-hero-copy"><h1><?php echo esc_html($atts['hero_title'] ?: spro_text('Kur dosimies tālāk?','Where to next?','Куда отправимся дальше?')); ?></h1>
        <?php if ($atts['hero_subtitle']): ?><p><?php echo esc_html($atts['hero_subtitle']); ?></p><?php endif; ?></div>
    </div>
</section>
<?php endif; ?>
<div class="sp-wrap sp-browser<?php if ($atts['show_hero'] === 'yes' && $atts['show_filter'] === 'yes') echo ' sp-overlap'; ?>" data-sp-browser>
<?php if ($atts['show_filter'] === 'yes'): ?>
<section class="sp-search" id="trip-search" aria-label="<?php echo esc_attr(spro_text('Piedāvājumu meklētājs','Offer search','Поиск предложений')); ?>">
    <?php if (!$data['fixed'] && $terms) include __DIR__ . '/category-tabs.php'; ?>
    <form class="sp-search-form" method="get" action="<?php echo esc_url(strtok($base,'?') . '#offers'); ?>" data-sp-search>
        <?php foreach (wp_parse_args(wp_parse_url($base, PHP_URL_QUERY)) as $key => $val): ?><input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($val); ?>"><?php endforeach; ?>
        <?php if ($data['slug']): ?><input type="hidden" name="trip_category" value="<?php echo esc_attr($data['slug']); ?>"><?php endif; ?>
        <?php if ($data['sort'] === 'price'): ?><input type="hidden" name="trip_sort" value="price"><?php endif; ?>
        <?php if (in_array('location',$fields,true)): ?>
            <label class="sp-field"><?php echo spro_icon('pin'); ?><span><b><?php echo esc_html(spro_text('Galamērķis','Destination','Направление')); ?></b>
            <select name="trip_location"><option value=""><?php echo esc_html($label_any); ?></option><?php foreach ($data['choices']['location'] as $val): ?><option <?php selected($values['location'], $val); ?>><?php echo esc_html($val); ?></option><?php endforeach; ?></select></span></label>
        <?php endif; ?>
        <?php if (array_intersect(['date_from','date_to'],$fields)): ?>
        <?php
        $pick_dates = spro_text('Izvēlēties datumus','Select dates','Выбрать даты');
        $chosen = array_filter([$values['date_from'] ?? '', $values['date_to'] ?? '']);
        ?>
        <details class="sp-field sp-dates">
            <summary><?php echo spro_icon('calendar'); ?><span><b><?php echo esc_html(spro_text('Ceļojuma datumi','Travel dates','Даты поездки')); ?></b><span data-date-summary data-placeholder="<?php echo esc_attr($pick_dates); ?>"><?php echo esc_html($chosen ? implode(' – ', $chosen) : $pick_dates); ?></span></span><?php echo spro_icon('chevron',14); ?></summary>
            <div class="sp-date-pop">
            <?php foreach (['date_from','date_to'] as $key): if (!in_array($key,$fields,true)) continue; ?>
                <label><?php echo esc_html($this->translate($key === 'date_from' ? 'Date From' : 'Date To')); ?><input type="date" name="trip_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($values[$key]); ?>"></label>
            <?php endforeach; ?>
            </div>
        </details>
        <?php endif; ?>
        <?php if (array_intersect(['price_from','price_to'],$fields)): ?>
        <fieldset class="sp-field"><legend class="screen-reader-text"><?php echo esc_html($this->translate('Price')); ?></legend><?php echo spro_icon('wallet'); ?><div><b><?php echo esc_html(spro_text('Budžets (€)','Budget (€)','Бюджет (€)')); ?></b><div class="sp-range">
            <?php foreach (['price_from','price_to'] as $key): if (!in_array($key,$fields,true)) continue; ?>
            <input type="number" min="0" step="any" aria-label="<?php echo esc_attr($this->translate($key === 'price_from' ? 'Price From' : 'Price To')); ?>" placeholder="<?php echo esc_attr($key === 'price_from' ? spro_text('No','Min','От') : spro_text('Līdz','Max','До')); ?>" name="trip_<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($values[$key]); ?>">
            <?php endforeach; ?></div></div></fieldset>
        <?php endif; ?>
        <?php foreach ($data['schema']['custom'] as $key => $label): $field = 'f_' . $key; ?>
            <label class="sp-field"><?php echo spro_icon('compass'); ?><span><b><?php echo esc_html($label); ?></b><select name="trip_<?php echo esc_attr($field); ?>"><option value=""><?php echo esc_html($label_all); ?></option><?php foreach ($data['choices'][$field] as $val): ?><option <?php selected($values[$field], $val); ?>><?php echo esc_html($val); ?></option><?php endforeach; ?></select></span></label>
        <?php endforeach; ?>
        <button class="sp-submit" type="submit"><?php echo spro_icon('search',20); ?><?php echo esc_html(spro_text('Meklēt','Search','Найти')); ?></button>
    </form>
</section>
<?php endif; ?>
<section class="sp-offers" id="offers" tabindex="-1" aria-label="<?php echo esc_attr(spro_text('Piedāvājumi','Offers','Предложения') . ' · ' . $data['count']); ?>">
    <div class="sp-offers-head">
        <?php if ($filtered && $data['ids']): ?>
            <a data-sp-link href="<?php echo esc_url($base . '#offers'); ?>"><?php echo esc_html($label_reset); ?></a>
        <?php endif; ?>
        <details class="sp-sort"><summary><?php echo spro_icon('sort',18); ?><?php echo esc_html($data['sort'] === 'price' ? spro_text('Lētākie','Lowest price','Сначала дешевле') : spro_text('Jaunākie','Newest','Сначала новые')); ?><?php echo spro_icon('chevron',14); ?></summary>
        <div><?php foreach (['new'=>spro_text('Jaunākie','Newest','Сначала новые'),'price'=>spro_text('Lētākie','Lowest price','Сначала дешевле')] as $key=>$label): ?><a data-sp-link href="<?php echo esc_url(add_query_arg('trip_sort',$key,remove_query_arg('trip_page')) . '#offers'); ?>"><?php echo esc_html($label); ?></a><?php endforeach; ?></div></details>
    </div>
    <div class="sp-grid">
    <?php foreach ($data['ids'] as $id):
        $title = $this->get_listing_title($id);
        $cats = get_the_terms($id,'listing_category');
        $cat = $cats && !is_wp_error($cats) ? reset($cats) : null;
        $place = get_post_meta($id,'_travel_location',true);
        $price = spro_price($id);
        $date = get_post_meta($id,'_travel_date_from',true);
        ?>
        <article class="sp-card"><a href="<?php echo esc_url(add_query_arg('lang',$this->get_current_language(),get_permalink($id))); ?>">
            <div class="sp-card-image"><?php if (has_post_thumbnail($id)) echo get_the_post_thumbnail($id,'medium_large',['alt'=>'','sizes'=>'(max-width: 600px) 94vw, (max-width: 900px) 45vw, 380px']); else echo spro_icon(spro_term_icon($cat),44); ?>
            <?php if ($cat): ?><span class="sp-cat"><?php echo spro_icon(spro_term_icon($cat),14); ?><?php echo esc_html($cat->name); ?></span><?php endif; ?></div>
            <div class="sp-card-body"><?php if ($place): ?><span class="sp-place"><?php echo spro_icon('pin',14); ?><?php echo esc_html($place); ?></span><?php endif; ?><h3><?php echo esc_html($title); ?></h3>
                <?php if ($date): ?><span class="sp-card-date"><?php echo spro_icon('calendar',14); ?><?php echo esc_html(mysql2date('d.m.Y',$date)); ?></span><?php endif; ?>
                <div class="sp-card-end"><span><?php echo esc_html($price ?: $this->translate('View Details')); ?></span><i><?php echo spro_icon('arrow',18); ?></i></div>
            </div></a></article>
    <?php endforeach; ?></div>
    <?php if (!$data['ids']): ?><p class="sp-empty"><?php echo esc_html(spro_text('Šiem filtriem nav atbilstošu piedāvājumu.','No offers match these filters.','По этим фильтрам предложений нет.')); ?> <a data-sp-link href="<?php echo esc_url($base . '#offers'); ?>"><?php echo esc_html($label_reset); ?></a></p><?php endif; ?>
    <?php if ($data['pages'] > 1): ?><nav class="sp-pages"><?php for ($i=1; $i <= $data['pages']; $i++): ?><a data-sp-link <?php if ($data['page'] === $i) echo 'aria-current="page"'; ?> href="<?php echo esc_url(add_query_arg('trip_page',$i) . '#offers'); ?>"><?php echo esc_html($i); ?></a><?php endfor; ?></nav><?php endif; ?>
</section></div></div>
