<?php
$claim_url = admin_url( 'admin.php?page=wpbdp-claim-listings&action=view&id=' . $claim->id );
?>
<p><?php _e( 'A new listing claim has been submitted. Details below.', 'wpbdp-claim-listings' ); ?></p>
<hr/>
<p>
    <b><?php _ex( 'ID', 'email', 'wpbdp-claim-listings' ); ?>:</b> <?php printf( '<a href="%s">%s</a>', $claim_url, $claim->id ); ?><br />
    <b><?php _ex( 'Date', 'email', 'wpbdp-claim-listings' ); ?>:</b> <?php echo $claim->created_on; ?><br />
    <b><?php _ex( 'Listing', 'email', 'wpbdp-claim-listings' ); ?>:</b> <?php printf( '<a href="%s">%s</a>', get_permalink( $claim->listing_id ), get_the_title( $claim->listing_id ) ); ?><br />
    <b><?php _ex( 'User', 'email', 'wpbdp-claim-listings' ); ?>:</b> <?php echo get_the_author_meta( 'user_login', $claim->user_id ); ?>
</p>

<p><?php echo str_replace( '<a>',
                           '<a href="' . $claim_url . '">',
                           _x( '<a>Click here</a> to manage this listing claim.', 'email', 'wpbdp-claim-listings' ) ); ?></p>
