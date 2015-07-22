<?php

/**
 * This is the actual implementation of the PayPal gateway.
 * @since 3.4
 */
class WPBDP_PayPal_Gateway extends WPBDP_Payment_Gateway {

    const LIVE_URL = 'https://www.paypal.com/cgi-bin/webscr';
    const SANDBOX_URL = 'https://www.sandbox.paypal.com/cgi-bin/webscr';

    public function get_id() {
        return 'paypal';
    }

    public function get_name() {
        return __( 'PayPal', 'wpbdp-paypal' );
    }

    public function get_integration_method() {
        return WPBDP_Payment_Gateway::INTEGRATION_BUTTON;
    }

    public function register_config( &$settings ) {
        $s = $settings->add_section( 'payment',
                                     'paypal',
                                     _x( 'PayPal Gateway Settings', 'admin settings', 'WPBDM' ) );
        $settings->add_setting( $s,
                                'paypal',
                                _x( 'Activate Paypal?', 'admin settings', 'WPBDM' ),
                                'boolean',
                                false );
        $settings->add_setting( $s,
                                'paypal-business-email',
                                _x( 'PayPal Business Email', 'admin settings', 'WPBDM' ) );
        $settings->register_dep( 'paypal-business-email', 'requires-true', 'paypal' );
        
        $settings->add_setting( $s,
                                'paypal-merchant-id',
                                _x( 'PayPal Merchant ID', 'admin settings', 'WPBDM' ) );
        $settings->register_dep( 'paypal-merchant-id', 'requires-true', 'paypal' );
    }

    public function get_supported_currencies() {
        return array( 'AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'NOK',
                      'NZD', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'TWD', 'THB', 'TRY', 'USD' );
    }

    public function get_capabilities() {
        return array( 'recurring' );
    }

    public function validate_config() {
        if ( '' == trim( wpbdp_get_option( 'paypal-business-email' ) ) )
            return array( __( '"Business Email" missing.', 'wpbdp-paypal' ) );
    }

    public function get_rounded_period( $days ) {
        $units = array(
                'D' => array( 'days' => 1, 'limits' => array( 1, 90 ) ),
                'W' => array( 'days' => 7, 'limits' => array( 1, 52 ), 'D' => 7 ),
                'M' => array( 'days' => 30, 'limits' => array( 1, 24 ), 'W' => 4, 'D' => 30 ),
                'Y' => array( 'days' => 365, 'limits' => array( 1, 5 ), 'M' => 12, 'W' => 52, 'D' => 365 )
        );

        $best_match = null;
        $results = array();

        foreach ( $units as $u => $unit ) {
            $p = max( $unit['limits'][0], min( (int) ( $days / $unit['days'] ), $unit['limits'][1] ) );
            $diff = abs( $days - ( $p * $unit['days'] ) );

            $res = array( 'unit' => $u,
                          'p' => $p,
                          'diff' => $diff );
            $results[] = $res;

            if ( is_null( $best_match ) || $diff < $best_match['diff'] )
                $best_match = $res;
        }

        return $best_match;
    }

    public function render_unsubscribe_integration( &$category, &$listing ) {
        $html  = '';
        $html .= '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_subscr-find&alias=' . wpbdp_get_option( 'paypal-merchant-id' ) . '">';
        $html .= '<img src="https://www.paypalobjects.com/en_US/i/btn/btn_unsubscribe_LG.gif" BORDER="0">';
        $html .= '</a>';

        return $html;
    }

