<?php
/*
 Plugin Name: Business Directory Plugin - 2Checkout Gateway Module
 Plugin URI: http://www.businessdirectoryplugin.com
 Version: 3.6
 Description: Business Directory Payment Gateway for 2Checkout.  Allows you to collect payments from Business Directory Plugin listings via 2Checkout.
 Author: D. Rodenbaugh
*/

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// This module is not included in the core of Business Directory Plugin. It is a separate add-on premium module and is not subject
// to the terms of the GPL license  used in the core package
// This module cannot be redistributed or resold in any modified versions of the core Business Directory Plugin product
// If you have this module in your possession but did not purchase it via businessdirectoryplugin.com or otherwise obtain it through businessdirectoryplugin.com  
// please be aware that you have obtained it through unauthorized means and cannot be given technical support through businessdirectoryplugin.com.
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


if ( ! class_exists( 'WPBDP_2Checkout_Module' ) ) {

class WPBDP_2Checkout_Module {

    const VERSION = '3.6';
    const REQUIRED_BD_VERSION = '3.5.2';


    public function __construct() {
        add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
        add_action( 'plugins_loaded', array( &$this, 'load_i18n' ) );
        add_action( 'wpbdp_register_gateways', array( &$this, 'register_gateway' ) );
    }

    private function check_requirements() {
        return defined( 'WPBDP_VERSION' ) && version_compare( WPBDP_VERSION, self::REQUIRED_BD_VERSION, '>=' );
    }

    public function admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( ! $this->check_requirements() ) {
            print '<div class="error"><p>';
            printf( __( 'Business Directory Plugin - 2Checkout Gateway Module requires Business Directory Plugin >= %s.', 'wpbdp-2checkout' ), self::REQUIRED_BD_VERSION );
            print '</p></div>';
            return;
        }

        global $wpbdp;
        if ( isset( $_GET['page'] ) && 'wpbdp_admin_fees' == $_GET['page'] && wpbdp_get_option( '2checkout' ) && wpbdp_get_option( 'listing-renewal-auto'  ) ) {
            echo '<div class="error"><p>';
            _e( 'Due to 2Checkout limitations only lengths multiples of a week can be used for recurring fees. All other fees will be charged as non-recurring during the checkout.', 'wpbdp-2checkout' );
            echo '</p></div>';
        }
    }

    public function load_i18n() {
        load_plugin_textdomain( 'wpbdp-2checkout', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );        
    }

    public function register_gateway( &$payments ) {
        if ( ! $this->check_requirements() )
            return;

        if ( ! wpbdp_licensing_register_module( '2Checkout Gateway Module', __FILE__, self::VERSION ) )
           return;

        require_once( plugin_dir_path( __FILE__ ) . 'class-2checkout-gateway.php' );
        $payments->register_gateway( '2checkout', new WPBDP_2Checkout_Gateway() );
    }        

}

new WPBDP_2Checkout_Module();

}
