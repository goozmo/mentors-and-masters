<div class="wpbdp-note">
    <p><?php _e( 'Set a field to "0" for Unlimited characters, otherwise enter a specific character limit to be enforced for the "Long Description" (content) field at that level.',
          'wpbdp-featured-levels' ); ?>
    </p>
</div>

<?php foreach ( $char_limits as $char_limit ): ?>
    <?php echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'character-limit.tpl.php',
                                  array( 'field' => $char_limit->field,
                                         'level_config' => $char_limit->level_config,
                                         'fees_config' => $char_limit->fees_config ) ); ?>
<?php endforeach; ?>

<table class="wpbdp-restrictions-table">
    <thead>
        <tr>
            <th class="capability-name">
                <a href="#" class="toggle-link"><?php _e( 'Misc. Settings', 'wpbdp-featured-levels' ); ?></a>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>
                <label>
                    <input type="checkbox" name="nofollow_on_featured" value="1" <?php echo $nofollow_on_featured ? 'checked="checked"' : ''; ?> />
                    <?php _ex( 'Make featured listings links crawable by search engines (remove nofollow)?', 'wpbdp-featured-levels' ); ?>
                </label>
                <span class="description"><?php _ex( 'This setting overrides any field-specific configuration.', 'wpbdp-featured-levels' ); ?></span>
            </td>
        </tr>
    </tbody>
</table>
