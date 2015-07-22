<h4><?php _e( 'Listing Location', 'wpbdp-googlemaps' ); ?></h4>
<p><?php _ex( 'If you want to adjust your listing\'s location, move the pin below to the correct position.', 'wpbdp-googlemaps' ); ?><br />
<?php _ex( 'When you\'re done, click on "Continue with listing submit" to complete the change.', 'wpbdp-googlemaps' ); ?></p>

<div class="wpbdp-googlemaps-place-chooser-container"></div>
<input type="hidden" name="location_override[lat]" value="" />
<input type="hidden" name="location_override[lng]" value="" />

<script type="text/javascript">
jQuery(function($) {
    var settings = { 'initial_value': <?php echo json_encode( $location ); ?>,
                     'done_after_drag': true,
                     'show_done_button': false,
                     'debug': true };
    var chooser = new wpbdp.googlemaps.PlaceChooser( $( '.wpbdp-googlemaps-place-chooser-container' ).get( 0 ), settings );
    chooser.when_done(function(res) {
        if ( ! res.success )
            return;

        $( 'input[name="location_override[lat]"]' ).val(res.lat);
        $( 'input[name="location_override[lng]"]' ).val(res.lng);
    });
    chooser.init();
});
</script>
