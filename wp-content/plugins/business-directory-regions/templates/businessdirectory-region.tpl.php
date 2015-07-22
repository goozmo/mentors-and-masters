<?php get_header(); ?>
<div id="content">

<div id="wpbdp-region-page" class="wpbdp-region-page businessdirectory-region businessdirectory wpbdp-page">
    <?php wpbdp_the_bar(array('search' => true)); ?>

    <h2 class="region-name"><?php echo wpbdp_regions_region_page_title(); ?></h2>

    <?php if (!have_posts()): ?>
        <?php _ex("No listings found in this region.", 'templates', "wpbdp-regions"); ?>
    <?php else: ?>
        <div class="listings">

			<?php while(have_posts()): the_post(); ?>
				<?php wpbdp_the_listing_excerpt(); ?>
			<?php endwhile; ?>

            <div class="wpbdp-pagination">
            <?php if (function_exists('wp_pagenavi')) : ?>
                    <?php wp_pagenavi(); ?>
            <?php elseif (function_exists('wp_paginate')): ?>
                    <?php wp_paginate(); ?>
            <?php else: ?>
                <span class="prev"><?php previous_posts_link(_x('&laquo; Previous', 'templates', 'wpbdp-regions')); ?></span>
                <span class="next"><?php next_posts_link(_x('Next &raquo;', 'templates', 'wpbdp-regions')); ?></span>
            <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

</div>
<?php get_footer(); ?>