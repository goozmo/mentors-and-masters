<div id="wpbdp-claim-listings-reject" class="wpbdp-claim-listing-dialog">

<h3><?php _ex( 'Reject Claim', 'admin', 'wpbdp-claim-listings' ); ?></h3>
<form action="<?php echo add_query_arg( 'action', 'reject-claim' ); ?>" method="post">
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label>
                        <?php _ex( 'Comment to user (optional)', 'admin', 'wpbdp-claim-listings' ); ?>:
                    </label>
                </th>
                <td><textarea name="comment"></textarea></td>
            </tr>
        </tbody>
    </table>

    <p class="buttons">
        <input type="button"
               value="<?php _ex( 'Cancel', 'admin', 'wpbdp-claim-listings' ); ?>"
               class="button cancel" />
        <input type="submit"
               value="<?php _ex( 'Reject Listing Claim', 'admin', 'wpbdp-claim-listings' ); ?>"
               class="button button-primary" />
    </p>
</form>

</div>
