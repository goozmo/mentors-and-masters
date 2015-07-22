<?php
/**
 * This is the actual implementation of the 2Checkout gateway.
 * @since 3.3
 */
class WPBDP_2Checkout_Gateway extends WPBDP_Payment_Gateway {

    public function get_id() {
        return '2checkout';
    }

    public function get_name() {
        return __( '2Checkout', 'wpbdp-2checkout' );
    }

    public function get_supported_currencies() {
        return array( 'ARS', 'AUD', 'BRL', 'GBP', 'CAD', 'DKK', 'EUR', 'HKD', 'INR', 'ILS', 'JPY', 'LTL', 'MYR',
                      'MXN', 'NZD', 'NOK', 'PHP', 'RON', 'RUB', 'SGD', 'ZAR', 'SEK', 'CHF', 'TRY', 'AED', 'USD' );
    }

    public function get_capabilities() {
        return array( 'recurring' );
    }

    public function get_integration_method() {
        return WPBDP_Payment_Gateway::INTEGRATION_BUTTON;
    }

    public function register_config( &$settings ) {
        $desc = '';
        if ( wpbdp_get_option( 'listing-renewal-auto' ) ) {
            $msg = __( 'For recurring payments to work you need to <a>specify an Instant Notification URL</a> in your 2Checkout dashboard.', 'wpbdp-2checkout' ) . '<br /> ' . 
                   __( 'Please use %s as the Instant Notification URL.', 'wpbdp-2checkout' );
            $url = '<b>' . $this->get_gateway_url( array( 'action' => 'ins' ) ) . '</b>';
            $desc .= str_replace( array( '<a>',
                                         '%s' ),
                                  array( '<a href="https://www.2checkout.com/documentation/notifications/" target="_blank">',
                                         $url ),
                                  $msg );
        }


        // TODO: instruct admins to use "Direct Return" for this to work and make sure the URLs match the domain.
        // TODO: instruct admins to use INS when possible?
        $s = $settings->add_section( 'payment',
                                     '2checkout',
                                     _x( '2Checkout Gateway Settings', 'admin settings', 'WPBDM' ),
                                     $desc );
        $settings->add_setting( $s,
                                '2checkout',
                                _x( 'Activate 2Checkout?', 'admin settings', 'WPBDM' ),
                                'boolean',
                                false );
        
        $settings->add_setting( $s,
                                '2checkout-seller',
                                _x( '2Checkout seller/vendor ID', 'admin settings', 'WPBDM' ) );
        $settings->register_dep( '2checkout-seller', 'requires-true', '2checkout' );

//        $settings->add_setting( $s,
//                                '2checkout-secret',
//                                _x( '2Checkout secret word', 'admin settings', 'WPBDM' ) );
//        $settings->register_dep( '2checkout-seller', 'requires-true', '2checkout' );
    }

    public function validate_config() {
        if ( '' == trim( wpbdp_get_option( '2checkout-seller') ) )
            return array( __( '2Checkout seller/vendor ID missing.', 'wpbdp-2checkout' ) );
    }

    public function render_integration( &$payment ) {
        global $wpbdp;
        $debug_on = $wpbdp->is_debug_on();

        $html  = '';

        if ( ! $debug_on && wpbdp_get_option( 'payments-test-mode' ) )
            $html .= '<input type="hidden" name="demo" value="Y" />';

        $html .= sprintf( '<form action="%s" method="post">', wpbdp_get_option( 'payments-test-mode' ) ? 'https://sandbox.2checkout.com/checkout/purchase' : 'https://www.2checkout.com/checkout/purchase' );
        $html .= sprintf( '<input type="hidden" name="sid" value="%s" />', wpbdp_get_option( '2checkout-seller' ) );
        $html .= '<input type="hidden" name="mode" value="2CO" />';
        $html .= sprintf( '<input type="hidden" name="merchant_order_id" value="%s" />', $payment->get_id() );
        $html .= '<input type="hidden" name="pay_method" value="CC" />';
        $html .= sprintf( '<input type="hidden" name="x_receipt_link_url" value="%s" />', $this->get_gateway_url() );

        $html .= sprintf( '<input type="hidden" name="currency_code" value="%s" />', $payment->get_currency_code() );

        $n = 0;
        foreach ( $payment->get_items() as $item ) {
            $html .= '<input type="hidden" name="li_' . $n . '_type" value="product" />';
            $html .= sprintf( '<input type="hidden" name="li_%d_name" value="%s" />', $n, esc_attr( $item->description ) );
            $html .= '<input type="hidden" name="li_' . $n . '_quantity" value="1" />';
            $html .= '<input type="hidden" name="li_' . $n . '_tangible" value="N" />';
            $html .= sprintf( '<input type="hidden" name="li_%d_price" value="%s" />', $n, number_format( $item->amount, 2, '.', '' ) );

            if ( 'recurring_fee' == $item->item_type ) {
                $recurrence = sprintf( '%d Week', intval( intval( $item->data['fee_days'] ) / 7 ) );
                $html .= sprintf( '<input type="hidden" name="li_%d_recurrence" value="%s">', $n, $recurrence );
                $html .= sprintf( '<input type="hidden" name="li_%d_duration" value="Forever">', $n );
            }

            $n++;
        }

        if ( $debug_on && wpbdp_get_option( 'payments-test-mode' ) ) {
            $html .= '<input type="hidden" name="card_holder_name" value="John A. Doe" />';
            $html .= '<input type="hidden" name="street_address" value="1234 Evergreen Av." />';
            $html .= '<input type="hidden" name="city" value="Springfield" />';
            $html .= '<input type="hidden" name="country" value="United States" />';
            $html .= '<input type="hidden" name="email" value="nobody@devnull.com" />';
        }

        $html .= sprintf( '<input type="image" src="%s" border="0" name="submit" alt="%s" />',
                          plugins_url( 'twocheckoutbuynow.gif', __FILE__ ),
                          __( 'Pay with 2Checkout', 'wpbdp-2checkout' )
                        );
        $html .= '</form>';

        return $html;
    }

