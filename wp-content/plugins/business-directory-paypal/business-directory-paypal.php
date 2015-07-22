<?php
/*
 * Plugin Name: Business Directory Plugin - PayPal Gateway Module
 * Plugin URI: http://www.businessdirectoryplugin.com
 * Version: 3.5.1
 * Author: D. Rodenbaugh
 * Description: Business Directory Payment Gateway for PayPal.  Allows you to collect payments from Business Directory Plugin listings via PayPal.
 * Author URI: http://www.skylineconsult.com
 */

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
// This module is not included in the core of Business Directory Plugin. It is a separate add-on premium module and is not subject
// to the terms of the GPL license  used in the core package
// This module cannot be redistributed or resold in any modified versions of the core Business Directory Plugin product
// If you have this module in your possession but did not purchase it via businessdirectoryplugin.com or otherwise obtain it through businessdirectoryplugin.com  
// please be aware that you have obtained it through unauthorized means and cannot be given technical support through businessdirectoryplugin.com.
/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////


class WPBDP_PayPal_Module {

    const VERSION = '3.5.1';
    const REQUIRED_BD_VERSION = '3.5.2';


    public function __construct() {
        add_action( 'plugins_loaded', array( &$this, 'load_i18n' ) );
        add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
        add_action( 'wpbdp_register_gateways', array( &$this, 'register_gateway' ) );
    }

    private function check_requirements() {
        return defined( 'WPBDP_VERSION' ) && version_compare( WPBDP_VERSION, self::REQUIRED_BD_VERSION, '>=' );
    }

    public function load_i18n() {
        load_plugin_textdomain( 'wpbdp-paypal', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );
    }

    public function admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( $this->check_requirements() )
            return;

        echo '<div class="error"><p>';
        printf( 'Business Directory - PayPal Gateway Module requires Business Directory Plugin >= %s.', self::REQUIRED_BD_VERSION );
        echo '</p></div>';
    }

    public function register_gateway( &$payments ) {
        if ( ! $this->check_requirements() )
            return;

        if ( ! wpbdp_licensing_register_module( 'PayPal Gateway Module', __FILE__, self::VERSION ) )
           return;

        require_once( plugin_dir_path( __FILE__ ) . 'class-paypal-gateway.php' );
        $payments->register_gateway( 'paypal', new WPBDP_PayPal_Gateway() );
    }    

}

new WPBDP_PayPal_Module();
