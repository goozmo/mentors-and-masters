jQuery(function($) {
    $('.wpbdp-listing-form-attachments .attachment .actions a.delete, #wpbdp-listing-attachments a.delete-link').click(function(e) {
        e.preventDefault();

        var $form = $(this).parents('form');
        $form.append($('<input type="hidden" name="attachment-remove" value="' + $(this).attr('data-attachment-key')  + '" />'));
        $form.submit();
    });

    $('.wpbdp-submit-page .wpbdp-listing-form-attachments input[type="file"].attachment-file').change(function() {
        var $form = $(this).parents('form');
        var value = $(this).val();

        if (value) {
            $form.find('input[name="attachment-upload"]').removeAttr('disabled');
        } else {
            $form.find('input[name="attachment-upload"]').attr('disabled', 'disabled');
        }       
    });

    /*
     * Admin.
     */
    $('.wp-admin #wpbdp-attachments-upload input#wpbdp-attachments-upload-file').change(function() {
       var $form = $(this).parents('form');
       var value = $(this).val();

        if (value) {
            $form.find('input[type="submit"]').removeAttr('disabled');
        } else {
            $form.find('input[type="submit"]').attr('disabled', 'disabled');
        }
    });

    

    $('.wpbdp-admin #wpbdp-fee-form .attachments-configuration .toggle-mode').click(function() {
        if ( $(this).is(':checked') ) {
            $('.wpbdp-admin #wpbdp-fee-form .attachments-configuration .custom-mode').show();
        } else {
            $('.wpbdp-admin #wpbdp-fee-form .attachments-configuration .custom-mode').hide();
        }
    });

});