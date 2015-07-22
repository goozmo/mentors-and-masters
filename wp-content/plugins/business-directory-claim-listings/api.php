<?php

class WPBDP_Claim_Listings_API {

    function __construct() {}

    function enabled() {
        return wpbdp_get_option( 'claim-listing-enabled' );
    }

    function can_claim_listing( $listing_id, $admin_side = false ) {
        if ( ! $this->enabled() )
            return false;

        $status = $this->get_listing_status( $listing_id );

        if ( $admin_side )
            return ( 'unclaimed' == $status || 'claims-pending' == $status );

        return 'unclaimed' == $status;
    }

    /**
     * Returns the configured "unclaimed user".
     * @param bool $full_details whether to return only the user ID or the full WP_User object. Default is FALSE.
     * @return int|object user ID or WP_User object.
     */
    function get_unclaimed_user( $full_details = false ) {
        $user_id = intval( wpbdp_get_option( 'claim-listing-unclaimed-user' ) );

        if ( $full_details )
            return get_user_by( 'id', $user_id );

        return $user_id;
    }

    function claim_listing( $listing_id = 0, $claim_id = 0 ) {
        global $wpdb;

        if ( ! $listing_id )
            return false;

        if ( ! $claim_id ) {
            $author_id = current_user_can( 'administrator' ) ? get_current_user_id() : 0;

            if ( ! $author_id )
                return false;

            if ( $author_id == $this->get_unclaimed_user() )
                return false;

            // Reject all pending claims for this listing.
            $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims',
                           array( 'status' => 'rejected',
                                  'processed_on' => current_time( 'mysql' ) ),
                           array( 'listing_id' => $listing_id, 'status' => 'pending' ) );

            $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims',
                           array( 'status' => 'rejected',
                                  'processed_on' => current_time( 'mysql' ) ),
                           array( 'listing_id' => $listing_id, 'status' => 'approved' ) );

