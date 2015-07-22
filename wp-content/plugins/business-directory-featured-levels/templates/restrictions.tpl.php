<?php
    echo wpbdp_admin_header();
    echo wpbdp_admin_notices();
?>

<div class="wpbdp-note"><p>
<?php _e( 'Featured Levels allows you to restrict access to certain features for fee plans and featured listing levels. You can configure what plans have access to your features here.',
          'wpbdp-featured-levels' ); ?>
</p></div>

<form id="wpbdp-restrictions-enable" action="" method="POST">
    <input type="hidden" name="action" value="enable_restrictions" />
    <label>
        <input type="checkbox" name="enable_restrictions" value="1" <?php echo get_option( 'wpbdp-restrictions-enabled' ) ? ' checked="checked"' : '';  ?> > <?php _e( 'Enable feature restrictions?', 'wpbdp-featured-levels' ); ?>
    </label>
</form>

<?php if ( get_option( 'wpbdp-restrictions-enabled' ) ): ?>
<div id="wpbdp-restrictions-settings">

<h3 class="nav-tab-wrapper">
    <?php foreach ( $groups as $g ): ?>
    <a class="nav-tab <?php echo $g->slug == wpbdp_getv($_REQUEST, 'group', 'premium') ? 'nav-tab-active': ''; ?>"
       href="<?php echo add_query_arg('group', $g->slug, remove_query_arg('settings-updated')); ?>">
       <?php echo $g->name; ?>
    </a>
    <?php endforeach; ?>
</h3>

<?php if ( $settings_form ): ?>
    <form method="POST">
        <input type="hidden" name="action" value="save-group" />
        <input type="hidden" name="group" value="<?php echo $current_group; ?>" />
        <?php echo $settings_form; ?>
        <?php echo submit_button(); ?>
    </form>
<?php else: ?>
    <?php _e( 'No settings are currently available.', 'wpbdp-featured-levels' ); ?>
<?php endif; ?>

</div>
<?php endif; ?>

<?php
    echo wpbdp_admin_footer();
?>