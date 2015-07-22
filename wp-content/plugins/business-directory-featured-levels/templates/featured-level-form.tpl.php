<?php
    echo wpbdp_admin_header(__('Add Featured Level', 'wpbdp-featured-levels'));
?>
<?php wpbdp_admin_notices(); ?>

<?php
$post_values = isset($_POST['level']) ? $_POST['level'] : array();
$level = isset($level) ? $level : null;
?>

<form id="wpbdp-featured-level-form" action="" method="POST">
    <?php if (isset($level)): ?>
    <input type="hidden" name="level[id]" value="<?php echo $level->id; ?>" />
    <input type="hidden" name="level[weight]" value="<?php echo $level->weight; ?>" />
    <?php endif; ?>
    <table class="form-table">
        <tbody>
            <tr class="form-field form-required">
                <th scope="row">
                    <label for="wpbdp-featured-level-form-name"> <?php _e('Level Name', 'wpbdp-featured-levels'); ?> <span class="description">(required)</span></label>
                </th>
                <td>
                    <input name="level[name]"
                           id="wpbdp-featured-level-form-name"
                           type="text"
                           aria-required="true"
                           value="<?php echo wpbdp_getv($post_values, 'name', $level ? $level->name : ''); ?>" />
                </td>
            </tr>
            <tr class="form-required">
                <th scope="row">
                    <label for="wpbdp-featured-level-form-cost"> <?php _e('Upgrade Cost', 'wpbdp-featured-levels'); ?> <span class="description">(required)</span></label>
                </th>
                <td>
                    <?php echo wpbdp_get_option( 'currency-symbol' ); ?>
                    <input name="level[cost]"
                           type="text"
                           aria-required="true"
                           id="wpbdp-featured-level-form-cost"
                           value="<?php echo $level && $level->id == 'normal' ? '0.0' : esc_attr( wpbdp_getv( $post_values, 'cost', $level ? $level->cost : '' ) ); ?>" 
                           <?php echo $level && $level->id == 'normal' ? ' disabled="disabled"' : ''; ?> />
                </td>
            </tr>             
            <tr class="form-field form-required">
                <th scope="row">
                    <label for="wpbdp-featured-level-form-description"> <?php _e('Level Description', 'wpbdp-featured-levels'); ?> </label>
                </th>
                <td>
                    <textarea name="level[description]" id="wpbdp-featured-level-form-description"><?php echo esc_textarea( wpbdp_getv( $post_values, 'description', $level ? $level->description : '' ) ); ?></textarea>
                </td>
            </tr>
    </table>

    <h3><?php _e('Custom Form Fields', 'wpbdp-featured-levels'); ?></h3>
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="wpbdp-featured-level-form-name"> <?php _e('Fields for this level', 'wpbdp-featured-levels'); ?> <span class="description">(required)</span></label>
                </th>
                <td>
                    <?php $level_fields = wpbdp_getv( $post_values, 'form_fields', $level ? $level->form_fields : array() );  ?>
                    <?php foreach ( wpbdp_get_form_fields() as $field ): ?>
                        <label>
                            <input type="checkbox" name="level[form_fields][]" value="<?php echo $field->get_id(); ?>"
                                <?php echo ( in_array( $field->get_association(), array( 'title', 'category', 'content'  ), true ) || in_array( $field->get_id(), $level_fields, true ) ) ? ' checked="checked"' : ''; ?> />
                            <?php echo esc_attr( $field->get_label() ); ?>
                        </label><br />
                    <?php endforeach; ?>
                </td>
            </tr>
        </tbody>
    </table>

    <?php if ($level): ?>
        <?php echo submit_button(_x('Update Level', 'form-fields admin', 'wpbdp-featured-levels')); ?>
    <?php else: ?>
        <?php echo submit_button(_x('Add Level', 'form-fields admin', 'wpbdp-featured-levels')); ?>
    <?php endif; ?>
</form>

<?php
    echo wpbdp_admin_footer();
?>