    public function render_integration( &$payment ) {
        $recurring = $payment->has_item_type( 'recurring_fee' );

        $html  = '';
        $html .= sprintf( '<form action="%s" method="POST">',
                          wpbdp_get_option( 'payments-test-mode' ) ? self::SANDBOX_URL : self::LIVE_URL );
        $paypal = array();

        // General button setup.
        $paypal['cmd'] = ! $recurring ? '_xclick' : '_xclick-subscriptions';
        $paypal['business'] = wpbdp_get_option( 'paypal-business-email' );
//        $paypal['test_ipn'] = wpbdp_get_option( 'payments-test-mode' ) ? 1 : 0;
        $paypal['currency_code'] = $payment->get_currency_code();
        $paypal['no_note'] = 1;
        $paypal['no_shipping'] = 1;
        $paypal['custom'] = $payment->get_id();
        $paypal['invoice'] = $payment->get_id();
        $paypal['rm'] = 2; // better use 2?
        
        // URLs.
        $paypal['notify_url'] = $this->get_url( $payment, 'notify' );
        $paypal['return'] = $this->get_url( $payment, 'return' );
        $paypal['cancel'] = $this->get_url( $payment, 'cancel' );

        if ( ! $recurring ) {
           $paypal['amount'] = number_format( $payment->get_total(), 2, '.', '' );
           $paypal['item_name'] = esc_attr( $payment->get_description() );
           $paypal['item_number'] = $payment->get_id();
           $paypal['quantity'] = 1;
        } else {
            $recurring_item = null;
            $regular_items = array();

            foreach ( $payment->get_items() as $item ) {
                if ( $item->item_type == 'recurring_fee' ) {
                    $recurring_item = $item;
                    continue;
                }

                $regular_items[] = $item;
            }

            if ( $regular_items ) {
                $paypal['a1'] = number_format( $regular_items[0]->amount, 2, '.', '' );
                $paypal['p1'] = 1;
                $paypal['t1'] = 'D';
            }

            $paypal['item_name'] = esc_attr( $regular_items ? _x( 'One time payment + recurring payment for renewal fees', 'paypal', 'WPBDM' ) : $recurring_item->description );
            $paypal['a3'] = number_format( $recurring_item->amount, 2, '.', '' );

            $recurring_period = $this->get_rounded_period( $recurring_item->data['fee_days'] );
            $paypal['p3'] = $recurring_period['p'];
            $paypal['t3'] = $recurring_period['unit'];

            $paypal['src'] = 1;
            // $paypal['srt'] = 1;
            $paypal['sra'] = 0; // Do not reattempt failed payments.
        }

        foreach ( $paypal as $k => $v )
            $html .= '<input type="hidden" name="' . $k . '" value="' . $v . '" />';

        if ( ! $recurring ) {
            $html .= sprintf( '<input type="image" src="%s" border="0" name="submit" alt="%s" />',
                              plugins_url( 'paypalbuynow.gif', __FILE__ ),
                              __( "Make payments with PayPal - it's fast, free and secure!", 'wpbdp-paypal' ) );
        } else {
            $html .= '<input type="hidden" name="bn" value="PP-SubscriptionsBF:btn_subscribe_LG.gif:NonHosted">';
            $html .= '<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">';
            $html .= '<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_subscribe_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">';
        }


        $html .= '</form>';

        return $html;
    }

