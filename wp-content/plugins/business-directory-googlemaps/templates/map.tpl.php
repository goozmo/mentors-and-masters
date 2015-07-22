<?php
$with_directions = ( $settings['listingID'] > 0 && $settings['show_directions'] );
?>
<div class="cf">
<div id="wpbdp-map-<?php echo $settings['map_uid']; ?>" class="wpbdp-map wpbdp-google-map <?php echo $settings['map_size']; ?> <?php echo $with_directions ? 'with-directions' : ''; ?>" style="<?php echo $settings['map_style_attr']; ?>"></div>

<?php if ( $with_directions ): ?>
<div class="wpbdp-map-directions-config">
    <input type="hidden" name="listing_title" value="<?php echo esc_attr( get_the_title( $settings['listingID'] ) ); ?>" />
    <h4><?php _ex( 'Directions to listing', 'wpbdp-googlemaps' ); ?></h4>

    <div class="directions-from">
        <label><?php _ex( 'From:', 'wpbdp-googlemaps' ); ?></label>
        <label>
            <input type="radio" name="from_mode" value="current" checked="checked" /> <?php _ex( 'Current location', 'wpbdp-googlemaps' ); ?>
        </label>
        <label>
            <input type="radio" name="from_mode" value="address" > <?php _ex( 'Specific Address', 'wpbdp-googlemaps' ); ?>
        </label>
        <input type="text" name="from_address" class="directions-from-address" />
    </div>

    <div class="directions-travel-mode">
        <label><?php _ex( 'Travel Mode:', 'wpbdp-googlemaps' ); ?></label>
        <select name="travel_mode">
            <option value="driving"><?php _ex( 'Driving', 'wpbdp-googlemaps' ); ?></option>
            <option value="transit"><?php _ex( 'Public Transit', 'wpbdp-googlemaps' ); ?></option>
            <option value="walking"><?php _ex( 'Walking', 'wpbdp-googlemaps' ); ?></option>
            <option value="cycling"><?php _ex( 'Cycling', 'wpbdp-googlemaps' ); ?></option>
        </select>
    </div>

    <input type="submit" value="<?php _ex( 'Show Directions', 'wpbdp-googlemaps' ); ?>" class="find-route-btn">
</div>
</div>
<?php endif; ?>

<script type="text/javascript">
var map = new wpbdp.googlemaps.Map( 'wpbdp-map-<?php echo $settings['map_uid']; ?>',
                                    <?php echo json_encode( $settings ); ?> );
map.setLocations( <?php echo json_encode( $locations ); ?> );
map.render();

<?php if ( $with_directions ): ?>
var directions = new wpbdp.googlemaps.DirectionsHandler( map, jQuery( '.wpbdp-map-directions-config' ) );
<?php endif; ?>
</script>
</div>
