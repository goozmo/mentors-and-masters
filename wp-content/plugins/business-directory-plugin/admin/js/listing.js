var wpbdp = window.wpbdp || {};
var admin = wpbdp.admin = wpbdp.admin || {};

( function( $ ) {
    var listing = admin.listing = admin.listing || {};

    var images = listing.images = wpbdp.admin.listing.images = {
        init: function() {
            var t = this;

            // Handle image deletes.
            $( '#wpbdp-uploaded-images' ).delegate( '.delete-image', 'click', function( e ) {
                e.preventDefault();
                $.post( $( this ).attr( 'data-action' ), {}, function( res ) {
                    if ( ! res.success )
                        return;

                    $( '#wpbdp-uploaded-images .wpbdp-image[data-imageid="' + res.data.imageId + '"]' ).remove();

                    if ( 0 == $( '#wpbdp-uploaded-images .wpbdp-image' ).length )
                        $( '#no-images-message' ).show();
                }, 'json' );
            } );

            // Image upload.
            wpbdp.dnd.setup( $( '#image-upload-dnd-area' ), {
                done: function( res ) {
                    $( '#no-images-message' ).hide();
                    $( '#wpbdp-uploaded-images' ).append( res.data.html );
                }
            } );
        }
    };

    // Initialization.
    $( document ).ready( function() {
        images.init();
    } );

} )( jQuery );
