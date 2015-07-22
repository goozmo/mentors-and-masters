<?php

class WPBDP_PayFast_Gateway extends WPBDP_Payment_Gateway {

    const SANDBOX_URL = 'https://sandbox.payfast.co.za/eng/process';
    const LIVE_URL = 'https://www.payfast.co.za/eng/process';

    public function get_id() {
        return 'payfast';
    }

    public function get_name() {
        return 'PayFast';
    }

    public function get_integration_method() {
        return WPBDP_Payment_Gateway::INTEGRATION_BUTTON;
    }

    public function get_supported_currencies() {
        return array( 'ZAR' );
    }

    public function register_config( &$settings ) {
        $s = $settings->add_section( 'payment',
                                     'payfast',
                                     __( 'PayFast Gateway Settings', 'wpbdp-payfast' ) );
        $settings->add_setting( $s,
                                'payfast',
                                __( 'Activate PayFast?', 'wpbdp-payfast' ),
                                'boolean',
                                false );
        $settings->add_setting( $s,
                                'payfast-merchant',
                                __( 'Merchant E-Mail', 'wpbdp-payfast') );
        $settings->add_setting( $s,
                                'payfast-merchant_id',
                                __( 'Merchant ID', 'wpbdp-payfast') );
        $settings->add_setting( $s,
                                'payfast-merchant_key',
                                __( 'Merchant Key', 'wpbdp-payfast') );
        // $settings->add_setting( $s,
        //                         'payfast-pdt_key',
        //                         __( 'PDT Key', 'wpbdp-payfast') );
    }

    public function validate_config() {
        $errors = array();

        foreach ( array( 'merchant', 'merchant_id', 'merchant_key' ) as $k ) {
            $val = trim( wpbdp_get_option( 'payfast-' . $k ) );

            if ( !$val )
                $errors[] = sprintf( __( '%s is missing.', 'wpbdp-payfast' ), ucwords( str_replace( '_', ' ', $k ) ) );
        }

        return $errors;
    }

    public function render_integration( &$payment ) {
        $html  = '';
        $html .= sprintf( '<form action="%s" method="post">',
                          wpbdp_get_option( 'payments-test-mode' ) ? self::SANDBOX_URL : self::LIVE_URL );

        $payfast = array();
        $payfast['merchant_id'] = wpbdp_get_option( 'payfast-merchant_id' );
        $payfast['merchant_key'] = wpbdp_get_option( 'payfast-merchant_key' );
        $payfast['notify_url'] = esc_url( $this->get_url( $payment, 'notify' ) );
        $payfast['return_url'] = esc_url( $this->get_url( $payment, 'return' ) );
        $payfast['cancel_url'] = esc_url( $this->get_url( $payment, 'cancel' ) );
        $payfast['email_confirmation'] = 1;

        $payfast['m_transaction_id'] = $payment->get_id();
        $payfast['curstom_str1'] = $payment->get_id();
        $payfast['amount'] = number_format( $payment->get_total(), 2, '.', '' );
        $payfast['item_name'] = esc_attr( $payment->get_short_description() );
        $payfast['item_description'] = esc_attr( $payment->get_description() );

        if ( $current_user_id = get_current_user_id() ) {
            $user_info = get_userdata( $current_user_id );

            if ( isset( $user_info->user_firstname ) )
                $payfast['name_first'] = esc_attr( $user_info->user_firstname );

            if ( isset( $user_info->user_lastname ) )
                $payfast['name_last'] = esc_attr( $user_info->user_lastname );

            $payfast['email_address'] = esc_attr( $user_info->user_email );
        }

        foreach ( $payfast as $k => $v )
            $html .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';

        $html .= sprintf( '<input type="image" src="https://www.payfast.co.za/images/buttons/paynow_basic_logo.gif" alt="%s" title="%s" />',
                          __( 'Pay Now', 'wpbdp-payfast' ),
                          __( 'Pay Now with PayFast', 'wpbdp-payfast' ) );

        $html .= '</form>';

        return $html;
    }

