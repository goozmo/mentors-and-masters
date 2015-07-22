<?php
$category_list = array_keys( $listing->get_categories( 'current' ) );
$category_count = count( $category_list );
$category_list = wp_list_categories( 'title_li=&style=none&echo=0&taxonomy=' . WPBDP_CATEGORY_TAX . '&include=' . implode( ',', $category_list ) );
$category_list = rtrim( trim( str_replace( '<br />', ',', $category_list ) ), ',' );
?>
<h2><?php echo $listing->get_title(); ?> - Claim Confirmation</h2>

<p>
    <?php printf( __( 'You are about to become the owner of the listing %s.', 'wpbdp-claim-listings' ),
                  '<a href="' . $listing->get_permalink() . '">' . $listing->get_title() . '</a>' ); ?><br />
    <?php _e( 'In order to complete the claim, you will need to select a fee below and complete payment.  After that, the listing is all yours!',
              'wpbdp-claim-listings' ); ?>
</p>

<div id="wpbdp-claim-listings-confirm-fees">
    <h3>Fee Selection</h3>
    <div class="inner">
        <p>
            <?php printf( _n( 'Your listing is currently listed inside category %s.',
                              'Your listing is currently listed inside categories %s.',
                              $category_count ), $category_list ); ?><br />
            <?php _ex( 'You have to choose a fee plan for each category.', 'templates', 'wpbdp-claim-listings' ); ?>
        </p>
        <form action="" method="post">
            <?php if ( isset( $validation_errors ) && $validation_errors ): ?>
            <?php echo wpbdp_render_msg( implode( '<br />', $validation_errors ), 'error' ); ?>
            <?php endif; ?>
            <?php wp_nonce_field( 'pay claim fees ' . $claim->id ); ?>
            <?php foreach( $fees as &$f ): ?>
                <?php echo wpbdp_render( 'parts/category-fee-selection', array( 'category' => $f['term'],
                                                                                'multiple_categories' => count( $fees ) > 1,
                                                                                'current_fee' => $f['fee_id'],
                                                                                'category_fees' => $f['options']/*,
                                                                                'fee_rows_filter' => $callback*/ ) ); ?>
            <?php endforeach; ?>
            <input type="submit" name="go-to-checkout" value="<?php _ex( 'Continue to checkout', 'templates', 'wpbdp-claim-listings' ); ?>" />
        </form>
    </div>
</div>

<div id="wpbdp-claim-listings-confirm-reject">
    <h3>Withdraw claim</h3>
    <div class="inner">
        <form action="" method="post">
            <p><?php _ex( 'If you have changed your mind and do not wish to claim this listing as yours, please click the button below and we\'ll release this listing again.', 'templates', 'wpbdp-claim-listings' ); ?></p>
            <?php wp_nonce_field( 'withdraw claim ' . $claim->id ); ?>
            <input type="hidden" name="withdraw-claim" value="1" />
            <input type="submit" value="<?php _ex( 'Withdraw Claim', 'templates', 'wpbdp-claim-listings' ); ?>" />
        </form>
    </div>
</div>

