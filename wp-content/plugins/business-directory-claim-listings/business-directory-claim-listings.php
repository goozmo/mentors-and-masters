<?php
/*
 * Plugin Name: Business Directory Plugin - Claim Listings Module
 * Plugin URI: http://businessdirectoryplugin.com
 * Version: 1.0.1
 * Author: D. Rodenbaugh
 * Description: Claim listings allows you to fill your directory with existing businesses so you can promote your directory by getting businesses to claim their existing listing, similar to Yelp or Foursquare.
 * from your site, with or without a fee.
 * Author URI: http://businessdirectoryplugin.com
 */

class WPBDP_Claim_Listings_Module {

    const VERSION = '1.0.1';
    const DB_VERSION = 1;
    const REQUIRED_BD = '3.5.5';

    private $admin = null;
    private $api = null;


    function __construct() {
        add_action( 'plugins_loaded', array( $this, '_load_i18n' ) );
        add_action( 'admin_notices', array( &$this, '_admin_notices') );
        add_action( 'wpbdp_modules_loaded', array( &$this, '_load_actions' ) );
    }

    public function _load_i18n() {
        load_plugin_textdomain( 'wpbdp-claim-listings', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );
    }

    function _load_actions() {
        if ( version_compare( WPBDP_VERSION, self::REQUIRED_BD, '<' ) )
            return;

        if ( ! wpbdp_licensing_register_module( 'Claim Listings Module', __FILE__, self::VERSION ) )
           return;

        add_action( 'wpbdp_register_settings', array( &$this, '_register_settings' ) );
        add_action( 'wpbdp_modules_init', array( &$this, 'init' ) );
    }

    function init() {
        $this->_install_or_update();

        // Load API.
        global $wpbdp_claim;
        require_once( plugin_dir_path( __FILE__ ) . 'api.php' );
        $wpbdp_claim = $this->api = new WPBDP_Claim_Listings_API();

        if ( ! $wpbdp_claim->enabled() )
            return;

        require_once( plugin_dir_path( __FILE__ ) . 'admin/admin.php' );
        $this->admin = new WPBDP_Claim_Listing_Admin();

        add_action( 'wpbdp_action_page_claim-listing-confirm', array( &$this, 'claim_listing_confirm_page' ) );
        add_action( 'WPBDP_Payment::status_change', array( &$this, 'change_listing_ownership_after_payment' ) );
        add_action( 'wpbdp_enqueue_scripts', array( &$this, '_enqueue_scripts' ) );
        add_action( 'wpbdp_before_excerpt_view', array( &$this, 'claim_listing_form' ) );
        add_action( 'wpbdp_before_single_view', array( &$this, 'claim_listing_form' ) );
        add_action( 'wp_ajax_wpbdp-claim-listings', array( &$this, 'ajax_claim_listing' ) );
        add_action( 'before_delete_post', array( &$this, 'after_listing_delete' ) );

        if ( ! wp_next_scheduled( 'wpbdp_claim_listing_maintenance' ) ) {
            wp_schedule_event( time(), 'daily', 'wpbdp_claim_listing_maintenance' );
        }

        add_action( 'wpbdp_claim_listing_maintenance', array( &$this, 'reject_staled_claims' ) );
    }

    function claim_listing_confirm_page() {
        require_once ( plugin_dir_path( __FILE__ ) . 'view-listing-claim-confirm-page.php' );

        $claim_id = isset( $_GET['claim'] ) ? intval( $_GET['claim'] ) : 0;
        $page = new WPBDP_Listing_Claim_Confirm_Page( $claim_id );
        echo $page->dispatch();
    }

