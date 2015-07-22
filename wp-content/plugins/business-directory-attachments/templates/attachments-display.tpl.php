<div class="wpbdp-listing-attachments field-value">
	<label><h3><?php echo wpbdp_get_option( 'attachments-header' ); ?></h3></label>
	<span class="value">
		<ul class="attachments <?php echo wpbdp_get_option( 'attachments-icons' ) ? 'with-icons' : ''; ?>">
		<?php foreach ( $attachments as &$attachment ): ?>
			<li class="attachment">
                <?php if ( isset( $attachment['icon'] ) && $attachment['icon'] ): ?>
                <img src="<?php echo $attachment['icon']; ?>" class="attachment-icon" />
                <?php endif; ?>
				<a href="<?php echo esc_url( $attachment['url'] ); ?>" target="_blank"><?php echo esc_attr( $attachment['file']['name'] ); ?></a> (<span class="filesize"><?php echo size_format( filesize( $attachment['path'] ), 2 ); ?></span>)<br />
				<?php if ( $attachment['description'] ): ?>
				<span class="description"><?php echo esc_html( $attachment['description'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
		</ul>
	</span>
</div>