    private function validate_transaction( &$payment ) {
       $payload = 'cmd=_notify-validate';
        foreach ( $_POST as $k => $v )
            $payload .= '&' . $k . '=' . urlencode( stripslashes( urldecode( $v ) ) );

        $ch = curl_init( wpbdp_get_option( 'payments-test-mode' ) ? self::SANDBOX_URL : self::LIVE_URL );
        curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt( $ch, CURLOPT_POST, 1 );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload );
        // curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 1 );
        // curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 2 );
        curl_setopt( $ch, CURLOPT_FORBID_REUSE, 1 );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Connection: Close' ) );
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);        
        $res = curl_exec( $ch );

        if ( curl_errno( $ch ) != 0 ) {
            curl_close( $ch );
            return false;
        }

        curl_close( $ch );
        // We perform the PayPal POST before internal checks in case our db is broken and to notify PayPal to stop sending the IPN notification.

        if ( ! $res || 0 != strcmp( $res, 'VERIFIED' ) )
            return false;

        if ( ! $payment->get_id() || $payment->get_id() != $_REQUEST['invoice'] )
            return false;

        if ( $payment->get_currency_code() != $_REQUEST['mc_currency'] )
            return false;

        // TODO: maybe this check is too harsh?
        if ( wpbdp_get_option( 'paypal-business-email' ) != $_REQUEST['receiver_email'] )
            return false;

        // TODO: maybe check mc_gross == $payment->get_total()? (not for recurring)

        return true;
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

    private function process_notify( &$payment ) {
        if ( ! $this->validate_transaction( $payment ) )
            return;

        // Handle recurring notifications a little different.
        if ( $payment->has_item_type( 'recurring_fee' ) ) {
            return $this->process_recurring_notify( $payment );
        }

        if ( ! $payment->is_pending() )
            return;
        
        $payment->set_data( 'txn_id', $_REQUEST['txn_id'] );

        // Set payer info.
        $payment->set_payer_info( 'first_name', trim( wpbdp_getv( $_REQUEST, 'first_name', '' ) ) );
        $payment->set_payer_info( 'last_name', trim( wpbdp_getv( $_REQUEST, 'last_name', '' ) ) );
        $payment->set_payer_info( 'country', trim( wpbdp_getv( $_REQUEST, 'residence_country', '' ) ) );
        $payment->set_payer_info( 'email', trim( wpbdp_getv( $_REQUEST, 'payer_email', '' ) ) );

        // Actual processing.
        switch ( strtolower( $_REQUEST['payment_status'] ) ) {
            // Canceled_Reversal: A reversal has been canceled. For example, you won a dispute with the customer, and the funds for the transaction that was reversed have been returned to you.
            // Completed: The payment has been completed, and the funds have been added successfully to your account balance.
            // Created: A German ELV payment is made using Express Checkout.
            // Denied: The payment was denied. This happens only if the payment was previously pending because of one of the reasons listed for the pending_reason variable or the Fraud_Management_Filters_x variable.
            // Expired: This authorization has expired and cannot be captured.
            // Failed: The payment has failed. This happens only if the payment was made from your customer's bank account.
            // Pending: The payment is pending. See pending_reason for more information.
            // Refunded: You refunded the payment.
            // Reversed: A payment was reversed due to a chargeback or other type of reversal. The funds have been removed from your account balance and returned to the buyer. The reason for the reversal is specified in the ReasonCode element.
            // Processed: A payment has been accepted.
            // Voided: This authorization has been voided.

            case 'completed':
                $payment->set_status( WPBDP_Payment::STATUS_COMPLETED, WPBDP_Payment::HANDLER_GATEWAY );
                break;

            case 'pending':
                break;

            default:
                $payment->set_status( WPBDP_Payment::STATUS_REJECTED, WPBDP_Payment::HANDLER_GATEWAY );
                break;
        }

        $payment->save();
    }

    private function process_recurring_notify( &$payment ) {
        if ( isset( $_REQUEST['payment_status'] ) && 'Pending' == $_REQUEST['payment_status'] )
            return;

        $listing = WPBDP_Listing::get( $payment->get_listing_id() );
        if ( ! $listing )
            return;

        $first_payment = $payment->is_first_recurring_payment();

        switch ( $_REQUEST['txn_type'] ) {
            case 'subscr_signup':
                break;

            case 'subscr_eot':
                // Remove 'recurring' bit from listing fee.
                $recurring_item = $payment->get_recurring_item();
                $listing->make_category_non_recurring( $recurring_item->rel_id_1 );
                break;

            case 'subscr_cancel':
                $payment->cancel_recurring();
                break;

            case 'subscr_payment':
                if ( $first_payment ) {
                    // Set payer info.
                    $payment->set_payer_info( 'first_name', trim( wpbdp_getv( $_REQUEST, 'first_name', '' ) ) );
                    $payment->set_payer_info( 'last_name', trim( wpbdp_getv( $_REQUEST, 'last_name', '' ) ) );
                    $payment->set_payer_info( 'country', trim( wpbdp_getv( $_REQUEST, 'residence_country', '' ) ) );
                    $payment->set_payer_info( 'email', trim( wpbdp_getv( $_REQUEST, 'payer_email', '' ) ) );

                    $payment->set_data( 'recurring_id', $_REQUEST['subscr_id'] );
                    $payment->set_status( WPBDP_Payment::STATUS_COMPLETED, WPBDP_Payment::HANDLER_GATEWAY );
                    $payment->save();
                } else {
                    $term_payment = $payment->generate_recurring_payment();
                    $term_payment->set_status( WPBDP_Payment::STATUS_COMPLETED, WPBDP_Payment::HANDLER_GATEWAY );
                    $term_payment->save();
                }

                break;
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

}

