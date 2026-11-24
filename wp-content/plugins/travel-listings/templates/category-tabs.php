<?php
defined('ABSPATH') || exit;

// Keep the compact layout within six controls.
$primary = $terms;
$extra = [];
if (count($terms) > 5) {
    $primary = array_slice($terms, 0, 4);
    $extra = array_slice($terms, 4);
    foreach ($extra as $index => $term) {
        if ($term->slug === $data['slug']) {
            $displaced = array_pop($primary);
            $primary[] = $term;
            unset($extra[$index]);
            array_unshift($extra, $displaced);
            break;
        }
    }
}
$expand_on_desktop = $extra && count($terms) <= 7;
$more_label = spro_text('Vēl', 'More', 'Ещё');
?>
<nav class="sp-tabs<?php if ($expand_on_desktop) echo ' sp-tabs-expand'; ?>" aria-label="<?php echo esc_attr(spro_text('Kategorijas','Categories','Категории')); ?>">
    <a class="sp-tab" data-sp-link href="<?php echo esc_url($base . '#trip-search'); ?>" <?php if (!$data['slug']) echo 'aria-current="true"'; ?>>
        <?php echo spro_icon('grid', 18); ?><span><?php echo esc_html($label_all); ?></span>
    </a>
    <?php foreach (($expand_on_desktop ? $terms : $primary) as $term): ?>
        <a class="sp-tab" <?php if ($expand_on_desktop && !in_array($term, $primary, true)) echo 'data-wide-only'; ?> data-sp-link title="<?php echo esc_attr($term->name); ?>" href="<?php echo esc_url(add_query_arg('trip_category', $term->slug, $base) . '#trip-search'); ?>" <?php if ($term->slug === $data['slug']) echo 'aria-current="true"'; ?>>
            <?php echo spro_icon(spro_term_icon($term), 19); ?><span><?php echo esc_html($term->name); ?></span>
        </a>
    <?php endforeach; ?>
    <?php if ($extra): ?>
        <details class="sp-more">
            <summary class="sp-tab"><?php echo spro_icon('more'); ?><span><?php echo esc_html($more_label); ?></span></summary>
            <div class="sp-more-panel">
                <?php if (count($extra) >= 10): ?>
                    <?php $find_label = spro_text('Meklēt kategoriju', 'Find a category', 'Найти категорию'); ?>
                    <input hidden type="search" data-category-search aria-label="<?php echo esc_attr($find_label); ?>" placeholder="<?php echo esc_attr($find_label); ?>">
                <?php endif; ?>
                <ul>
                    <?php foreach ($extra as $term): ?>
                        <li><a data-sp-link href="<?php echo esc_url(add_query_arg('trip_category', $term->slug, $base) . '#trip-search'); ?>"><?php echo esc_html($term->name); ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <p hidden data-category-empty><?php echo esc_html(spro_text('Nav atbilstošu kategoriju.', 'No matching categories.', 'Категории не найдены.')); ?></p>
            </div>
        </details>
    <?php endif; ?>
</nav>
