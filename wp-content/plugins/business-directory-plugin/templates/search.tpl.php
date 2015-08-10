<?php
// Goozmo done some editing 

$api = wpbdp_formfields_api();
//global $wp_query;
//echo "<pre>";
//print_r( $wp_query );
//echo "</pre>";
?>
<div id="wpbdp-search-page" class="wpbdp-search-page businessdirectory-search businessdirectory wpbdp-page">

    <div class="wpbdp-bar cf"><?php wpbdp_the_main_links(); ?></div>

    <h2 class="title"><?php _ex('Customize Your Search', 'search', 'WPBDM'); ?></h2>
    <?php if ( !$show_form ): ?>
    <a href="#" style="font-size: 90%; float: right; margin-right: 20px;" onclick="jQuery('#wpbdp-search-form-wrapper').show(); jQuery(this).remove();"><?php _ex('Return to Advanced Search', 'search', 'WPBDM'); ?></a>
    <?php endif; ?>    

<!-- Search Form -->
<div id="wpbdp-search-form-wrapper" style="<?php echo !$show_form ? 'display: none;' : ''; ?>">
<h3><?php _ex('All fields optional, use any combination', 'templates', 'WPBDM'); ?></h3>
<form action="" id="wpbdp-search-form" method="GET">
    <input type="hidden" name="action" value="search" />
    <input type="hidden" name="page_id" value="<?php echo wpbdp_get_page_id('main'); ?>" />
    <input type="hidden" name="dosrch" value="1" />
    <input type="hidden" name="q" value="" />

    <?php echo $fields; ?>
    <?php do_action('wpbdp_after_search_fields'); ?>

    <p>
<!--         <input type="reset" class="reset" value="<?php _ex( 'Clear', 'search', 'WPBDM' ); ?> " onclick="window.location.href = '<?php echo wpbdp_get_page_link( 'search' ); ?>';" /> -->
        <input type="submit" class="submit" value="<?php _ex('Search', 'search', 'WPBDM'); ?>" />
        <?php // Goozmo Edited this ?>
        <input type="button" class="button" onclick="clearForm( this.form );" value="Clear Fields" />
    </p>
</form>
</div>
<!-- Search Form -->

<?php if ($searching): ?>
	<?php // Goozmo Edited This
		global $wp_query;
		$no_records = $wp_query->found_posts
	?>
    <h3><?php echo $no_records; ?> <?php _ex('Search Result(s)', 'search', 'WPBDM'); ?></h3>    

    <?php do_action( 'wpbdp_before_search_results' ); ?>
    <div class="search-results">
    <?php if (have_posts()): ?>
        <?php echo wpbdp_render('businessdirectory-listings'); ?>
    <?php else: ?>
        <?php _ex("No listings found.", 'templates', "WPBDM"); ?>
        <br />
        
        <?php 
	        // Goozmo Done some editing here.  		
	        echo sprintf('<a href="%s">%s</a>.', '/explore/business-directory/?action=viewlistings',
                           _x('Return to directory', 'templates', 'WPBDM')); ?>    
    <?php endif; ?>
    </div>
    <?php do_action( 'wpbdp_after_search_results' ); ?>
    
    <script>
	    // Goozmo Edited this
	    jQuery('document').ready( function(){
		    var toPos = document.getElementsByClassName('search-results')[0].offsetTop || document.getElementsByClassName('search-results')[0].scrollTop;
		    window.scrollTo( 0, toPos );
	    });
	</script>
	
    
<?php endif; ?>
</div>
