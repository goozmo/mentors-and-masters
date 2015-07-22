<?php
add_action( 'init', 'cm_events_create_post_type' );
function cm_events_create_post_type() {

	$collect = array("CM Events");
	
	foreach($collect as $value){
	
		$slug = str_replace(' ', '-', $value);
		$slug = str_replace("'", "", $slug );
		$slug = strtolower($slug);
		
		//echo $slug;
		
		register_post_type( 
			"$value",
			array(
				'labels'=>array(
					'name'=>__( "$value" ),
					'singular_name'=>__( "$value" )
				),
				'public'=>true,
				'exclude_from_search'=>false,
				'publicly_queryable'=>true,
				'show_ui'=>true,
				'show_in_nav_menus'=>false,
				'show_in_menu'=>true,
				'show_in_admin_bar'=>true,
				'menu_position'=>NULL,
				'capability_type'=>'post',
				'capabilities'=>array('edit_posts','read_posts','delete_posts'),
				'map_meta_cap'=>true,
				'hierarchical'=>true,
				'supports'=> array( 
					'title',
					'editor',
					'thumbnail',
					'excerpt',
					'revisions',
					'page-attributes',
				 ),
				'register_meta_box_cb'=>NULL,
				'taxonomies'=>array( 'category' ),
				'has_archive'=>"$slug",
				'permalink_epmask'=>'EP_PERMALINK',
				'rewrite'=>array(
					'slug'=> "$slug",
					'with_front'=>false
				),
				'query_var'=>true,
				'can_export'=>true
			)
		);	
	}	
}
?>