<div class="wpbdp-note">
<p><?php
    _e( 'Specify which fields should be visible/editable for listings depending on their featured level status or selected fee plan. Checked fields will be visible for that fee plan or featured level. For a better user experience, we recommend that higher levels should have access to more than lower levels.',
        'wpbdp-featured-levels' );
?></p>
</div>

<div class="wpbdp-note">
<p><?php _e( 'The following fields cannot be restricted because of WordPress requirements for displaying a custom post type:', 'wpbdp-featured-levels' ); ?></p>
<p style="padding:7px;">
    <?php foreach ( wpbdp_get_form_fields() as $f ): ?>
        <?php if ( in_array( $f->get_association(), array( 'title', 'content', 'category' ), true ) ): ?>
            <span class="tag"><?php echo esc_html( $f->get_label() ); ?></span>
        <?php endif; ?>
    <?php endforeach; ?>
</p>
<p><?php _e('They must always be displayed and therefore, Featured levels cannot restrict them. They have been disabled in the fields below.', 'wpbdp-featured-levels'); ?></p>
</div>

<?php if ( $level_config ): ?>
<table class="wpbdp-restrictions-table">
    <thead>
        <tr>
            <th colspan="2" class="capability-name">
                <a href="#" class="toggle-link"><?php _e( 'Fields by listing level', 'wpbdp-featured-levels' ); ?></a>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="subheader">
                <?php _e( 'Listing Level', 'wpbdp-featured-levels' ); ?>
            </td>
            <td class="subheader">
                <?php _e( 'Form Fields', 'wpbdp-featured-levels' ); ?>
            </td>
        </tr>
        <?php foreach ( $level_config as $l ): ?>
            <tr>
                <td><?php echo esc_html( $l->level->name ); ?></td>
                <td>
                    <?php foreach ( wpbdp_get_form_fields() as $f ): ?>
                        <label>
                            <input type="checkbox" name="levels[<?php echo $l->level->id; ?>][]" value="<?php echo $f->get_id(); ?>" <?php echo ( in_array( $f->get_association(), array( 'title', 'content', 'category' ), true ) ) ? 'checked="checked" disabled="disabled"' : ''; ?> <?php echo ( in_array( $f->get_id(), $l->fields, true ) ) ? 'checked="checked"' : ''; ?> /> <?php echo esc_html( $f->get_label() ); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ( $fees_config ): ?>
<table class="wpbdp-restrictions-table">
    <thead>
        <tr>
            <th colspan="2" class="capability-name">
                <a href="#" class="toggle-link"><?php _e( 'Fields by listing fee', 'wpbdp-featured-levels' ); ?></a>
            </th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="subheader">
                <?php _e( 'Fee', 'wpbdp-featured-levels' ); ?>
            </td>
            <td class="subheader">
                <?php _e( 'Form Fields', 'wpbdp-featured-levels' ); ?>
            </td>
        </tr>
        <?php foreach ( $fees_config as $feec ): ?>
            <tr>
                <td><?php echo esc_html( $feec->fee->label ); ?></td>
                <td>
                    <?php foreach ( wpbdp_get_form_fields() as $f ): ?>
                        <label>
                            <input type="checkbox" name="fees[<?php echo $feec->fee->id; ?>][]" value="<?php echo $f->get_id(); ?>" <?php echo ( in_array( $f->get_association(), array( 'title', 'content', 'category' ), true ) ) ? 'checked="checked" disabled="disabled"' : ''; ?> <?php echo ( in_array( $f->get_id(), $feec->fields, true ) ) ? 'checked="checked"' : ''; ?> /> <?php echo esc_html( $f->get_label() ); ?>
                        </label>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>        
    </tbody>
</table>
<?php endif; ?>