    public function process( &$payment, $action ) {
        switch ( $action ) {
            case 'notify':
                return $this->process_notify( $payment );
            case 'cancel':
                return $this->process_cancel( $payment );
            case 'return':
                return $this->process_return( $payment );
        }
    }

    public function process_return( &$payment ) {
        $payment->set_data( 'returned', true );
        $payment->save();

        $url = $payment->get_redirect_url();
        wp_redirect( $url );
    }

    public function process_cancel( &$payment ) {
        $payment->set_status( WPBDP_Payment::STATUS_CANCELED, WPBDP_Payment::HANDLER_GATEWAY );
        $payment->add_error( __( 'The payment has been canceled at your request.', 'wpbdp-paypal' ) );
        $payment->save();

        wp_redirect( $payment->get_redirect_url() );
    }

    public function process_notify( &$payment ) {
        if ( ! $payment->is_pending() )
            return;

        $pf_data = stripslashes_deep( $_POST );
        $pf_string = '';

        // Dump the submitted variables and calculate security signature.
        foreach( $pf_data as $key => $val ) {
            if( $key != 'signature' )
                $pf_string .= $key .'='. urlencode( $val ) .'&';
        }

        $pf_string = substr( $pf_string, 0, -1 );
        $signature = md5( $pf_string );
        $result = ( $_POST['signature'] == $signature );

        if( function_exists( 'curl_init' ) ) {
            $ch = curl_init();

            $curlOpts = array(
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => false,
                CURLOPT_SSL_VERIFYHOST => true,
                CURLOPT_SSL_VERIFYPEER => false,

                CURLOPT_URL => wpbdp_get_option( 'payments-test-mode' ) ? 'https://sandbox.payfast.co.za/eng/query/validate' : 'https://www.payfast.co.za/eng/query/validate',
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $pf_string,
            );
            curl_setopt_array( $ch, $curlOpts );

            $res = curl_exec( $ch );
            curl_close( $ch );

            if( $res === false )
                return;
        } else {
            $header = "POST /eng/query/validate HTTP/1.0\r\n";
            $header .= "Host: ". ( $payments->in_test_mode() ? 'sandbox.payfast.co.za' : 'www.payfast.co.za' ) ."\r\n";
            $header .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $header .= "Content-Length: " . strlen( $pf_string ) . "\r\n\r\n";

            $socket = fsockopen( 'ssl://'. ( wpbdp_get_option( 'payments-test-mode' ) ? 'sandbox.payfast.co.za' : 'www.payfast.co.za' ), 443, $errno, $errstr, 10 );
            fputs( $socket, $header . $pf_string );

            $res = '';
            $headerDone = false;

            while( !feof( $socket ) ) {
                $line = fgets( $socket, 1024 );

                // Check if we are finished reading the header yet.
                if( strcmp( $line, "\r\n" ) == 0 ) {
                    // read the header
                    $headerDone = true;
                }
                // If header has been processed.
                else if( $headerDone ) {
                    $res .= $line;
                }
            }
        }

        $lines = explode( "\n", $res );
        $result = trim( $lines[0] );

        if( strcmp( $result, 'VALID' ) !== 0 )
            return;

        if ( $payment->get_id() != $pf_data['custom_str1'] )
            return;

        if ( isset( $pf_data['name_first'] ) )
            $payment->set_payer_info( 'first_name', trim( $pf_data['name_first'] ) );

        if ( isset( $pf_data['name_last'] ) )
            $payment->set_payer_info( 'last_name', trim( $pf_data['name_last'] ) );

        if ( isset( $pf_data['email_address'] ) )
            $payment->set_payer_info( 'email', trim( $pf_data['email_address'] ) );

        $payment->set_data( 'payfast_args', $pf_data );

        if ( 'COMPLETE' == $pf_data['payment_status'] )
            $payment->set_status( WPBDP_Payment::STATUS_COMPLETED, WPBDP_Payment::HANDLER_GATEWAY );
        else
            $payment->set_status( WPBDP_Payment::STATUS_REJECTED, WPBDP_Payment::HANDLER_GATEWAY );

        $payment->save();
    }

}
