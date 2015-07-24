<?php 

global $avia_config; 


//allows you to modify the search parameters. for example bbpress search_id needs to be 'bbp_search' instead of 's'. you can also deactivate ajax search by setting ajax_disable to true
$search_params = apply_filters('avf_frontend_search_form_param', array(
	
	'placeholder'  	=> __('Enter Specialty, City or Keyword Combination','avia_framework'),
	'search_id'	   	=> 's',
	'form_action'	=> home_url( '/' ),
	'ajax_disable'	=> false
));

$disable_ajax = $search_params['ajax_disable'] == false ? "" : "av_disable_ajax_search";

$icon  = av_icon_char('search');
$class = av_icon_class('search');
?>

<?php 
$placeholder = is_front_page() === true ? __('Enter Specialty, City or Keyword Combination','avia_framework') : __( 'Search Directory' );
?>

<form action="/explore/business-directory/?action=search&dosrch=1" id="wpbdmsearchform" method="get" class="wpbdp-search-form">
	<div>
		<input type="hidden" name="action" value="search">
        <input type="hidden" name="page_id" value="4">
        <input type="hidden" name="dosrch" value="1">
		<input id="intextbox" maxlength="150" name="q" size="20" type="text" value="" placeholder="<?php echo $placeholder; ?>">
		<input type="submit" value="search" id="searchsubmit" class="button <?php echo $class; ?>" />
		<?php 
		
		// allows to add aditional form fields to modify the query (eg add an input with name "post_type" and value "page" to search for pages only)
		do_action('ava_frontend_search_form'); 
		
		?>
		<ul class="search-sub">
			<li><a href="/business-directory/?action=search">Advanced Search</a></li>
		</ul>
	</div>
</form>