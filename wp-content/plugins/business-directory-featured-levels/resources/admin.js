jQuery(function($){

    $('form#wpbdp-restrictions-enable input[name="enable_restrictions"]').change(function(e){
        $(this).parents('form').submit();
    });

    $( 'table.wpbdp-restrictions-table a.toggle-link' ).click(function(e){
        e.preventDefault();
        $(this).parents('table').find('tbody').toggle();
    });

    $('table.wpbdp-restrictions-table .advanced-controls a.toggle').click(function(e) {
    	e.preventDefault();
    	$(this).siblings('.controls').toggle();

    	if ($(this).siblings('.controls').is(':visible')) {
    		$(this).text( $(this).text().replace('+', '-'));
    	} else {
    		$(this).text( $(this).text().replace('-', '+'));
    	}
    });

    $('table.wpbdp-restrictions-table .enabledcol input[type="checkbox"]').click(function(e) {
    	if ($(this).is(':checked')) {
    		$(this).parents('tr').next('tr.advanced-controls').show();
    	} else {
			$(this).parents('tr').next('tr.advanced-controls').hide();
    	}
    });

    // Attachments.
    $('table.wpbdp-restrictions-table .advanced-controls input.toggle-custom').change(function(e) {
    	if ( $(this).val() == 'custom' ) {
    		$(this).parents('.advanced-controls').find('.custom-settings').show();
    	} else {
			$(this).parents('.advanced-controls').find('.custom-settings').hide();
    	}
    });

});