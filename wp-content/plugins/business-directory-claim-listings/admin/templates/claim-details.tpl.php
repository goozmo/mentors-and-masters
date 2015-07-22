<?php
echo wpbdp_admin_header( sprintf( _x( 'Listing Claim Details (%s)', 'admin', 'wpbdp-claim-listings' ),
                                  get_the_title( $claim->listing_id ) ),
                        null,
                        array( array( '← Back to Claim Listing', admin_url( 'admin.php?page=wpbdp-claim-listings' ) ) ) );
echo wpbdp_admin_notices();
?>

<div id="wpbdp-claim-listings-admin">
    <div class="info-boxes cf">
        <div class="info-box claim-info">
            <h3><?php _ex( 'Claim Info', 'templates', 'wpbdp-claim-listings' ); ?></h3>
            <dl>
                <dt><?php _ex( 'Status', 'templates', 'wpbdp-claim-listings' ); ?></dt>
                <dd>
                    <span class="tag <?php echo $claim->status; ?>"><?php echo $claim->status; ?></span>
                </dd>
                <dt><?php _ex( 'Created On', 'templates', 'wpbdp-claim-listings' ); ?></dt>
                <dd><?php echo date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $claim->created_on ) ); ?></dd>
                <dt><?php _ex( 'Processed On', 'templates', 'wpbdp-claim-listings' ); ?></dt>
                <dd><?php echo $claim->processed_on ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $claim->processed_on ) ) : '--'; ?></dd>
                <dt><?php _ex( 'Listing', 'templates', 'wpbdp-claim-listings' ); ?></dt>
                <dd>
                    <a href="<?php echo esc_url( get_permalink( $claim->listing_id ) ); ?>" target="_blank"><?php echo get_the_title( $claim->listing_id ); ?></a>
                </dd>
            </dl>
        </div>

        <div class="info-box user-info">
            <h3><?php _ex( 'User Info', 'templates', 'wpbdp-claim-listings' ); ?></h3>
            <dl>
                <dt><?php _ex( 'Name', 'templates', 'wpbdp-claim-listings' ); ?></dt>
                <dd><?php echo $user->display_name; ?></dd>

                <dt><?php _ex( 'E-Mail', 'templates', 'wpbdp-claim-listings' ); ?></dt>
                <dd><a href="<?php echo esc_url( 'mailto:' . $user->user_email ); ?>"><?php echo $user->user_email; ?></a></dd>

                <dt><?php _ex( 'Submitted Info', 'templates', 'wpbdp-claim-listings' ); ?></dt>
                <dd>
                    <?php if ( $claim->user_comment ): ?>
                        <?php echo nl2br( esc_html( $claim->user_comment ) ); ?>
                    <?php else: ?>
                        <?php _ex( '(not provided)', 'templates', 'wpbdp-claim-listings' ); ?>
                    <?php endif; ?>
                </dd>
            </dl>
        </div>
    </div>

    <div id="wpbdp-claim-listings-actions">
        <?php if ( 'pending' == $claim->status  ): ?>
        <a href="#TB_inline?width=300&height=150&inlineId=wpbdp-claim-listings-approve-w" class="thickbox button">
            <?php _ex( 'Approve Claim', 'templates', 'wpbdp-claim-listings' ); ?>
        </a>
        <a href="#TB_inline?width=350&height=300&inlineId=wpbdp-claim-listings-reject-w" class="thickbox button">
            <?php _ex( 'Reject Claim', 'templates', 'wpbdp-claim-listings' ); ?>
        </a>
        <a href="#TB_inline?width=350&height=500&inlineId=wpbdp-claim-listings-requestinfo-w" class="thickbox button requestinfo">
            <?php _ex( 'Request info from user', 'templates', 'wpbdp-claim-listings' ); ?>
        </a>
        <?php elseif ( 'rejected' == $claim->status ): ?>
        <a href="<?php echo add_query_arg( 'action', 'send-email' ); ?>" class="button">
            <?php _ex( 'Send rejection e-mail again', 'templates', 'wpbdp-claim-listings' ); ?>
        </a>
        <a href="<?php echo add_query_arg( array( 'action' => 'delete-claim',
                                                  '_wpnonce' => wp_create_nonce( 'delete claim ' . $claim->id )  ) ); ?>" class="button">
            <?php _ex( 'Delete Claim', 'templates', 'wpbdp-claim-listings' ); ?>
        </a>
        <?php elseif ( 'approved' == $claim->status ): ?>
        <a href="<?php echo add_query_arg( 'action', 'send-email' ); ?>" class="button">
            <?php _ex( 'Send approval e-mail again', 'templates', 'wpbdp-claim-listings' ); ?>
        </a>
        <?php endif; ?>
    </div>

    <div id="wpbdp-claim-listings-approve-w" style="display: none;">
        <?php echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'approve-claim-form.tpl.php',
                                      array( 'claim' => $claim, 'user' => $user ) ); ?>
    </div>

    <div id="wpbdp-claim-listings-reject-w" style="display: none;">
        <?php echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'reject-claim-form.tpl.php' ); ?>
    </div>

    <div id="wpbdp-claim-listings-requestinfo-w" style="display: none;">
    <?php
        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'request-info-form.tpl.php',
                                array( 'claim' => $claim, 'user' => $user ) );
    ?>
    </div>

</div>
<?php echo wpbdp_admin_footer(); ?>
