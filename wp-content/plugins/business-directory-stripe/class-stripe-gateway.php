<?php
/**
 * This is the actual implementation of the Stripe gateway.
 * @since 1.0
 */
class WPBDP_Stripe_Gateway extends WPBDP_Payment_Gateway {

    public function get_id() {
        return 'stripe';
    }

    public function get_name() {
        return __( 'Stripe', 'wpbdp-stripe' );
    }

    public function get_supported_currencies() {
        // TODO: in fact Stripe supports a lot more. These are the same as PayPal's.
        return array( 'AUD', 'BRL', 'CAD', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN', 'NOK',
                      'NZD', 'PHP', 'PLN', 'GBP', 'RUB', 'SGD', 'SEK', 'CHF', 'TWD', 'THB', 'TRY', 'USD' );
    }

    public function get_capabilities() {
        return array( 'recurring' );
    }

    public function get_integration_method() {
        return WPBDP_Payment_Gateway::INTEGRATION_BUTTON;
    }

    public function register_config( &$settings ) {
        $msg = '';

        if ( wpbdp_get_option( 'listing-renewal-auto' ) ) {
            $msg .= __( 'For recurring payments to work you need to <a>specify a webhook URL</a> in your Stripe Account Settings.', 'wpbdp-stripe' ) . '<br />' .
                    __( 'Please use %s as the webhook URL Stripe will use to contact your site.', 'wpbdp-stripe' );
            $msg = str_replace( array( '<a>', '%s' ),
                                array( '<a href="https://stripe.com/docs/webhooks" target="_blank">',
                                       '<b>' . $this->get_gateway_url() . '</b>' ),
                                $msg );
        }

        $s = $settings->add_section( 'payment',
                                     'stripe',
                                     __( 'Stripe Gateway Settings', 'wpbdp-stripe' ),
                                     $msg );
        $settings->add_setting( $s,
                                'stripe',
                                __( 'Activate Stripe?', 'wpbdp-stripe' ),
                                'boolean',
                                false );
        $settings->add_setting( $s,
                                'stripe-use-custom-form',
                                __( 'Use a custom form instead of a "Stripe Checkout" button?', 'wpbdp-stripe' ),
                                'boolean',
                                false );
        $settings->add_setting( $s,
                                'stripe-test-secret-key',
                                __( 'TEST Secret Key', 'wpbdp-stripe') );
        $settings->register_dep( 'stripe-test-secret-key', 'requires-true', 'stripe' );

        $settings->add_setting( $s,
                                'stripe-test-publishable-key',
                                __( 'TEST Publishable Key', 'wpbdp-stripe') );
        $settings->register_dep( 'stripe-test-publishable-key', 'requires-true', 'stripe' );

        $settings->add_setting( $s,
                                'stripe-live-secret-key',
                                __( 'LIVE Secret Key', 'wpbdp-stripe') );
        $settings->register_dep( 'stripe-live-secret-key', 'requires-true', 'stripe' );

        $settings->add_setting( $s,
                                'stripe-live-publishable-key',
                                __( 'LIVE Publishable Key', 'wpbdp-stripe') );
        $settings->register_dep( 'stripe-live-publishable-key', 'requires-true', 'stripe' );
    }

    public function validate_config() {
        $errors = array();

        foreach ( array( 'secret-key', 'publishable-key' ) as $k ) {
            $option_name = wpbdp_get_option( 'payments-test-mode' ) ? 'stripe-test-' . $k : 'stripe-live-' . $k;
            $option_value = trim( wpbdp_get_option( $option_name ) );

            if ( !$option_value )
                $errors[] = sprintf( __( '%s is missing.', 'wpbdp-stripe' ), ucwords( str_replace( '-', ' ', $k ) ) );
        }

        return $errors;
    }