            wp_update_post( array( 'ID' => $listing_id, 'post_author' => $author_id ) );
        } else {
            $claim = $this->get_claim( $claim_id );

            // Reject all pending claims for this listing.
            $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims',
                           array( 'status' => 'rejected',
                                  'processed_on' => current_time( 'mysql' ) ),
                           array( 'listing_id' => $listing_id, 'status' => 'pending' ) );

            if ( 'approved' != $claim->status )
                return false;

            // Mark claim as "completed" and assign listing to user.
            $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims', array( 'status' => 'completed' ), array( 'id' => $claim->id ) );
            wp_update_post( array( 'ID' => $claim->listing_id, 'post_author' => $claim->user_id ) );
        }

        return true;
    }

    function unclaim_listing( $listing_id ) {
        global $wpdb;

        if ( ! $listing_id )
            return false;

        wp_update_post( array( 'ID' => $listing_id, 'post_author' => wpbdp_get_option( 'claim-listing-unclaimed-user' ) ) );
    }

    /**
     * Returns the listing status with respect to the Claim Listing module.
     * @param int $listing_id The listing's ID.
     * @return string The claimed status. Possible values are: 'claimed' if the listing is claimed,
     *                                                         'unclaimed' if it is not and there are no pending claims,
     *                                                         'pending' if there is one approved claim pending payment,
     *                                                         'claims-pending' if the listing is unclaimed and there are pending claims.
     */
    function get_listing_status( $listing_id ) {
        $p = get_post( $listing_id );

        if ( $this->get_unclaimed_user() != intval( $p->post_author ) )
            return 'claimed';

        global $wpdb;
        if ( 1 == intval( $wpdb->get_var( $wpdb->prepare( "SELECT 1 AS x FROM {$wpdb->prefix}wpbdp_listing_claims WHERE listing_id = %d AND status = %s", $listing_id, 'approved' ) ) ) )
            return 'pending';
        elseif ( 1 == intval( $wpdb->get_var( $wpdb->prepare( "SELECT 1 AS x FROM {$wpdb->prefix}wpbdp_listing_claims WHERE listing_id = %d AND status = %s", $listing_id, 'pending' ) ) ) )
            return 'claims-pending';

        return 'unclaimed';
    }

    function get_listing_status_text( $status = '' ) {
        $translatable_strings = array( 'claimed' => _x( 'Claimed', 'admin', 'wpbdp-claim-listings' ),
                                       'unclaimed' => _x( 'Unclaimed', 'admin', 'wpbdp-claim-listings' ),
                                       'pending' => _x( 'Pending Payment', 'admin', 'wpbdp-claim-listings' ),
                                       'claims-pending' => _x( 'Claims Pending', 'admin', 'wpbdp-claim-listings' ) );

        return isset( $translatable_strings[ $status ] ) ? $translatable_strings[ $status ] : '';
    }

    function save_claim( $data = array(), &$error = null ) {
        global $wpdb;

        if ( ! is_array( $data ) )
            $data = (array)  $data;

        if ( ! $data )
            return;

        $data = stripslashes_deep( $data );
        $error = '';

        if ( ! isset( $data['listing_id'] ) || WPBDP_POST_TYPE != get_post_type( $data['listing_id'] ) )
            return false;

        if ( ! isset( $data['user_id'] ) || ! $data['user_id'] )
            return false;

        $row = array(
            'listing_id' => $data['listing_id'],
            'status' => 'pending',
            'user_id' => $data['user_id'],
            'user_comment' => isset( $data['user_comment'] ) ? trim( $data['user_comment'] ) : '',
            'created_on' => current_time( 'mysql' )
        );

        if ( ! $wpdb->insert( $wpdb->prefix . 'wpbdp_listing_claims', $row ) )
            return false;

        if ( wpbdp_get_option( 'claim-listing-notify-admin' ) ) {
            $email = new WPBDP_Email();
            $email->subject = sprintf( __( '[%s] New listing claim submitted', 'wpbdp-claim-listings' ), get_bloginfo( 'name' ) );
            $email->to[] = get_bloginfo( 'admin_email' );
            $email->html = wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/email-new-claim.tpl.php',
                                              array( 'claim' => $this->get_claim( $wpdb->insert_id ) ) );
            $email->send();
        }

        return true;
    }

    function get_claims( $status = 'pending', $listing_id = 0 ) {
        global $wpdb;

        $listing_id = intval( $listing_id );

        if ( ! $status || ! in_array( $status, array( 'all', 'approved', 'pending', 'rejected', 'completed' ) ) )
            $status = 'pending';

        if ( 'all' == $status ) {
            if ( $listing_id ) {
                $query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_listing_claims WHERE listing_id = %d ORDER BY id DESC",
                                         $listing_id );
            } else {
                $query = "SELECT * FROM {$wpdb->prefix}wpbdp_listing_claims ORDER BY id DESC";
            }
        } else {
            if ( $listing_id ) {
                $query = $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}wpbdp_listing_claims WHERE listing_id = %d AND status = %s ORDER BY id DESC",
                            $listing_id,
                            $status );
            } else {
                $query = $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}wpbdp_listing_claims WHERE status = %s ORDER BY id DESC",
                            $status );
            }
        }

        $results = $wpdb->get_results( $query );
        return $results;
    }

    function get_claim( $id ) {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_listing_claims WHERE id = %d", $id ) );
    }

    function reject_claim( $id, $reason = '' ) {
        global $wpdb;

        $claim = $this->get_claim( $id );

        if ( ! $claim )
            return;

        $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims',
                       array( 'status' => 'rejected',
                              'processed_on' => current_time( 'mysql' ),
                              'answer' => trim( stripslashes( $reason ) ) ),
                       array( 'id' => $id ) );
        $this->send_rejection_email( $claim->id );
        return true;
    }

    function delete_claim( $id ) {
        global $wpdb;

        $claim = $this->get_claim( $id );

        if ( ! $claim || ( 'rejected' !== $claim->status && 'completed' !== $claim->status ) )
            return false;

        return ( false !== $wpdb->delete( $wpdb->prefix . 'wpbdp_listing_claims', array( 'id' => $id ) ) );

    }

    function approve_claim( $id ) {
        global $wpdb;

        $claim = $this->get_claim( $id );

        if ( ! $claim || 'pending' != $claim->status )
            return false;

        if ( ! $this->can_claim_listing( $claim->listing_id, true ) )
            return false;

        // Reject other claims for this listing.
        $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims',
                       array( 'status' => 'rejected',
                              'processed_on' => current_time( 'mysql' ) ),
                       array( 'listing_id' => $claim->listing_id, 'status' => 'pending' ) );

        // Approve this claim and mark listing as claimed.
        $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims',
                       array( 'status' => 'approved',
                              'processed_on' => current_time( 'mysql' ) ),
                       array( 'id' => $id ) );

        $this->send_approval_email( $claim->id );

        return true;
    }

    function withdraw_claim( $claim_id ) {
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims',
                       array( 'status' => 'rejected' ),
                       array( 'id' => $claim_id ) );

        return true;
    }

    // {{{ E-mail.

    function send_approval_email( $claim_id ) {
        $claim = $this->get_claim( $claim_id );

        if ( ! $claim || 'approved' != $claim->status )
            return false;

        $user = get_user_by( 'id', $claim->user_id );
        $email_placeholders = array( 'listing' => esc_html( get_the_title( $claim->listing_id ) ),
                                     'name' => $user->first_name . ' ' . $user->last_name,
                                     'link' => add_query_arg( array( 'action' => 'claim-listing-confirm',
                                                                     'claim' => $claim->id ),
                                                              wpbdp_get_page_link( 'main' ) ),
                                     'days' => intval( wpbdp_get_option( 'claim-listing-unpaid-release-days' ) ) );

        $email = wpbdp_email_from_template( 'claim-listing-approval-msg', $email_placeholders );
        $email->to[] = $user->user_email;
        $email->send();

        return true;
    }

    function send_rejection_email( $claim_id ) {
        $claim = $this->get_claim( $claim_id );

        if ( ! $claim || 'rejected' != $claim->status || empty( $claim->answer ) )
            return false;

        $user = get_user_by( 'id', $claim->user_id );

        $placeholders = array( 'reason' => $claim->answer,
                               'listing' => esc_html( get_the_title( $claim->listing_id ) ),
                               'name' => $user->first_name . ' ' . $user->last_name );
        $email = wpbdp_email_from_template( 'claim-listing-approval-rejection-msg', $placeholders );
        $email->to[] = $user->user_email;
        $email->send();

    }

    // }}}

}
