<div class="wpbdp-claim-listings">
    <a href="#" class="claim-listing-link">
        <span class="arrow-closed">▸</span>
        <span class="arrow-open">▾</span>
        <?php _ex( 'Claim This Listing', 'templates', 'wpbdp-claim-listings' ); ?>
    </a>

    <div class="claim-form-wrapper">
    <?php if ( ! is_user_logged_in() ): ?>
        <p><?php _ex( 'Please login in order to be able to claim this listing.','templates', 'wpbdp-claim-listings' ); ?></p>
        <?php echo wpbdp_render( 'parts/login-required' ); ?>
    <?php else: ?>
        <?php if ( $claim_pending ): ?>
        <div id="wpbdp-claim-listings-message" class="error">
            <?php _ex( 'You have already sent a claim request for this listing.', 'templates', 'wpbdp-claim-listings' ); ?>
        </div>
        <?php endif; ?>

        <?php if ( ! $claim_pending ): ?>
        <div id="wpbdp-claim-listings-message" style="display: none;"></div>
        <form data-action="<?php echo $url; ?>" method="post" id="wpbdp-claim-listings-form">
            <input type="hidden" name="listing_id" value="<?php echo $listing_id; ?>" />

            <div class="field">
                <label><?php _ex( 'Name', 'claim form', 'wpbpd-claim-listing' ); ?>
                <input type="text" value="<?php echo esc_attr( $user->display_name ); ?>" readonly="readonly" disabled="disabled" />
            </div>

            <div class="field">
                <label><?php _ex( 'E-Mail Address', 'claim form', 'wpbdp-claim-listings' ); ?>
                <input type="text" value="<?php echo esc_attr( $user->user_email ); ?>" readonly="readonly" disabled="disabled" />
            </div>

            <?php if ( wpbdp_get_option( 'claim-listing-form-details-show' ) ): ?>
            <div class="field">
                <label for="wpbdp-claim-form-details"><?php echo wpbdp_get_option( 'claim-listing-form-details-label' ); ?><?php echo wpbdp_get_option( 'claim-listing-form-details-required' ) ? ' *' : ''; ?></label>
                <textarea name="details" id="wpbdp-claim-form-details"></textarea>
            </div>
            <?php endif; ?>

            <?php if ( wpbdp_get_option( 'claim-listing-form-recaptcha-on' ) ): ?>
            <div class="field recaptcha">
                <?php echo wpbdp_recaptcha(); ?>
            </div>
            <?php endif; ?>

            <p class="buttons">
                <input type="reset" value="<?php _ex( 'Cancel', 'templates', 'wpbdp-claim-listings' ); ?>" />
                <input type="submit" value="<?php _ex( 'Claim Listing', 'templates', 'wpbdp-claim-listings' ); ?>" />
            </p>
        </form>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

