jQuery(function($) {
    var $form = $( 'form#wpbdp-stripe-form ' );

    if ( 0 == $form.length )
        return;

    Stripe.setPublishableKey( $( 'input[name="_stripeKey"]', $form ).val() );

    $form.submit(function(e) {
        e.preventDefault();

        $( '.stripe-errors', $form ).hide();
        $( 'input.submit', $form ).prop( 'disabled', true );

        Stripe.card.createToken( $form, function( status, response ) {
            if ( response.error ) {
                $( '.stripe-errors', $form ).text( response.error.message ).show();
                $( 'input.submit', $form ).prop( 'disabled', false );
                return;
            }

            var token = response.id;
            $( 'input[name="stripeToken"]', $form ).val( token );
            $form[0].submit();
        });
    });

});
