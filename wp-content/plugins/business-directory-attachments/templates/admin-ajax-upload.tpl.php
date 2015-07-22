<?php iframe_header( '', true ); ?>
<script type="text/javascript">
parent.jQuery("#TB_window, #TB_iframeContent").width(400).height(300);
</script>

<div id="wpbdp-attachments-upload">
<?php if ( $success && $upload ): ?>
    <div class="updated success">
        <p><?php printf( __( 'The file %s was successfully uploaded and attached to the listing.', 'wpbdp-attachments' ),
                         '<i>' . esc_attr( $upload['file']['name'] ) . '</i>'
                        ); ?><br />
        <a href="#"><?php _e( 'Return to listing.', 'wpbdp-attachments' ); ?></a>
        </p>
    </div>

    <script type="text/javascript">
    jQuery(function($){
        var newUploadHTML = <?php echo json_encode( $upload_html ); ?>;
        parent.jQuery('#wpbdp-listing-attachments .listing-attachments .empty').hide();
        parent.jQuery('#wpbdp-listing-attachments .listing-attachments').append(newUploadHTML);

        $('#wpbdp-attachments-upload .updated.success a').click(function(e){
            e.preventDefault();
            parent.jQuery('#TB_closeWindowButton').click();
        });
    });
    </script>
<?php else: ?>
    <h1><?php _e( 'Add Listing Attachment', 'wpbdp-attachments' ); ?></h1>

    <?php if ( $errors ): ?>
    <div class="error">
        <p><?php echo implode( '<br />&#149;', $errors ); ?></p>
    </div>
    <?php endif; ?>

    <form action="" method="post" enctype="multipart/form-data">
        <input type="hidden" name="listing_id" value="<?php echo intval( $_GET['listing_id'] ); ?>" />
        <input type="hidden" name="MAX_FILE_SIZE" value="1073741824" />
        <p>
            <label for="wpbdp-attachments-upload-file"><?php _e( 'Attachment:', 'wpbdp-attachments' ); ?></label>
            <input type="file" name="file" id="wpbdp-attachments-upload-file" />
        </p>
        <p>
            <label><?php _e( 'Description:', 'wpbdp-attachments' ); ?></label>
            <input type="text" name="description" id="wpbdp-attachments-upload-description" value="<?php echo isset( $_POST['description'] ) ? esc_attr( $_POST['description'] ) : ''; ?>" />
        </p>
        <p>
            <input type="submit" class="button button-primary" value="<?php _e( 'Upload File', 'wpbdp-attachments' ); ?>" disabled="disabled" />
            <input type="button" class="button button-secondary" value="<?php _e( 'Cancel', 'wpbdp-attachments' ); ?>"
                   onclick="return parent.jQuery('#TB_closeWindowButton').click();" />
        </p>
    </form>
<?php endif; ?>    
</div>
<?php iframe_footer(); ?>