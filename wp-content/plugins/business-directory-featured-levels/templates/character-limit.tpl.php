<table class="wpbdp-restrictions-table">
    <thead>
        <tr>
            <th colspan="2" class="capability-name">
                <a href="#" class="toggle-link"><?php printf( _x( '"%s" field character limit', 'wpbdp-featured-levels' ), esc_attr( $field->get_label() ) ); ?></a>
            </th>
        </tr>
    </thead>
    <tbody>
    <?php if ( $level_config ): ?>
        <tr>
            <td class="subheader">
                <?php _e( 'Listing Level', 'wpbdp-featured-levels' ); ?>
            </td>
            <td class="subheader">
                <?php _e( 'Limit', 'wpbdp-featured-levels' ); ?>
            </td>
        </tr>
        <?php foreach ( $level_config as $lc ): ?>
            <tr>
                <td><?php echo esc_html( $lc->level->name ); ?></td>
                <td>
                    <input type="text" name="char_limit[<?php echo $field->get_id(); ?>][levels][<?php echo $lc->level->id; ?>]" value="<?php echo intval( $lc->char_limit ); ?>" size="2" />
                </td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if ( $fees_config ): ?>
        <tr>
            <td class="subheader">
                <?php _e( 'Listing Fee', 'wpbdp-featured-levels' ); ?>
            </td>
            <td class="subheader">
                <?php _e( 'Limit', 'wpbdp-featured-levels' ); ?>
            </td>
        </tr>
        <?php foreach ( $fees_config as $fc ): ?>
            <tr>
                <td><?php echo esc_html( $fc->fee->label ); ?></td>
                <td>
                    <input type="text" name="char_limit[<?php echo $field->get_id(); ?>][fees][<?php echo $fc->fee->id; ?>]" value="<?php echo intval( $fc->char_limit ); ?>" size="2" />
                </td>
            </tr>    
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