    public function render_unsubscribe_integration( &$category, &$listing ) {
        if ( $_POST  ) {
            if ( wp_verify_nonce( $_POST['_wpnonce'], 'cancel auto renewal' ) ) {
                require_once( plugin_dir_path( __FILE__ ) . 'vendors/stripe-php/lib/Stripe.php' );
                Stripe::setApiKey( wpbdp_get_option( 'payments-test-mode' ) ? wpbdp_get_option( 'stripe-test-secret-key' ) : wpbdp_get_option( 'stripe-live-secret-key' ) );

                try {
                    $customer = $this->get_stripe_customer( $listing->get_id() );
                    $subscription = $customer->subscriptions->retrieve( $category->recurring_id );
                    $subscription->cancel();

                    return wpbdp_render_msg( __( 'Your subscription was canceled.', 'wpbdp-stripe' ) );
                } catch ( Exception $e ) {
                    return wpbdp_render_msg( __( 'An error occurred while trying to cancel your subscription. Please try again later or contact the site administrator.', 'wpbdp-stripe' ),
                                             'error' );
                }
            }
        }

        $html  = '';
        $html .= '<form action="" method="post">';
        $html .= wp_nonce_field( 'cancel auto renewal', '_wpnonce', false, false );
        $html .= '<input type="submit" name="cancel_auto_renewal" value="' . __( 'Cancel Automatic Renewal', 'wpbdp-stripe' ) . '" />';
        $html .= '</form>';
        return $html;
    }

    public function render_integration( &$payment ) {
        if ( wpbdp_get_option( 'stripe-use-custom-form' ) ) {
            $vars = array();
            $vars['url'] = $this->get_url( $payment, 'process' );
            $vars['key'] = wpbdp_get_option( 'payments-test-mode' ) ? wpbdp_get_option( 'stripe-test-publishable-key' ) : wpbdp_get_option( 'stripe-live-publishable-key' );

            return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/checkout-form.tpl.php', $vars );
        }

        $stripe = array();
        $stripe['key'] = wpbdp_get_option( 'payments-test-mode' ) ? wpbdp_get_option( 'stripe-test-publishable-key' ) : wpbdp_get_option( 'stripe-live-publishable-key' );
        $stripe['amount'] = round( $payment->get_total() * 100, 0 );
        $stripe['name'] = esc_attr( get_bloginfo( 'name' ) );
        $stripe['description'] = esc_attr( $payment->get_short_description() );

        if ( $u = wp_get_current_user() )
            $stripe['email'] = $u->user_email ? esc_attr( $u->user_email ) : '';

        $stripe['label'] = __( 'Pay now via Stripe', 'wpbdp-stripe' );
        // $stripe['panel-label'] = _x( 'Pay' );
        $stripe['currency'] = strtolower( wpbdp_get_option( 'currency' ) );

        $html  = '';
        $html .= sprintf( '<form action="%s" method="POST">', $this->get_url( $payment, 'process' ) );
        $html .= '<script src="https://checkout.stripe.com/checkout.js" class="stripe-button" ';

        foreach ( $stripe as $k => $v )
            $html .= sprintf( 'data-%s="%s"', $k, $v );

        $html .= '></script>';
        $html .= '</form>';

        return $html;
    }

    public function process( &$payment, $action ) {
        if ( $action != 'process' )
            return;

        if ( ! $payment->is_pending() )
            return;

        $token = isset( $_POST['stripeToken'] ) ? $_POST['stripeToken'] : '';

        if ( ! $token )
            return;

        require_once( plugin_dir_path( __FILE__ ) . 'vendors/stripe-php/lib/Stripe.php' );
        Stripe::setApiKey( wpbdp_get_option( 'payments-test-mode' ) ? wpbdp_get_option( 'stripe-test-secret-key' ) : wpbdp_get_option( 'stripe-live-secret-key' ) );

        try {
            $recurring = $payment->has_item_type( 'recurring_fee' );

            if ( $recurring ) {
                $recurring_item = null;
                $regular_items = array();

                foreach ( $payment->get_items() as $item ) {
                    if ( $item->item_type == 'recurring_fee' ) {
                        $recurring_item = $item;
                        continue;
                    }

                    $regular_items[] = $item;
                }

                $plan = $this->get_stripe_plan( $recurring_item );
                $customer = $this->get_stripe_customer( $payment->listing_id );

                if ( $regular_items ) {
                    $customer->account_balance = ( $regular_items[0]->amount * 100 );
                    $customer->save();
                }

                $response = $customer->subscriptions->create( array( 'plan' => $plan,
                                                                     'card' => $token,
                                                                     'metadata' => array( 'payment_id' => $payment->get_id() ) ) );
                $payment->set_data( 'recurring_id', $response->id );
            } else {
                $charge = Stripe_Charge::create( array(
                    'amount' => round( $payment->get_total() * 100, 0 ),
                    'currency' => strtolower( wpbdp_get_option( 'currency' ) ),
                    'card' => $token,
                    'description' => sprintf( 'BD Payment #%d', $payment->get_id() )
                ) );
            }

            $payment->set_status( WPBDP_Payment::STATUS_COMPLETED, WPBDP_Payment::HANDLER_GATEWAY );
        } catch( Stripe_CardError $e ) {
            $payment->add_error( __( 'Your payment was declined (incorrect credit card information).', 'wpbdp-stripe') );
            $payment->set_status( WPBDP_Payment::STATUS_REJECTED, WPBDP_Payment::HANDLER_GATEWAY );
        } catch( Exception $e ) {
            $payment->add_error( __( 'An unknown error occurred while your payment was being processed.', 'wpbdp-stripe') );
            $payment->set_status( WPBDP_Payment::STATUS_REJECTED, WPBDP_Payment::HANDLER_GATEWAY );
        }

        $payment->save();

        wp_redirect( $payment->get_redirect_url() );
    }

