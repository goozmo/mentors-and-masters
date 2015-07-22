<table class="form-table attachments-configuration">
    <tbody>
        <!-- Use fee-specific config? -->
        <tr>
            <th scope="row">
                <label><?php _e( 'Override defaults for this fee?', 'wpbdp-attachments' ); ?></label>
            </th>
            <td>
                <input type="checkbox" name="attachments[mode]" value="custom" class="toggle-mode" <?php echo $config['mode'] == 'custom' ? 'checked="checked"' : ''; ?> />
            </td>
        </tr>
        <!--<tr>
            <th scope="row">
                <label><?php _e( 'Allow attachments on this fee plan?', 'wpbdp-attachments' ); ?></label>
            </th>
            <td>
                <input type="checkbox" name="attachments[enabled]" value="1" />
            </td>
        </tr>-->
        <tr class="form-required custom-mode" style="<?php echo $config['mode'] == 'custom' ? '' : 'display: none;'; ?>">
            <th scope="row">
                <label><?php _e( 'Maximum number of attachments per listing', 'wpbdp-attachments' ); ?> <span class="description">(<?php _e( 'required', 'admin', 'WPBDM' ); ?>)</span></label>
            </th>
            <td>
                <input type="text" name="attachments[count]" size="5" value="<?php echo $config['count']; ?>" />
            </td>
        </tr>
        <tr class="form-required custom-mode" style="<?php echo $config['mode'] == 'custom' ? '' : 'display: none;'; ?>">
            <th scope="row">
                <label><?php _e( 'Maximum attachment size (in KB)', 'wpbdp-attachments' ); ?> <span class="description">(<?php _e( 'required', 'admin', 'WPBDM' ); ?>)</span></label>
            </th>
            <td>
                <input type="text" name="attachments[maxsize]" size="5" value="<?php echo $config['maxsize']; ?>" />
            </td>
        </tr>        
    </tbody>
</table>