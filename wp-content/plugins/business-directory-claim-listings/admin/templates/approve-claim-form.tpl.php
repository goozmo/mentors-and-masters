<div id="wpbdp-claim-listings-approve" class="wpbdp-claim-listing-dialog">
    <h3><?php _ex( 'Approve Claim', 'admin', 'wpbdp-claim-listings' ); ?></h3>

    <form action="<?php echo add_query_arg( 'action', 'approve-claim' ); ?>" method="post">
        <input type="hidden" name="claim_id" value="<?php echo $claim->id; ?>" />
        <h4>
        <?php
        printf( _x( 'Are you sure you want to give ownership of listing "%s" to %s?', 'admin', 'wpbdp-claim-listings' ),
                get_the_title( $claim->listing_id ),
                esc_html( $user->display_name ) );
        ?>
        </h4>

        <p class="buttons">
            <input type="button"
                   value="<?php _ex( 'Cancel', 'admin', 'wpbdp-claim-listings' ); ?>"
                   class="button cancel" />
            <input type="submit"
                   value="<?php _ex( 'Yes, proceed', 'admin', 'wpbdp-claim-listings' ); ?>"
                   class="button button-primary" />
        </p>
    </form>
</div>
