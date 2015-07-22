jQuery(function($) {

    var claim = {};

    claim.handleErrors = function( $form, prefix, errors ) {
        $.each( errors, function( k, msg ) {
            $form.find( '[name="' + prefix + '[' + k + ']"]' ).addClass( 'error' );
        } );
    };

    $( '#wpbdp-claim-listings-actions a' ).click(function(e) {
        return true;
    });

    $( '.wpbdp-claim-listings-dialog input[type="button"].cancel' ).click(function(e) {
        e.preventDefault();
        $(this).closest( 'form' )[0].reset();
        tb_remove();
    });

    // Request more info.
    $( '#wpbdp-claim-listings-requestinfo input[type="submit"]' ).click( function(e) {
        e.preventDefault();

        var $form = $(this).parents( 'form' );
        var data = $form.serialize();

        $form.find( '.error' ).removeClass( 'error' );

        $.post( $form.attr( 'action' ), data, function(res) {
            if ( ! res.success )
                return claim.handleErrors( $form, 'message', res.error );

            $form[0].reset();
            tb_remove();
            $('#wpbdp-claim-listings-admin').prepend( '<div class="updated message"><p>' + res.message + '</p></div>' );
        }, 'json' );
    });


    // {{{ Initial setup.

    $( '#wpbdp-claim-listings-initial-setup .button-primary' ).click(function() {
        var config = $('#wpbdp-claim-listings-initial-setup input[name="config"]:checked').val();
        var user = $( '#wpbdp-claim-listings-initial-setup select[name="user"]' ).val();

        $.post( ajaxurl, { 'action': 'wpbdp-claim-listings-setup', 'config': config, 'user': user }, function(res) {
            if ( ! res.success ) {
                $( '#wpbdp-claim-listings-initial-setup' ).html( '<p>' + res.error + '</p>' );
            } else {
                $( '#wpbdp-claim-listings-initial-setup' ).html( '<p>' + res.message + '</p>' ).addClass( 'done' );
            }
        }, 'json' );
    });

    $( '#wpbdp-claim-listings-initial-setup .button.dismiss' ).click(function() {
        $.post( ajaxurl, { 'action': 'wpbdp-claim-listings-setup', 'config': 'skip' }, function(res) {
            if ( res.success )
                $( '#wpbdp-claim-listings-initial-setup' ).fadeOut();
            else
                alert( res.error );
        }, 'json' );
    });

    // }}}

});
