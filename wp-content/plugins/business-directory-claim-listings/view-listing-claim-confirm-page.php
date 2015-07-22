<?php
require_once( WPBDP_PATH . 'core/class-view.php' );


class WPBDP_Listing_Claim_Confirm_Page extends WPBDP_View {

    private $claim = null;
    private $listing = null;

    private $errors = array();


    public function __construct( $claim_id = 0 ) {
        global $wpbdp_claim;

        if ( $this->claim = $wpbdp_claim->get_claim( $claim_id ) )
            $this->listing = WPBDP_Listing::get( $this->claim->listing_id );
    }

    public function dispatch() {
        if ( ! $this->claim || ! $this->listing || 'approved' != $this->claim->status )
            return wpbdp_render_msg( __( 'The claim link you followed is no longer valid because the claim expired or was withdrawn. Try to reclaim the listing again or contact the site administrator for assistance.',
                                     'wpbdp-claim-listings' ), 'error' );

        if ( ! is_user_logged_in() )
            return wpbdp_render( 'parts/login-required', array(), false );

        if ( get_current_user_id() != $this->claim->user_id )
            return;

        if ( $this->claim->payment_id > 0 ) {
            return $this->checkout();
        } elseif ( isset( $_POST['withdraw-claim'] ) && 1 == $_POST['withdraw-claim'] ) {
            return $this->withdraw_claim();
        } elseif ( isset( $_POST['go-to-checkout'] ) ) {
            return $this->fee_payment();
        } else {
            return $this->fee_selection();
        }
    }

    private function withdraw_claim() {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'withdraw claim ' . $this->claim->id ) )
            return wpbdp_render_msg( __( 'Invalid request.', 'wpbdp-claim-listings' ), 'error' );

        global $wpbdp_claim;
        $wpbdp_claim->withdraw_claim( $this->claim->id );

        $html  = '';
        $html .= wpbdp_render_msg( __( 'Your listing claim has been withdrawn.', 'wpbdp-claim-listings' ) );
        $html .= '<p>';

        if ( 'publish' == get_post_status( $this->claim->listing_id ) ) {
            $html .= '<a href="' . get_permalink( $this->claim->listing_id ) . '">' . __( 'Back to listing', 'wpbdp-claim-listings' ) . '</a> | ';
        }

        $html .= '<a href="' . wpbdp_get_page_link( 'main' ) . '"> ' . __( 'Return to directory.', 'wpbdp-claim-listings' ) . '</a>';
        $html .= '</p>';

        return $html;
    }

    /*function _add_special_fees( $html, $category ) {
        $html .= '<tr class="fee-option fee-id-removecategory">';
        $html .= '<td class="fee-selection">';
        $html .= '<input type="radio" id="wpbdp-fees-radio-removecategory" name="fees[' . $category->term_id . ']" value="removecategory" data-canrecur="0">';
        $html .= '</td>';
        $html .= '<td class="fee-label" colspan="4">';
        $html .= 'Remove listing from this category.';
        $html .= '</td>';
        $html .= '</tr>';
        return $html;
    }*/

    private function fee_selection() {
        $vars = array();
        $vars['claim'] = $this->claim;
        $vars['listing'] = $this->listing;
        $vars['fees'] = $this->obtain_fee_selection( $this->listing );
        //$vars['callback'] = array( &$this, '_add_special_fees' );
        $vars['validation_errors'] = $this->errors;

        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/claim-listing-confirm.tpl.php',
                                $vars );
    }

    private function obtain_fee_selection( &$listing ) {
        $fee_selection = array();
        $categories = $listing->get_categories( 'current' );

        foreach ( $categories as $cat ) {
            $options = wpbdp_get_fees_for_category( $cat->id );
            $fee_selection[ $cat->id ] = array( 'fee_id' => 0,
                                                'term' => get_term( $cat->id, WPBDP_CATEGORY_TAX ),
                                                'options' => $options );
        }

        return $fee_selection;
    }

    private function fee_payment() {
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'pay claim fees ' . $this->claim->id ) )
            return wpbdp_render_msg( __( 'Invalid request.', 'wpbdp-claim-listings' ), 'error' );

        if ( ! isset( $_POST['fees'] ) )
            return $this->fee_selection();

        global $wpdb;

        $categories_to_remove = 0;
        $validates = true;

        foreach ( $this->listing->get_categories( 'current' ) as $category ) {
            $selected_fee = wpbdp_getv( $_POST['fees'], $category->id, null );

            if ( null === $selected_fee ) {
                $this->errors[] = sprintf( _x( 'Please select a fee option for the "%s" category.', 'templates', 'WPBDM' ),
                                           esc_html( $category->name ) );
                $validates = false;
            } elseif ( 'removecategory' === $selected_fee ) {
                $categories_to_remove++;
            }
        }

        if ( $validates && ( $categories_to_remove == count( $_POST['fees'] ) ) ) {
            $this->errors[] = __( 'There is only one category available and we can\'t change the listing to not have a category. Contact the administrator of the site if you need assistance.', 'wpbdp-claim-listings' );
            $validates = false;
        }

        if ( ! $validates )
            return $this->fee_selection();

        $payment = new WPBDP_Payment( array( 'listing_id' => $this->claim->listing_id ) );
        $payment->add_item( 'listing-claim',
                            0.0,
                            sprintf( __( 'Listing Claim #%d', 'wpbdp-claim-listings' ), $this->claim->id ),
                            null,
                            $this->claim->id );
        foreach ( $_POST['fees'] as $category_id => $fee_id ) {
            if ( 'removecategory' == $fee_id )
                $payment->add_item( 'category-removal',
                                    0.0,
                                    sprintf( __( 'Removal of category "%s"', 'wpbdp-claim-listings' ),
                                             wpbdp_get_term_name( $category_id ) ),
                                    array(),
                                    $category_id );
            else
                $payment->add_category_fee_item( $category_id, wpbdp_get_fee( $fee_id ) );
        }
        $payment->save();

        $this->claim->payment_id = $payment->get_id();
        $wpdb->update( $wpdb->prefix . 'wpbdp_listing_claims',
                       array( 'payment_id' => $payment->get_id() ),
                       array( 'id' => $this->claim->id ) );

        return $this->checkout();
    }

    private function checkout() {
        $payment = WPBDP_Payment::get( $this->claim->payment_id );

        if ( ! $payment )
            return wpbdp_render_msg( __( 'Invalid request.', 'wpbdp-claim-listings' ), 'error' );

        require_once( WPBDP_PATH . 'core/view-checkout.php' );
        $checkout = new WPBDP_Checkout_Page( $payment );
        return $checkout->dispatch();
    }

}
