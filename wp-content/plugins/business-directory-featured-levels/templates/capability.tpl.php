<?php $i = 0; ?>

<table class="wpbdp-restrictions-table">
    <thead>
        <tr>
            <th colspan="2" class="capability-name">
                <a href="#" class="toggle-link">
                    <?php echo sprintf( __( 'Allow access to "%s" for the following levels/fee plans',
                                            'wpbdp-featured-levels' ), esc_html( $capability->name ) ); ?>
                </a>
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
                    <?php _e( 'Allow access?', 'wpbdp-featured-levels' ); ?>
                </td>
            </tr>
            <?php foreach ( $level_config as &$l ): $i++; ?>
            <tr class="<?php echo $i % 2 == 0 ? 'alternate' : ''; ?>">
                <td class="item-name">
                    <?php echo esc_html( $l->level->name ); ?>
                </td>
                <td class="enabledcol">
                    <input type="checkbox" value="1" name="levels[<?php echo $l->level->id; ?>][<?php echo $capability->id; ?>]" <?php echo $l->enabled ? 'checked="checked"' : ''; ?> />
                </td>
            </tr>
            <?php if ( $advanced_controls ): ?>
            <tr class="advanced-controls" style="<?php echo $l->enabled ? '' : 'display: none;'; ?>">
                <td colspan="2">
                    <a href="#" class="toggle"><?php _e( '+ Show Advanced Configuration', 'wpbdp-featured-levels' ); ?></a>
                    <div class="controls" style="display: none;">
                        <?php call_user_func_array( $advanced_controls, array( 'levels', &$capability, &$l ) ); ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ( $fees_config ): ?>
            <tr>
                <td class="subheader">
                    <?php _e( 'Listing Fee', 'wpbdp-featured-levels' ); ?>
                </td>
                <td class="subheader">
                    <?php _e( 'Allow access?', 'wpbdp-featured-levels' ); ?>
                </td>               
            </tr>
            <?php foreach ( $fees_config as &$f ): $i++; ?>
            <tr class="<?php echo $i % 2 == 0 ? 'alternate' : ''; ?>">
                <td class="item-name">
                    <?php echo esc_html( $f->fee->label ); ?>
                </td>
                <td class="enabledcol">
                    <input type="checkbox" value="1" name="fees[<?php echo $f->fee->id; ?>][<?php echo $capability->id; ?>]" <?php echo $f->enabled ? 'checked="checked"' : ''; ?> />
                </td>
            </tr>
            <?php if ( $advanced_controls ): ?>
            <tr class="advanced-controls" style="<?php echo $f->enabled ? '' : 'display: none;'; ?>">
                <td colspan="2">
                    <a href="#" class="toggle"><?php _e( '+ Show Advanced Configuration', 'wpbdp-featured-levels' ); ?></a>
                    <div class="controls" style="display: none;">
                        <?php call_user_func_array( $advanced_controls, array( 'fees', &$capability, &$f ) ); ?>
                    </div>
                </td>
            </tr>
            <?php endif; ?>            
            <?php endforeach; ?>
        <?php endif; ?>     
    </tbody>
</table>