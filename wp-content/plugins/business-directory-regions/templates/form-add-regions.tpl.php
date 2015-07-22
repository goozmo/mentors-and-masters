<div id="wpbdp-regions-tabs">
    <ul class="clearfix">
        <li><a href="#wpbdp-add-region"><?php _ex('Add Region', 'add region form', 'wpbdp-regions'); ?></a></li>
        <li><a href="#wpbdp-add-multiple-regions"><?php _ex('Add Multiple Regions', 'add region form', 'wpbdp-regions'); ?></a></li>
    </ul>
    <p><?php _ex('To add a new Region, you must have a Name and a Parent to which the region(s) belongs. If a Region has no parent, it is considered the "top" level region.<br/><br/>(e.g. North America&#8594;United States&#8594;New York&#8594;Albany)', 'add region form', 'wpbdp-regions'); ?></p>
    <div id="wpbdp-add-region">
    </div>
    <div id="wpbdp-add-multiple-regions" class="form-wrap">
        <form class="validate" action="" method="post" id="addtag">
            <input type="hidden" value="<?php echo wpbdp_regions_taxonomy(); ?>" name="taxonomy">
            <input type="hidden" value="<?php echo WPBDP_POST_TYPE; ?>" name="post_type">
            <?php wp_nonce_field('add-multiple-regions', '_wpnonce'); ?>

            <div class="form-field form-required">
                <label for="tag-name"><?php _ex('Name', 'add region form', 'wpbdp-regions'); ?></label>
                <textarea aria-required="true" id="tag-name" name="tag-name"></textarea>
                <p><?php _ex('The name of the Regions, one region per line.', 'add region form', 'wpbdp-regions'); ?></p>
            </div>

            <!--<div class="form-field">
                <label for="tag-slug">Slug</label>
                <input type="text" size="40" value="" id="tag-slug" name="slug">
                <p>The “slug” is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.</p>
            </div>-->

            <div class="form-field js-parent-field">
                <label for="parent"><?php _ex('Parent', 'add region form', 'wpbdp-regions'); ?></label>
                <!-- options will be stolen from other form -->
                <select class="postform" id="parent" name="parent"></select>
            </div>

            <!--<div class="form-field" style="display: none;">
                <label for="tag-description">Description</label>
                <textarea cols="40" rows="5" id="tag-description" name="description"></textarea>
                <p>The description is not prominent by default; however, some themes may show it.</p>
            </div>-->

            <p class="submit"><input type="submit" class="button button-primary" value="<?php _ex('Add Multiple Regions', 'add region form', 'wpbdp-regions'); ?>" class="button" id="submit" name="submit"></p>
        </form>
    </div>
</div>
