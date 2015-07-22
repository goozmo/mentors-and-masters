<?php
	
	
	
	
	
	

add_action( 'init', 'create_post_type' );
function create_post_type() {

	$collect = array("Daily Quote Rotator");
	
	foreach($collect as $value){
	
		$slug = str_replace(' ', '-', $value);
		$slug = str_replace("'", "", $slug );
		$slug = strtolower($slug);
		
		//echo $slug;
		
		register_post_type( 
			"$value",
			array(
				'labels'					=> array( 'name'=>__( "$value" ), 'singular_name'=>__( "$value" ) ),
				'public'					=> true,
				'exclude_from_search'		=> false,
				'publicly_queryable'		=> true,
				'show_ui'					=> true,
				'show_in_nav_menus'			=> false,
				'show_in_menu'				=> true,
				'show_in_admin_bar'			=> true,
				'menu_position'				=> NULL,
				'capability_type'			=> 'post',
				'capabilities'				=> array( 'edit_posts','read_posts','delete_posts' ),
				'map_meta_cap'				=> true,
				'hierarchical'				=> true,
				'supports'					=> NULL,
				'register_meta_box_cb'		=> NULL,
				'taxonomies'				=> array('category'),
				'has_archive'				=> "$slug",
				'permalink_epmask'			=> 'EP_PERMALINK',
				'rewrite'					=> array( 'slug'=> "$slug", 'with_front'=>false ),
				'query_var'					=> true,
				'can_export'				=> true,
			)
		);	
	}	
}

/**
 * Hide editor on specific pages.
 *
 */
/*
add_action( 'admin_init', 'hide_editor' );

function hide_editor() {
  $post_id = $_GET['post'] ? $_GET['post'] : $_POST['post_ID'] ;
  if( !isset( $post_id ) ) return;

  $post_type = get_post_type( $post_id );

  if( $post_type == 'dailyquoterotator' ){ 
    remove_post_type_support('dailyquoterotator', 'editor');
  }
}
*/

?>