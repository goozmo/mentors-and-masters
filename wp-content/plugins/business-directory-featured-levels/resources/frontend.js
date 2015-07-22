jQuery(function($){

    var strlen = function( text ) {
        var count = 0;

        for ( var i = 0; i < text.length; i++ ) {
            if ( "\n" == text[i] || "\r" == text[i] ) {
                continue;
            }

            count++;
        }

        return count;
    };

    var truncate = function ( text, limit ) {
        var res = "";
        var i = 0;
        var length = 0;

        while ( true ) {
            if ( length == limit || i == text.length ) {
                break;
            }

            res += text[i];

            if ( "\n" != text[i] && "\r" != text[i] ) {
                length++;
            }

            i++;
        }

        //console.log( 'truncate', res );

        return res;
    };
	
	$( '.wpbdp-form-field[data-limit-characters="1"]' ).each(function(i,e) {
		var $e = $(e);
		var limit = parseInt( $(e).attr('data-characters-limit'), 10 );
		var $placeholder = $e.find('.characters-left-placeholder');

		$e.find('textarea, input[type="text"]').bind('keyup keydown', function() {
			var text = $(this).val();
			var textLength = strlen( text );

			if ( limit > 0 ) {
				if ( textLength > limit ) {
				    text = truncate( text, limit );
				    textLength = strlen( text );
				}

				$placeholder.text( limit - textLength );
				$(this).val( text );
			}
		}).trigger('keyup');

	});
});
