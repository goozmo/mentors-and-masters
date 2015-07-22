<div class="wpbdp-region-selector cf">
    <form action="<?php echo $this->url(); ?>" method="post">
        <input type="hidden" name="redirect" value="<?php echo is_paged() ? get_pagenum_link(1) : ''; ?>" />
        <input type="hidden" name="origin" value="<?php echo $this->origin_hash(); ?>" />
        <p class="legend">
            <?php echo $this->get_current_location(); ?>
            <a href="#" class="js-handler"><span></span></a>
        </p>

        <div class="wpbdp-region-selector-inner" data-collapsible="true" data-collapsible-default-mode="<?php echo wpbdp_get_option( 'regions-selector-open' ) ? 'open' : 'closed'; ?>">
            <div class="wpbdp-hide-on-mobile">
            <p>
                <?php _ex('Use the fields below to filter listings for a particular country, city, or state.  Start by selecting the top most region, the other fields will be automatically updated to show available locations.', 'region-selector', 'wpbdp-regions'); ?>
            </p>
            <p>
                <?php _ex('Use the <strong><em>Clear Filter</em></strong> button if you want to start over.', 'region-selector', 'wpbdp-regions'); ?>
            </p>
            </div>

            <?php foreach ($fields as $field): ?>
                <?php echo $field; ?>
            <?php endforeach ?>

            <div class="form-submit">
                <input type="submit" value="<?php _ex('Clear Filter', 'region-selector', 'wpbdp-regions'); ?>" name="clear-location" class="button">
                <input type="submit" value="<?php _ex('Set Filter', 'region-selector', 'wpbdp-regions'); ?>" name="set-location" class="button">
            </div>
        </div>
    </form>
</div>
