<?php
/*
 * Plugin Name: Business Directory Plugin - PayFast Payment Module
 * Plugin URI: http://www.businessdirectoryplugin.com
 * Version: 3.5.1
 * Author: D. Rodenbaugh
 * Description: Business Directory Payment Gateway for PayFast.  Allows you to collect payments from Business Directory Plugin listings via PayFast.
 * Author URI: http://www.skylineconsult.com
 */

if ( !class_exists( 'WPBDP_Gateways_PayFast' ) ) {


class WPBDP_Gateways_PayFast {

    const VERSION = '3.5.1';
    const REQUIRED_BD = '3.5.2';


    function __construct() {
        add_action( 'plugins_loaded', array( &$this, 'initialize' ) );
    }

    function initialize() {
        // Load i18n.
        load_plugin_textdomain( 'wpbdp-payfast',
                                false,
                                trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );

        add_action( 'admin_notices', array( &$this, 'admin_notices' ) );

        if ( ! $this->check_requirements() )
            return;

        if ( ! wpbdp_licensing_register_module( 'PayFast Payment Module', __FILE__, self::VERSION ) )
           return;

        add_filter( 'wpbdp_settings_render', array( &$this, 'currency_setting' ), 10, 3 );
        add_action( 'wpbdp_register_gateways', array( &$this, 'register_gateway' ) );
    }

    function check_requirements() {
        return defined( 'WPBDP_VERSION' ) && version_compare( WPBDP_VERSION, self::REQUIRED_BD, '>=' );
    }

    function admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( $this->check_requirements() )
            return;

        echo '<div class="error"><p>';
        printf( _x( 'Business Directory - PayFast Module requires Business Directory Plugin >= %s.', 'wpbdp-payfast' ), self::REQUIRED_BD );
        echo '</p></div>';
    }

    function currency_setting( $html, $setting, $args=array() ) {
        if ( $setting->name != 'currency' || !wpbdp_get_option( 'payfast' ) )
            return $html;

        $html  = '';
        $html .= '<input type="hidden" name="wpbdp-currency" value="ZAR" />';
        $html .= __( 'South African Rand (ZAR)', 'wpbdp-payfast' );

        return $html;
    }

    function register_gateway( &$payments ) {
        if ( ! $this->check_requirements() )
            return;

        require_once( plugin_dir_path( __FILE__ ) . 'class-payfast-gateway.php' );
        $payments->register_gateway( 'payfast', new WPBDP_PayFast_Gateway() );
    }

}

new WPBDP_Gateways_PayFast();

}
