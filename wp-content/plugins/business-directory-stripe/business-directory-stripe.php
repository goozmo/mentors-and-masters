<?php
/**
 * Plugin Name: Business Directory Plugin - Stripe Payment Module
 * Plugin URI: http://www.businessdirectoryplugin.com
 * Version: 3.6
 * Author: D. Rodenbaugh
 * Description: Business Directory Payment Gateway for Stripe.  Allows you to collect payments from Business Directory Plugin listings via Stripe.
 * Author URI: http://www.skylineconsult.com
 */

class WPBDP_Stripe_Module {

    const VERSION = '3.6';
    const REQUIRED_BD = '3.5.2';


    public function __construct() {
        add_action( 'plugins_loaded', array( &$this, 'initialize' ) );
        add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
    }

    private function check_requirements() {
        return defined( 'WPBDP_VERSION' ) && version_compare( WPBDP_VERSION, self::REQUIRED_BD, '>=' );
    }

    public function initialize() {
        // Load i18n.
        load_plugin_textdomain( 'wpbdp-stripe',
                                false,
                                trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );

        if ( ! $this->check_requirements() )
            return;

        if ( ! wpbdp_licensing_register_module( 'Stripe Payment Module', __FILE__, self::VERSION ) )
           return;

        add_action( 'wpbdp_register_gateways', array( &$this, 'register_gateway' ) );
        add_action( 'wpbdp_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );
    }

    public function admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( $this->check_requirements() )
            return;

        echo '<div class="error"><p>';
        printf( __( 'Business Directory - Stripe Gateway Module requires Business Directory Plugin >= %s.', 'wpbdp-stripe' ), self::REQUIRED_BD );
        echo '</p></div>';
    }

    public function register_gateway( &$payments ) {
        require_once( plugin_dir_path( __FILE__ ) . 'class-stripe-gateway.php' );
        $payments->register_gateway( 'stripe', new WPBDP_Stripe_Gateway() );
    }

    public function enqueue_scripts() {
        if ( ! wpbdp_get_option( 'stripe-use-custom-form' ) )
            return;

        wp_enqueue_script( 'stripe-api', 'https://js.stripe.com/v2/' );
        wp_enqueue_script( 'wpbdp-stripe', plugins_url( 'resources/custom-form.js', __FILE__ ), array( 'stripe-api', 'jquery' ) );
    }

}

global $wpbdp_stripe;
$wpbdp_stripe = new WPBDP_Stripe_Module();