    function _admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( ! defined( 'WPBDP_VERSION' ) || version_compare( WPBDP_VERSION, self::REQUIRED_BD, '<' ) ) {
            echo '<div class="error"><p>';
            printf( __( 'Business Directory - Claim Listing requires Business Directory Plugin >= %s.', 'wpbdp-claim-listings' ),
                        self::REQUIRED_BD );
            echo '</p></div>';
            return;
        }
    }

    function _users_dropdown() {
        $users = get_users( array( 'fields' => array( 'ID', 'user_login' ) ) );
        $res = array();

        //$res[] = array( 0, _x( '(Do not change author)', 'settings', 'wpbdp-claim-listings' ) );
        foreach ( $users as $u )
            $res[] = array( $u->ID, $u->user_login );

        return $res;
    }


    function _install_or_update() {
        global $wpdb;

        $db_version = get_option( 'wpbdp-claim-listings-db-version', 0 );

        if ( $db_version == self::DB_VERSION )
            return;

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        $sql = "CREATE TABLE {$wpdb->prefix}wpbdp_listing_claims (
            id bigint(20) PRIMARY KEY  AUTO_INCREMENT,
            listing_id bigint(20) NOT NULL,
            status varchar(20) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT 'pending',
            user_id bigint(20) NOT NULL,
            user_comment text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
            answer text CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
            payment_id bigint(20) NOT NULL DEFAULT 0,
            created_on datetime NOT NULL,
            processed_on datetime NULL,
            data blob NULL
        ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

        dbDelta( $sql );
        update_option( 'wpbdp-claim-listings-db-version', self::DB_VERSION );
    }

    function _register_settings( $settings ) {
        $g = $settings->add_group( 'claim-listings',
                                   _x( 'Claim Listings', 'settings', 'wpbdp-claim-listings' ) );

        // General settings.
        $s = $settings->add_section( $g,
                                     'claim-listings/general',
                                     _x( 'General Settings', 'settings', 'wpbdp-claim-listings' ) );
        $settings->add_setting( $s,
                                'claim-listing-enabled',
                                _x( 'Enable "Claim Listing"?', 'settings', 'wpbdp-claim-listings' ),
                                'boolean',
                                true );
        $settings->add_setting( $s,
                                'claim-listing-unclaimed-user',
                                _x( 'Default owner of \'Unclaimed Listings\'', 'settings', 'wpbdp-claim-listings' ),
                                'choice',
                                1,
                                _x( 'Unclaimed listings should be owned by a single user, typically the administrator of the site or some other user that will never actually be managing a listing.', 'settings', 'wpbdp-claim-listings' ),
                                array( 'choices' => array( &$this, '_users_dropdown' ) )
                              );
        $settings->add_setting( $s,
                                'claim-listing-notify-admin',
                                _x( 'Notify admin of new claims?', 'settings', 'wpbdp-claim-listings' ),
                                'boolean',
                                true );

        // E-Mail settings.
        $s = $settings->add_section( 'email',
                                     'email/claim-listing-templates',
                                     _x( 'Claim Listing Templates', 'settings', 'wpbdp-claim-listings' ) );

        $settings->add_setting( $s,
                                'claim-listing-approval-msg',
                                _x( 'Approval e-mail', 'settings', 'wpbdp-claim-listings' ),
                                'email_template',
                                array( 'subject' => sprintf( '[[site-title]] %s', _x( 'Claim Request Approved', 'settings', 'wpbdp-claim-listings' ) ),
                                       'body' => _x( 'Your claim for the listing "[listing]" was  approved. Please visit [link] to pay and become the owner of the listing. If you do not perform this step the listing will be released after [days] days.', 'templates', 'wpbdp-claim-listings' ) ),
                                '',
                                array( 'placeholders' => array( 'listing' => _x( 'Listing\'s name', 'settings', 'wpbdp-claim-listings' ),
                                                                'name' => _x( 'Claimant\'s name', 'settings', 'wpbdp-claim-listings' ),
                                                                'link' => _x( 'Link to the confirm page', 'settings', 'wpbdp-claim-listings' ),
                                                                'days' => _x( 'Number of days before the listing can be claimed by other users', 'settings', 'wpbdp-claim-listings' ) ) )
                              );

        $placeholders = array( 'listing' => _x( 'Listing\'s name', 'settings', 'wpbdp-claim-listings' ),
                               'reason' => _x( 'Rejection reason', 'settings', 'wpbdp-claim-listings' ),
                               'name' => _x( 'Claimant\'s name', 'settings', 'wpbdp-claim-listings' ) );
        $settings->add_setting( $s,
                                'claim-listing-approval-rejection-msg',
                                _x( 'Rejection e-mail (when reason is provided)', 'settings', 'wpbdp-claim-listings' ),
                                'email_template',
                                array( 'subject' => sprintf( '[[site-title]] %s', _x( 'Claim Request Rejected', 'settings', 'wpbdp-claim-listings' ) ),
                                       'body' => _x( 'Your listing claim was rejected. Reason: [reason].', 'templates', 'wpbdp-claim-listings' ) ),
                                '',
                                array( 'placeholders' => $placeholders )
                              );

        // Submit claim form.
        $s = $settings->add_section( $g,
                                     'claim-listings/form',
                                     _x( 'Claim Listing Form', 'settings', 'wpbdp-claim-listings' ) );
        $settings->add_setting( $s,
                                'claim-listing-form-recaptcha-on',
                                _x( 'Put reCAPTCHA on the claim listing form (reduces spam claims)?', 'settings', 'wpbdp-claim-listings' ),
                                'boolean',
                                false,
                                str_replace( '<a>',
                                             '<a href="' . esc_url( admin_url( 'admin.php?page=wpbdp_admin_settings&groupid=general' ) ) . '">',
                                             _x( 'To activate SPAM protection for this form you need to activate reCAPTCHA for Business Directory <a>here</a> first.',
                                                 'settings',
                                                 'wpbdp-claim-listings' ) ) );
        $settings->add_setting( $s,
                                'claim-listing-form-details-show',
                                _x( 'Show a "Claim Reason" field?', 'settings', 'wpbdp-claim-listings' ),
                                'boolean',
                                true );
        $settings->add_setting( $s,
                                'claim-listing-form-details-required',
                                _x( 'Make the "Claim Reason" field required?', 'settings', 'wpbdp-claim-listings' ),
                                'boolean',
                                false );
        $settings->add_setting( $s,
                                'claim-listing-form-details-label',
                                _x( '"Claim Reason" field label', 'settings', 'wpbdp-claim-listings' ),
                                'text',
                                _x( 'Please provide additional details for your claim here', 'settings', 'wpbdp-claim-listings' ) );
        $settings->add_setting( $s,
                                'claim-listing-form-submit-received-text',
                                _x( 'Request Received Text', 'settings', 'wpbdp-claim-listings' ),
                                'text_template',
                                _x( 'We have received your request for claiming listing "[listing]" and will contact you shortly about it.',
                                    'settings',
                                    'wpbdp-claim-listings' ),
                                _x( 'This text is displayed after the user submits their claim to a listing.', 'settings', 'wpbdp-claim-listings' ),
                                array( 'placeholders' => array( 'listing' => _x( 'Listing\'s title', 'settings', 'wpbdp-claim-listings' ) ) ));

        // Payment.
        $s = $settings->add_section( $g, 'claim-listings/payment', _x( 'Payment', 'settings', 'wpbdp-claim-listings' ) );
        $settings->add_setting( $s,
                                'claim-listing-unpaid-release-days',
                                _x( 'Days before releasing claimed listings pending payment?', 'settings', 'wpbdp-claim-listings' ),
                                'text',
                                '5',
                                _x( 'This is the time in days between when a claim is made and when the payment must be received before BD will allow the listing to be claimed by someone else.', 'settings', 'wpbdp-claim-listings' ) );

        $help = str_replace( '<a>',
                             '<a href="' . admin_url( 'admin.php?page=wpbdp_admin_settings&groupid=email#email/claim-listing-templates' ) .  '">',
                             _x( 'You can configure the <a>Claim Listing approval and rejection e-mail templates</a>.',
                                 'settings',
                                 'wpbdp-claim-listings' ) );
        $s = $settings->add_section( $g,
                                     'claim-listings/email',
                                     _x( 'E-mail Templates', 'settings', 'wpbdp-claim-listings' ),
                                     $help );
    }

    function _enqueue_scripts() {
        global $wpbdp;

        wp_enqueue_style( 'wpbdp-claim-listings',
                          plugins_url( '/resources/wpbdp-claim-listings' . ( $wpbdp->is_debug_on() ? '' : '.min' ) . '.css', __FILE__ ) );
        wp_enqueue_script( 'wpbdp-claim-listings-js',
                           plugins_url( '/resources/wpbdp-claim-listings' . ( $wpbdp->is_debug_on() ? '' : '.min' ) . '.js', __FILE__ ),
                           array( 'jquery' ) );
    }

    function claim_listing_form( $listing_id ) {
        if ( ! $this->api->can_claim_listing( $listing_id ) )
            return;

        global $wpdb;

        $vars = array();
        $vars['url'] = add_query_arg( 'action', 'wpbdp-claim-listings', admin_url( 'admin-ajax.php' ) );
        $vars['listing_id'] = $listing_id;
        $vars['user'] = wp_get_current_user();
        $vars['claim_pending'] = intval( $wpdb->get_var( $wpdb->prepare(
                        "SELECT 1 AS x FROM {$wpdb->prefix}wpbdp_listing_claims WHERE user_id = %d AND listing_id = %d AND status = %s LIMIT 1",
                        get_current_user_id(),
                        $listing_id,
                        'pending' ) ) );

        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/claim-listing-form.tpl.php', $vars );
    }

    function ajax_claim_listing() {
        if ( ! is_user_logged_in() )
            die();

        $res = new WPBDP_Ajax_Response();

        $listing_id = isset( $_POST['listing_id'] ) ? $_POST['listing_id'] : 0;

        $request = array();
        $request['listing_id'] = intval( $listing_id );
        $request['user_id'] = get_current_user_id();
        $request['user_comment'] = isset( $_POST['details'] ) ? trim( $_POST['details'] ) : '';

        $error = '';
        if ( wpbdp_get_option( 'claim-listing-form-details-required' ) && ! $request['user_comment'] ) {
            $res->send_error( __( 'Please fill in the required fields.', 'wpbdp-claim-listings' ) );
        }

        if ( wpbdp_get_option( 'claim-listing-form-recaptcha-on' ) ) {
            if ( ! wpbdp_recaptcha_check_answer() ) {
                $res->send_error( __( 'Invalid reCAPTCHA entered.', 'wpbdp-claim-listings' ) );
            }
        }

        if ( ! $error ) {
            if ( ! $this->api->save_claim( $request, $error ) )
                $res->send_error( $error );
        }

        $res->set_message( wpbdp_text_from_template( 'claim-listing-form-submit-received-text',
                                                     array( 'listing' => esc_html( get_the_title( $request['listing_id'] ) ) ) ) );
        $res->send();
    }

    function change_listing_ownership_after_payment( &$payment ) {
        if ( ! $this->api->enabled() || ! $payment->has_item_type( 'listing-claim' ) )
            return;

        global $wpdb, $wpbdp_claim;

        $claim_item = $payment->get_item( array( 'item_type' => 'listing-claim' ) );
        $claim = $wpbdp_claim->get_claim( $claim_item->rel_id_1 );

        if ( $payment->is_canceled() || $payment->is_rejected() ) {
            $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims',
                           array( 'payment_id' => 0 ),
                           array( 'id' => $claim->id ) );
        }

        if ( ! $payment->is_completed() )
            return;

        $removals = $payment->get_items( array( 'item_type' => 'category-removal' ) );

        if ( $removals ) {
            $listing = WPBDP_Listing::get( $claim->listing_id );

            foreach ( $removals as $r ) {
                $listing->remove_category( $r->rel_id_1, true, true );
            }
        }

        $wpbdp_claim->claim_listing( $claim->listing_id, $claim->id );
    }

    function reject_staled_claims() {
        global $wpdb, $wpbdp_claim;

        $days = max( 1, abs( intval( wpbdp_get_option( 'claim-listing-unpaid-release-days' ) ) ) );
        $n_days_before = date( 'Y-m-d 00:00:00', strtotime( '-' . $days . ' days' ) );

        $staled = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}wpbdp_listing_claims WHERE status = %d AND processed_on < %s LIMIT 50",
                                                  'approved',
                                                  $n_days_before
                                                  ) );

        foreach ( $staled as $claim_id )
            $wpbdp_claim->reject_claim( $claim_id );
    }

    function after_listing_delete( $post_id ) {
        if ( WPBDP_POST_TYPE != get_post_type( $post_id ) )
            return;

        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'wpbdp_listing_claims', array( 'listing_id' => $post_id ) );
    }

}

new WPBDP_Claim_Listings_Module();
