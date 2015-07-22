<div id="wpbdp-claim-listings-requestinfo" class="wpbdp-claim-listing-dialog">

<h3><?php _ex( 'Request info from user', 'admin', 'wpbdp-claim-listings' ); ?></h3>
<form action="<?php echo add_query_arg( 'action', 'wpbdp-claim-listings-requestinfo', admin_url( 'admin-ajax.php' ) ); ?> " method="post">
    <input type="hidden" name="claim_id" value="<?php echo $claim->id; ?>" />
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label>
                        <?php _ex( 'Subject', 'admin', 'wpbdp-claim-listings' ); ?>:
                    </label>
                </th>
                <td>
                    <input type="text"
                           name="message[subject]"
                           value="<?php echo esc_attr( sprintf( _x( 'Regarding your claim of "%s"', 'admin', 'wpbdp-claim-listings' ),
                                                                get_the_title( $claim->listing_id ) ) ); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label>
                        <?php _ex( 'E-Mail', 'admin', 'wpbdp-claim-listings' ); ?>:
                    </label>
                </th>
                <td>
                    <input type="text"
                           name="message[email]"
                           value="<?php echo esc_attr( $user->user_email ); ?>" />
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label>
                        <?php _ex( 'Message', 'admin', 'wpbdp-claim-listings' ); ?>:
                    </label>
                </th>
                <td>
                    <textarea name="message[body]"></textarea>
                </td>
            </tr>
        </tbody>
    </table>

    <p class="buttons">
        <input type="button" 
               value="<?php _ex( 'Cancel', 'admin', 'wpbdp-claim-listings' ); ?>"
               class="button cancel" />
        <input type="submit"
               value="<?php _ex( 'Send Message', 'admin', 'wpbdp-claim-listings' ); ?>"
               class="button button-primary" />
    </p>
</form>

</div>
