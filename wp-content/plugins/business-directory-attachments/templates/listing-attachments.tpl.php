<div class="wpbdp-listing-form-attachments">

<h3><?php echo wpbdp_get_option( 'attachments-header' ); ?></h3>

<?php if ( $errors ): ?>
	<ul class="validation-errors">
		<?php foreach ( $errors as &$error ): ?>
		<li><?php echo $error; ?></li>
		<?php endforeach; ?>
	</ul>
<?php endif; ?>

<?php if ( $status ): ?>
<div class="wpbdp-msg status"><?php echo $status; ?></div>
<?php endif; ?>

<?php if ( $state->extra['attachments'] ): ?>
<div class="attachments">
    <?php foreach ( $state->extra['attachments'] as &$attachment ): ?>
    <div class="attachment">
        <div class="actions">
            <a href="#" class="delete" data-attachment-key="<?php echo $attachment['key']; ?>"><?php _e( 'Delete', 'wpbdp-attachments' ); ?></a>
        </div>
        <div class="file-info">
            <a href="<?php echo esc_url( $attachment['url'] ); ?>" class="url"><?php echo basename( $attachment['path'] ); ?></a> <span class="filesize">(<?php echo trim( size_format( filesize( realpath( $attachment['path'] ) ), 2 ) ); ?>)</span>
        </div>
        <?php if ( $attachment['description'] ): ?><div class="description"><?php echo esc_html( $attachment['description'] ); ?></div><?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="attachments-new">
    <dl class="info">
        <dt class="limit"><?php _e( 'Attachments limit: ', 'wpbdp-attachments' ); ?></dt>
        <dd class="limit">
            <?php echo $config['limit']; ?> <span class="remaining"><?php printf( __( '(%d remaining)', 'wpbdp-attachments' ), max( 0, $config['limit'] - count( $state->extra['attachments'] ) ) ); ?></span>
        </dd>
        <dt class="filesize"><?php _e( 'Max. upload size: ', 'wpbdp-attachments' ); ?></dt>
        <dd class="filesize"><?php echo size_format( $config['filesize'], 2 ); ?></dd>
        <dt class="extensions"><?php _e( 'Supported file extensions: ', 'wpbdp-attachments' ); ?></dt>
        <dd class="extensions"><?php echo strtoupper(join( ', ', $config['extensions'] ) ); ?></dd>
    </dl>

    <div class="attachment-data">
        <h4><?php _e( 'Add Attachment', 'wpbdp-attachments' ); ?></h4>
    	<div class="attachment-file">
    		<label><?php _e( 'Attachment:', 'wpbdp-attachments' ); ?></label>
    		<input type="file" class="attachment-file" name="upload_file" />
    	</div>
    	<div class="attachment-description">
    		<label><?php _e( 'Description:', 'wpbdp-attachments' ); ?></label>
    		<input type="text" class="attachment-description" name="upload_description" />
    	</div>
    	<div class="attachment-actions">
    		<input type="submit" class="submit" name="attachment-upload" value="<?php _e( 'Upload File', 'wpbdp-attachments' ); ?>" disabled="disabled" />
    	</div>
    </div>

    <br style="clear: both;" />

</div>

</div>