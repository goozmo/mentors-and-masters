jQuery(function($) {

    $( 'a.claim-listing-link' ).click(function(e) {
        e.preventDefault();

        $( '.wpbdp-claim-listings' ).toggleClass('open');
    });

    $( 'form#wpbdp-claim-listings-form input[type="reset"]' ).click(function() {
        $( '.wpbdp-claim-listings' ).removeClass('open');
    });

    $( 'form#wpbdp-claim-listings-form' ).submit(function(e) {
        e.preventDefault();

        $( '.wpbdp-claim-listings .field.error' ).removeClass('error');
        $( '#wpbdp-claim-listings-message' ).fadeOut('fast', function() { $(this).removeClass('error') });

        $.post( $(this).attr('data-action'), $(this).serialize(), function(res) {
            if ( ! res.success ) {
                if ( res.error )
                    $( '#wpbdp-claim-listings-message' ).addClass('error').html( res.error ).fadeIn();

                return;
            }

            $( 'form#wpbdp-claim-listings-form' ).fadeOut( 'fast', function() {
                $( '#wpbdp-claim-listings-message' ).html( res.message ).fadeIn();
            } );
//            $( '.wpbdp-claim-listings' ).removeClass( 'open' );
        }, 'json' );
    });

});
