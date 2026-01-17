<?php
/**
 * Template for displaying travel listings archive
 */

get_header();
?>

<div class="travel-listings-archive">
    <header class="archive-header">
        <h1 class="archive-title"><?php post_type_archive_title(); ?></h1>
        <?php if (get_the_archive_description()): ?>
        <div class="archive-description"><?php echo get_the_archive_description(); ?></div>
        <?php endif; ?>
    </header>
    
    <?php echo do_shortcode('[travel_listings posts_per_page="12" show_filter="yes"]'); ?>
</div>

<style>
.travel-listings-archive {
    max-width: 1400px;
    margin: 0 auto;
    padding: 40px 20px;
}

.archive-header {
    text-align: center;
    margin-bottom: 40px;
}

.archive-title {
    font-size: 36px;
    margin: 0 0 16px 0;
    color: #1a1a1a;
}

.archive-description {
    font-size: 18px;
    color: #666;
    max-width: 600px;
    margin: 0 auto;
}

@media (max-width: 768px) {
    .travel-listings-archive {
        padding: 20px 15px;
    }
    
    .archive-title {
        font-size: 28px;
    }
}
</style>

<?php
get_footer();
