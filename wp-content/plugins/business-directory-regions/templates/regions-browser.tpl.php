<?php
function _wpbdp_regions_browser( $regions ) {
	foreach ( $regions as &$r ) {
?>
<li class="region"><a href="<?php echo $r->link; ?>"><?php echo $r->name; ?></a></li>
<?php
	}
}
?>

<div class="wpbdp-regions-browser">

<?php if ( $breadcrumbs ): ?>
<div class="breadcrumbs">
	<?php echo $breadcrumbs; ?>
</div>
<?php endif; ?>

<?php if ( $regions ): ?>
	<?php if ( $current_region ): ?>	
		<p class="same-level"><a href="<?php echo $current_region->link; ?>"><?php _ex( 'Pick this level', 'shortcodes', 'wpbdp-regions' ); ?></a></p>
	<?php endif; ?>

	<?php if ( $field ): ?>
	<h3>Select a <?php echo strtolower( $field->get_label() ); ?>:</h3>
	<?php endif; ?>

	<?php if ( $alphabetically ): ?>
		<ul class="regions-list alphabetically">
			<?php foreach ( $regions as $l => &$l_regions ): ?>
			<li class="letter-regions">
				<h4><?php echo $l; ?></h4>
				<ul class="regions-list cf"><?php _wpbdp_regions_browser( $l_regions ); ?></ul>
			</li>
			<?php endforeach; ?>
		</ul>
	<?php else: ?>
		<ul class="regions-list">
			<?php _wpbdp_regions_browser( $regions ); ?>
		</ul>
	<?php endif; ?>
<?php else: ?>
	<p>No regions found.</p>
<?php endif; ?>

</div>