<?php
require_once( plugin_dir_path( __FILE__ ) . 'class-listing-claims-table.php' );

class WPBDP_Claim_Listing_Admin {

    function __construct() {
        add_action( 'wpbdp_admin_menu', array( &$this, '_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( &$this, 'admin_scripts' ) );
        //add_action( 'admin_notices', array( &$this, '_initial_setup_notice' ) );

        add_action( 'wpbdp_admin_metabox_generalinfo_list', array( &$this, 'listing_metabox_integration' ) );

        add_filter( 'wpbdp_admin_directory_views', array( &$this, 'directory_views' ), 10, 2 );
        add_filter( 'wpbdp_admin_directory_filter', array( &$this, 'directory_filter' ), 10, 2 );
        add_filter( 'wpbdp_admin_directory_bulk_actions', array( &$this, 'directory_bulk_actions' ) );
        add_action( 'wpbdp_admin_directory_handle_action', array( &$this, 'handle_bulk_actions' ) );

        add_filter( 'wpbdp_admin_directory_columns', array( &$this, 'directory_add_claim_status_column' ) );
        add_action( 'wpbdp_admin_directory_column_claim_status', array( &$this, 'directory_claim_status_column' ) );

        //add_action( 'wp_ajax_wpbdp-claim-listings-setup', array( &$this, '_ajax_initial_setup' ) );
        add_action( 'wp_ajax_wpbdp-claim-listings-requestinfo', array( &$this, 'ajax_requestinfo' ) );
    }

    function _admin_menu( $id ) {
        add_submenu_page( $id,
                          __( 'Claim Listing', 'wpbdp-claim-listings' ),
                          __( 'Claim Listing', 'wpbdp-claim-listings' ),
                          'administrator',
                          'wpbdp-claim-listings',
                          array( &$this, 'dispatch' ) );
    }

    function _initial_setup_notice() {
        if ( ! wpbdp_get_option( 'claim-listing-enabled' ) || get_option( 'wpbdp-claim-listings-setup-done', false ) )
            return;

        $users = wp_dropdown_users( array( 'show' => 'user_login', 'echo' => FALSE, 'id' => '' ) );

        echo '<div id="wpbdp-claim-listings-initial-setup" class="error">';
        echo '<p>';
        echo _x( 'To get started with Claim Listings, we first need to know what to do with your existing Business Directory listings. Should we...?', 'admin', 'wpbdp-claim-listings' );
        echo '<br /><br />';

        echo '<div class="setup-choice">';
        echo '<input type="radio" name="config" value="all-except-by-user" /> ';
        printf( _x( 'Mark all listings by %s as <b>Unclaimed</b>, mark the reminder as claimed.', 'admin', 'wpbdp-claim-listings' ), $users );
        echo '</div>';

        echo '<div class="setup-choice">';
        echo '<input type="radio" name="config" value="all-by-user" /> ';
        printf( _x( 'Mark all existing listings by %s as <b>already claimed</b>, mark the reminder as unclaimed.', 'admin', 'wpbdp-claim-listings' ), $users );
        echo '</div>';

//        echo '<div class="setup-choice">';
//        echo '<input type="radio" name="config" value="all-claimed" /> ';
//        _ex( 'Mark all existing listings as claimed already (by their current author).', 'admin', 'wpbdp-claim-listings' );
//        echo '</div>';

        echo '<div class="setup-choice">';
        echo '<input type="radio" name="config" value="skip" checked="checked" /> ';
        _ex( 'Leave listings as they are (I\'ll handle this manually).', 'admin', 'wpbdp-claim-listings' );
        echo '</div>';
        echo '</p>';

        echo '<p>';
        echo '<input type="button" class="button button-primary" value="' . _x( 'Finish setup of Claim Listings', 'admin', 'wpbdp-claim-listings' ) . '" />';
        echo '<input type="button" class="button dismiss" value="' . _x( 'Dismiss message', 'admin', 'wpbdp-claim-listings' ) . '" />';
        echo '</p>';
        echo '</div>';
    }

    function _ajax_initial_setup() {
        global $wpdb;

        $res = new WPBDP_Ajax_Response();

        $config = isset( $_POST['config'] ) ? trim( $_POST['config'] ) : '';

        // TODO: maybe we should handle pending claims in case they are and already claimed/unclaimed listings?
        switch( $config ) {
            case 'skip':
                break;
/*            case 'all-claimed':
                $query = $wpdb->prepare( "
                    INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
                    SELECT p.ID, %s, 1 FROM wp_posts p LEFT JOIN wp_postmeta m
                    ON m.post_id = p.ID AND m.meta_key = %s
                    WHERE p.post_type = %s AND m.meta_value IS NULL ",
                    '_wpbdp[claimed]',
                    '_wpbdp[claimed]',
                    WPBDP_POST_TYPE
                );
                $wpdb->query( $query );
                break;*/
            case 'all-except-by-user':
                $user = intval( isset( $_POST['user'] ) ? trim( $_POST['user'] ) : '' );

                if ( ! $user )
                    $res->send_error();

                $query = $wpdb->prepare( "
                    INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
                    SELECT p.ID, %s, 1 FROM wp_posts p LEFT JOIN wp_postmeta m
                    ON m.post_id = p.ID AND m.meta_key = %s
                    WHERE p.post_author != %d AND p.post_type = %s AND m.meta_value IS NULL",
                    '_wpbdp[claimed]',
                    '_wpbdp[claimed]',
                    $user,
                    WPBDP_POST_TYPE
                );
                $wpdb->query( $query );
                break;
            case 'all-by-user':
                $user = intval( isset( $_POST['user'] ) ? trim( $_POST['user'] ) : '' );

                if ( ! $user )
                    $res->send_error();

                $query = $wpdb->prepare( "
                    INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
                    SELECT p.ID, %s, 1 FROM wp_posts p LEFT JOIN wp_postmeta m
                    ON m.post_id = p.ID AND m.meta_key = %s
                    WHERE p.post_author = %d AND p.post_type = %s AND m.meta_value IS NULL",
                    '_wpbdp[claimed]',
                    '_wpbdp[claimed]',
                    $user,
                    WPBDP_POST_TYPE
                );
                $wpdb->query( $query );
                break;
            default:
                $res->send_error();
        }

        update_option( 'wpbdp-claim-listings-setup-done', true );
        $res->set_message( _x( 'Initial setup done.', 'admin', 'wpbdp-claim-listings' ) );
        $res->send();
    }

    function admin_scripts() {
        wp_enqueue_style( 'wpbdp-claim-listings-admin-css',
                           plugins_url( 'resources/admin.css', __FILE__ ),
                           array( 'wpbdp-admin' ) );
        wp_enqueue_script( 'wpbdp-claim-listings-admin-js',
                           plugins_url( 'resources/admin.js', __FILE__ ) );
        add_thickbox();
    }

    // {{{ Directory screen integration.

    function directory_add_claim_status_column( $columns ) {
        // Add after sticky status.
        $sticky_index = array_search( 'sticky_status', array_keys( $columns ) );

        return array_merge( array_slice( $columns, 0, $sticky_index + 1, true  ),
                            array( 'claim_status' => __( 'Claim Status', 'wpbdp-listing-claim' ) ),
                            array_slice( $columns, $sticky_index + 1, null, true ) );
    }

    function directory_claim_status_column( $listing_id ) {
        global $wpdb, $wpbdp_claim;

        $status = $wpbdp_claim->get_listing_status( $listing_id );

        echo '<span class="tag ' . $status . '">' . $wpbdp_claim->get_listing_status_text( $status ) . '</span>';

        if ( ! current_user_can( 'administrator' ) )
            return;

        $row_actions = array();

        switch ( $status ) {
            case 'claims-pending':
                $row_actions['see-claims'] = sprintf( '<a href="%s">%s</a>',
                                                      admin_url( 'admin.php?page=wpbdp-claim-listings&listing=' . $listing_id ),
                                                      __( 'See Claims', 'wpbdp-claim-listings' ) );
                break;
            case 'claimed':
                $row_actions['unclaim'] = sprintf( '<a href="%s">%s</a>',
                                                   add_query_arg( array( 'wpbdmaction' => 'bulk-mark-unclaimed',
                                                                         'post' => $listing_id ) ),
                                                   _x( 'Unclaim', 'admin', 'wpbdp-claim-listings' ) );
                break;
            case 'unclaimed':
                if ( get_current_user_id() != $wpbdp_claim->get_unclaimed_user() )
                    $row_actions['claim'] = sprintf( '<a href="%s">%s</a>',
                                                     add_query_arg( array( 'wpbdmaction' => 'bulk-mark-claimed',
                                                                           'post' => $listing_id ) ),
                                                     _x( 'Claim', 'admin', 'wpbdp-claim-listings' ) );
                break;
            default:
                break;
        }

        if ( $row_actions ) {
            echo '<div class="row-actions">';

            foreach ( $row_actions as $action_id => $action_link ) {
                echo '<span>' . $action_link . '</span>';
            }

            echo '</div>';
        }
    }

    function listing_metabox_integration( $listing_id ) {
        global $wpbdp_claim;

        $status = $wpbdp_claim->get_listing_status( $listing_id );

        echo '<dt>';
        _ex( 'Claim Status', 'metabox', 'wpbdp-claim-listings' );
        echo '<dd>';
        echo '<span class="tag ' . $status . '">' . $wpbdp_claim->get_listing_status_text( $status ) . '</span>';

        if ( current_user_can( 'administrator' ) && ( 'claimed' === $status || 'unclaimed' === $status ) ) {
            echo '<br />';
            printf( '<span><a href="%s">%s</a></span>',
                    add_query_arg( array( 'wpbdmaction' => 'bulk-mark-' . ( 'claimed' == $status ? 'unclaimed' : 'claimed' ),
                                          'post' => $listing_id ) ),
                    'claimed' === $status ? _x( 'Unclaim listing', 'admin', 'wpbdp-claim-listings' ) : _x( 'Claim listing', 'admin', 'wpbdp-claim-listing' ) );
        }

        echo '</dd>';
        echo '</dt>';
    }

    function directory_views( $views, $post_statuses ) {
        global $wpdb, $wpbdp_claim;

        $unclaimed_user = $wpbdp_claim->get_unclaimed_user();

        $total_listings = intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status IN ({$post_statuses})",
            WPBDP_POST_TYPE ) ) );
        $unclaimed = intval( $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_author = %d AND p.post_status IN ({$post_statuses})",
            WPBDP_POST_TYPE,
            $unclaimed_user ) ) );

        $views['claimed'] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                                     add_query_arg( 'wpbdmfilter', 'claimed', remove_query_arg( 'post' ) ),
                                     'claimed' == wpbdp_getv( $_REQUEST, 'wpbdmfilter' ) ? 'current' : '',
                                     _x( 'Claimed', 'admin', 'wpbdp-claim-listings' ),
                                     number_format_i18n( $total_listings - $unclaimed ) );
        $views['unclaimed'] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                                       add_query_arg( 'wpbdmfilter', 'unclaimed', remove_query_arg( 'post' ) ),
                                       'unclaimed' == wpbdp_getv( $_REQUEST, 'wpbdmfilter' ) ? 'current' : '',
                                       _x( 'Unclaimed', 'admin', 'wpbdp-claim-listings' ),
                                       number_format_i18n( $unclaimed ) );

        return $views;
    }

    function directory_filter( $pieces, $filter = '' ) {
        global $wpdb, $wpbdp_claim;

        $unclaimed_user = $wpbdp_claim->get_unclaimed_user();

        switch ( $filter ) {
            case 'claimed':
                $pieces['where'] .= $wpdb->prepare( " AND {$wpdb->posts}.post_author != %d ", $unclaimed_user );
                break;
            case 'unclaimed':
                $pieces['where'] .= $wpdb->prepare( " AND {$wpdb->posts}.post_author = %d ", $unclaimed_user );
                break;
        }

        return $pieces;
    }

    function directory_bulk_actions( $actions ) {
        $actions['sepclaim'] = '--';
        $actions['bulk-mark-claimed'] = _x( 'Mark as Claimed', 'admin actions', 'wpbdp-claim-listings' );
        $actions['bulk-mark-unclaimed'] = _x( 'Mark as Unclaimed', 'admin actions', 'wpbdp-claim-listings' );

        return $actions;
    }

    // }}}

    function dispatch() {
        $_SERVER['REQUEST_URI'] = remove_query_arg( array( 'wpbdmaction', 'post', '_wpnonce', 'status', 'action' ), $_SERVER['REQUEST_URI'] );

        $action = isset( $_GET['action'] ) ? $_GET['action'] : '';

        switch ( $action ) {
            case 'bulk-approve':
            case 'bulk-reject':
            case 'bulk-delete':
                $this->handle_bulk_actions();
                return $this->claims_table();
                break;
            case 'approve-claim':
                return $this->claim_approve();
                break;
            case 'reject-claim':
                return $this->claim_reject();
                break;
            case 'delete-claim':
                return $this->claim_delete();
                break;
            case 'view':
                return $this->claim_details();
                break;
            case 'send-email':
                return $this->send_email();
                break;
            default:
                return $this->claims_table();
        }
    }

    function handle_bulk_actions( $action = '' ) {
        global $wpbdp_claim;

        $action = $action ? $action : $_GET['action'];
        $items = isset( $_GET['post'] ) ? $_GET['post'] : ( isset( $_GET['claims'] ) ? $_GET['claims'] : array() );

        if ( ! is_array( $items ) )
            $items = array( $items );

        foreach ( $items as $claim_id ) {
            if ( 'bulk-mark-claimed' == $action )
                $wpbdp_claim->claim_listing( $claim_id ); // Not really a claim ID but an actual listing ID.
            elseif ( 'bulk-mark-unclaimed' == $action )
                $wpbdp_claim->unclaim_listing( $claim_id ); // Not really a claim ID but an actual listing ID.
            elseif ( 'bulk-approve' == $action )
                $wpbdp_claim->approve_claim( $claim_id );
            elseif( 'bulk-reject' == $action )
                $wpbdp_claim->reject_claim( $claim_id );
            elseif ( 'bulk-delete' == $action ) {
                $wpbdp_claim->delete_claim( $claim_id );
            }
        }

        if ( 'bulk-approve' == $action )
            wpbdp_admin_message( __( 'Listing claims approved.', 'wpbdp-listing-claim' ) );
        elseif ( 'bulk-reject' == $action )
            wpbdp_admin_message( __( 'Listing claims rejected.', 'wpbdp-listing-claim' ) );

        $_SERVER['REQUEST_URI'] = remove_query_arg( array( 'wpbdmaction', 'post', '_wpnonce' ), $_SERVER['REQUEST_URI'] );
    }

    // {{{ Views.

    function claim_approve() {
        global $wpbdp_claim;

        $claim_id = isset( $_REQUEST['id'] ) ? intval( $_REQUEST['id'] ) : 0;

        if ( ! $claim_id )
            return;

        if ( ! $wpbdp_claim->approve_claim( $claim_id ) )
            wpbdp_admin_message( _x( 'An error occurred while trying to approve this listing claim.',
                                     'admin',
                                     'wpbdp-claim-listings' ),
                                 'error' );
        else
            wpbdp_admin_message( _x( 'The listing claim has been approved.', 'admin', 'wpbdp-claim-listings' ) );

        return $this->claim_details();
    }

    function claim_reject() {
        global $wpbdp_claim;

        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        if ( ! $id )
            return;

        $comment = isset( $_POST['comment'] ) ? $_POST['comment'] : '';

        if ( $wpbdp_claim->reject_claim( $id, $comment ) )
            wpbdp_admin_message( _x( 'The listing claim has been rejected.', 'admin', 'wpbdp-claim-listings' ) );

        return $this->claim_details();
    }

    function claim_delete() {
        $nonce = isset( $_GET['_wpnonce'] ) ? $_GET['_wpnonce'] : '';
        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

        if ( ! $id || ! $nonce || ! wp_verify_nonce( $nonce, 'delete claim ' . $id ) )
            return;

        global $wpbdp_claim;
        if ( $wpbdp_claim->delete_claim( $id ) )
            wpbdp_admin_message( _x( 'The listing claim has been deleted.', 'admin', 'wpbdp-claim-listings' ) );

        return $this->claims_table();

    }

    function claim_details() {
        global $wpbdp_claim;

        $id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
        $claim = $wpbdp_claim->get_claim( $id );

        if ( ! $id || ! $claim  )
            return;

        $user = get_user_by( 'id', $claim->user_id );

        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/claim-details.tpl.php',
                                array( 'claim' => $claim, 'user' => $user ) );
    }

    function claims_table() {
        if ( isset( $_GET['listing'] ) ) {
            $msg = str_replace( '<a>',
                                '<a href="' . esc_url( remove_query_arg( 'listing' ) ) . '">',
                                sprintf( __( 'You are only seeing claims related to listing "%s". <a>Click here</a> to see all the claims.',
                                             'wpbdp-claim-listings' ),
                                         get_the_title( $_GET['listing'] ) ) );
            wpbdp_admin_message( $msg );
        }

        echo wpbdp_admin_header( null,
                                 null,
                                 array( array( _x( 'Go to settings', 'admin', 'wpbdp-claim-listings' ),
                                               admin_url( 'admin.php?page=wpbdp_admin_settings&groupid=claim-listings' ) ) ) );
        echo wpbdp_admin_notices();

        echo '<div id="wpbdp-claim-listings-claims">';

        echo '<form action="" method="get">';
        echo '<input type="hidden" name="page" value="' . $_GET['page'] . '" />';
        $table = new WPBDP_Listing_Claims_Table();
        $table->prepare_items();
        $table->views();
        $table->display();
        echo '</form>';
        echo '</div>';

        echo wpbdp_admin_footer();
    }

    function send_email() {
        global $wpbdp_claim;

        $claim_id = isset( $_GET['id'] ) ? $_GET['id'] : 0;
        $claim = $wpbdp_claim->get_claim( $claim_id );

        if ( ! $claim )
            return;

        if ( 'approved' == $claim->status ) {
            $wpbdp_claim->send_approval_email( $claim_id );
        } elseif ( 'rejected' == $claim->status ) {
            $wpbdp_claim->send_rejection_email( $claim_id );
        }

        wpbdp_admin_message( _x( 'E-Mail sent.', 'admin', 'wpbdp-claim-listings' ) );

        return $this->claim_details();
    }

    // }}}

    // {{{ AJAX.

    function ajax_requestinfo() {
        $claim_id = isset( $_POST['claim_id'] ) ? $_POST['claim_id'] : 0;
        $message = isset( $_POST['message'] ) ? stripslashes_deep( $_POST['message'] ) : array();

        if ( ! $claim_id || ! $message )
            die();

        $response = new WPBDP_Ajax_Response();
        $errors = array();

        if ( ! isset( $message['email'] ) || '' == trim( $message['email'] ) || ! is_email( $message['email'] ) )
            $errors['email'] = _x( 'Please use a valid e-mail address.', 'admin', 'wpbdp-claim-listings' );

        if ( ! isset( $message['body'] ) || '' == trim( $message['body'] ) )
            $errors['body'] = _x( 'A message is required.', 'admin', 'wpbdp-claim-listings' );

        if ( $errors )
            $response->send_error( $errors );

        $email = new WPBDP_Email();
        $email->to = $message['email'];
        $email->subject = $message['subject'];
        $email->body = $message['body'];
        $email->send();

        $response->set_message( _x( 'Message sent.', 'admin', 'wpbdp-claim-listings' ) );
        $response->send();
    }

    // }}}

}