    /**
     * @since 3.5.1
     */
/*    public function setup_payment( &$payment ) {
        if ( ! $payment->has_item_type( 'recurring_fee' ) )
            return;

        $items = $payment->get_items();

        // 2Checkout only supports weekly intervals so anything that isn't is made non-recurring.
        foreach ( $items as &$item ) {
            if ( 'recurring_fee' != $item->item_type )
                continue;

            if ( ( intval( $item->data['fee_days'] ) % 7 ) != 0 ) {
                $item->item_type = 'fee';
                continue;
            }

            // Assume indefinite length until we receive a cancellation notification.
            // FIXME: this can be fixed if we process correct INS notifications.
            $item->data['fee_days'] = 0;
        }

        $payment->update_items( $items );
    }*/


    /*
     * @since 3.5.1
     */
    private function process_ins() {
        $payment_id = isset( $_REQUEST['vendor_order_id'] ) ? intval( $_REQUEST['vendor_order_id'] ) : 0;

        if ( ! $payment_id )
            die();

        $payment = WPBDP_Payment::get( $payment_id );
        if ( ! $payment || '2checkout' != $payment->get_gateway() )
            die();

        if ( ! $payment->has_item_type( 'recurring_fee' ) )
            die();
        
        $first_payment = $payment->is_first_recurring_payment();

        $msg = isset( $_REQUEST['message_type'] ) ? strtoupper( $_REQUEST['message_type'] ) : '';

        switch( $msg ) {
            case 'RECURRING_STOPPED':
            case 'RECURRING_COMPLETED':
                $payment->cancel_recurring();
                $payment->save();
                break;
            default:
                if ( $first_payment ) {
                    $payment->set_status( WPBDP_Payment::STATUS_COMPLETED, WPBDP_Payment::HANDLER_GATEWAY );
                    $payment->save();
                } else {
                    $term_payment = $payment->generate_recurring_payment();
                    $term_payment->set_status( WPBDP_Payment::STATUS_COMPLETED, WPBDP_Payment::HANDLER_GATEWAY );
                    $term_payment->save();
                }

                break;
        }

        die();
    }

    /**
     * @since 3.5.1
     */
    public function process_generic( $action = '' )  {
        if ( 'ins' == $action )
            return $this->process_ins();

        if ( 'postback' != $action || ! isset( $_REQUEST['merchant_order_id'] ) )
            die();

        $id = intval( $_REQUEST['merchant_order_id'] );
        $payment = WPBDP_Payment::get( $id );

        if ( '2checkout' != $payment->get_gateway() )
            die();

        return $this->process( $payment, 'process' );
    }

    public function process( &$payment, $action ) {
        if ( 'process' !== $action || ! $payment->is_pending() )
            return;

        // TODO: use 'key' for validation (see https://www.2checkout.com/documentation/checkout/passback/validation).
        $payment->set_payer_info( 'first_name', trim( wpbdp_getv( $_REQUEST, 'first_name', '' ) ) );
        $payment->set_payer_info( 'last_name', trim( wpbdp_getv( $_REQUEST, 'last_name', '' ) ) );
        $payment->set_payer_info( 'country', trim( wpbdp_getv( $_REQUEST, 'country', '' ) ) );
        $payment->set_payer_info( 'email', trim( wpbdp_getv( $_REQUEST, 'email', '' ) ) );
        $payment->set_payer_info( 'phone', trim( wpbdp_getv( $_REQUEST, 'phone', '' ) ) );
        $payment->set_data( 'gateway_order', trim( wpbdp_getv( $_REQUEST, 'order_number', '' ) ) );

        if ( 'Y' == wpbdp_getv( $_REQUEST, 'credit_card_processed', 'K' ) )
            $payment->set_status( WPBDP_Payment::STATUS_COMPLETED, WPBDP_Payment::HANDLER_GATEWAY );
        else
            $payment->set_status( WPBDP_Payment::STATUS_REJECTED, WPBDP_Payment::HANDLER_GATEWAY );

        $payment->save();

        wp_redirect( $payment->get_redirect_url() );
    }

}