    public function process_generic( $action = '' ) {
        @header('HTTP/1.1 200 OK');

        require_once( plugin_dir_path( __FILE__ ) . 'vendors/stripe-php/lib/Stripe.php' );
        Stripe::setApiKey( wpbdp_get_option( 'payments-test-mode' ) ? wpbdp_get_option( 'stripe-test-secret-key' ) : wpbdp_get_option( 'stripe-live-secret-key' ) );

        $input = @file_get_contents( 'php://input' );
        $ev_json = json_decode( $input );

        if ( is_null( $ev_json ) || ! isset( $ev_json->id ) )
            die();

        try {
            $event = Stripe_Event::retrieve( $ev_json->id );
        } catch (Exception $e) { die(); }

        // Process subscription events.
        $inv = $event->data->object;
        if ( ! isset( $inv->subscription ) )
            die();

        global $wpdb;
        $data = unserialize( $wpdb->get_var( $wpdb->prepare(
            "SELECT recurring_data FROM {$wpdb->prefix}wpbdp_listing_fees WHERE recurring_id = %s", $inv->subscription
        ) ) );
        $payment = WPBDP_Payment::get( $data['payment_id'] );

        if ( ! $payment )
            die();

        switch ( $event->type ) {
            case 'customer.subscription.created':
                break;
            case 'invoice.payment_succeeded':
                $first_payment = $payment->is_first_recurring_payment();

                if ( $first_payment ) {
                } else {
                    $term_payment = $payment->generate_recurring_payment();
                    $term_payment->set_status( WPBDP_Payment::STATUS_COMPLETED, WPBDP_Payment::HANDLER_GATEWAY );
                    $term_payment->save();
                }

                break;
            case 'customer.subscription.deleted':
                // Remove 'recurring' bit from listing fee.
                $recurring_item = $payment->get_recurring_item();
                $listing->make_category_non_recurring( $recurring_item->rel_id_1 );
                break;
        }

        die();
    }

    private function get_stripe_plan( $recurring ) {
        $id = 'bd-fee-id' . $recurring->data['fee_id'] . '-d' . $recurring->data['fee_days'];

        try {
            $plan = Stripe_Plan::retrieve( $id );
        } catch ( Stripe_InvalidRequestError $e ) {
            // Try to create plan.
            $plan = Stripe_Plan::create( array(
                'amount' => round( $recurring->amount * 100, 0 ),
                'currency' => strtolower( wpbdp_get_option( 'currency' ) ),
                'interval' => 'day',
                'interval_count' => $recurring->data['fee_days'],
                'name' => __( 'Directory Renewal Fee', 'wpbdp-stripe' ),
                'id' => $id,
                'metadata' => $recurring->data
            ) );
        }

        if ( ! $plan )
            return null;

        return $plan->id;
    }

    private function get_stripe_customer( $listing_id ) {
        $post = get_post( $listing_id );
        $stripe_id = get_user_meta( $post->post_author, '_wpbdp_stripe_customer_id', true );
        $customer = null;

        if ( ! $stripe_id ) {
            // TODO: maybe store e-mail too?
            $customer = Stripe_Customer::create(array(
                'description' => 'BD User # ' . $post->post_author,
            ));
            update_user_meta( $post->post_author, '_wpbdp_stripe_customer_id', $customer->id );
        } else {
            try {
                $customer = Stripe_Customer::retrieve( $stripe_id );
            } catch ( Exception $e ) {
                delete_user_meta( $post->post_author, '_wpbdp_stripe_customer_id' );
                $customer = Stripe_Customer::create(array(
                    'description' => 'BD User # ' . $post->post_author,
                ));
                update_user_meta( $post->post_author, '_wpbdp_stripe_customer_id', $customer->id );
            }
        }

        return $customer;
    }

}
