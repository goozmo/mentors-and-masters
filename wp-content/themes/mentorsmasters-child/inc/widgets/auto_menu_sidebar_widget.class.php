<?php

class auto_menu_sidebar_widget extends WP_Widget {
 
	function __construct() {
		
		parent::__construct(

			// Identifier
			'auto_menu_sidebar_widget',

			// Name
			__('CM Auto Menu', 'auto_menu_sidebar_widget_domain'),

			// Description
			array(
				'description' => __( 'Automated menu. Generates a menu based on page hierarchy without having to create yet another WP menu', 				'auto_menu_sidebar_widget_domain' ), )
		);			
	}

	public function widget( $args, $instance ) {
		
		$parent_id = $instance['page'];
		//$parent_id = get_post_ancestors($instance['page']);
		//$_i = count($parent_id)-1;
		//$parent_id = $parent_id[$_i];
		
		$menu_item_insts =array();
		
		$parent_post_data = get_post($parent_id);
		$guid = get_permalink( $parent_id );
		$link = "<li class='cm-menu-t1'><a href='".$guid."'>".$parent_post_data->post_title."</a></li>";
		array_push($menu_item_insts, $link);
		
		$params = array('post_parent'=>$parent_id, 'post_type'=>'page', 'post_status'=>'publish','orderby' => 'menu_order','order' => 'ASC');
		if(get_children($params)){
			$child_insts = get_children($params);
			
			foreach($child_insts as $key => $value){
				
				$link = "<li class='cm-menu-t2'><a href='". get_permalink( $value->ID ) ."'>".$value->post_title."</a></li>";
				array_push($menu_item_insts, $link);
				
				if(get_children(array('post_parent'=>$value->ID, 'post_type'=>'page', 'post_status'=>'publish','orderby' => 'title','order' => 'ASC'))){
					$_child_insts = get_children(array('post_parent'=>$value->ID, 'post_type'=>'page', 'post_status'=>'publish','posts_per_page'=>100,'orderby' => 'title','order' => 'ASC'));
					
					foreach($_child_insts as $_key=>$_value){
						$_link = "<li class='cm-menu-t3'><a href='". get_permalink( $_value->ID ) ."'>".$_value->post_title."</a></li>";
						array_push($menu_item_insts, $_link);
					}
				}
			}
		}
		echo "<section class='widget widget-cm-menu'>";
		echo "<ul>";
		foreach($menu_item_insts as $value){
			echo $value;
		}
		echo "</ul>";
		echo "</section>";
	}






	// Widget Backend
	public function form( $instance ) {

		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		if ( isset( $instance[ 'page' ] ) ) {
			$page = $instance[ 'page' ];
		}
		//print_r($instance);
		// Widget admin form
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />

		</p>
		
		<?php $args = array(
			'sort_order' => 'ASC',
			'sort_column' => 'post_title',
			'hierarchical' => 1,
			'child_of' => 0,
			'parent' => -1,
			'offset' => 0,
			'post_type' => 'page',
			'post_status' => 'publish'
		); 
		$pages = get_pages($args);
		
		// echo "<pre>";
		// print_r($pages);
		// echo "</pre>";
		
		?>
		<label for="<?php echo $this->get_field_name('page'); ?>"><?php echo _e( 'Parent Page:' ); ?></label><br/>
		<select name="<?php echo $this->get_field_name('page'); ?>">
		<?php
		foreach($pages as $key => $value){
			
			$_id = $value->ID;
			$_title = $value->post_title;
			
			if(count(get_children(array( 'post_parent' => $_id, 'post_type' => 'page', 'post_status' => 'publish' ))) > 0){
				$children = true;
				?>
				<option value="<?php echo $_id; ?>" <?php if(isset($page) && ($page == $_id)){ echo "selected='true'"; } ?>><?php echo $_title; ?></option>
				<?php
			}
		}
		?>
		</select>
		<?php
		
		if( $children !== true ){
			?>
			<br/><br/>
			<i>!!! No child page hierarchy has been established. For this plugin to work, you must establish a parent, child page architecture. The menus will then be generated based on that hierarchy without the need to create another menu.</i>
			<br/><br/>
			<?php
		}
		
		
		//echo "<pre>";
		//print_r($pages);
		//echo "</pre>";
		
				
	}

	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['page'] = strip_tags( $new_instance['page'] );

		return $instance;
	}
} // Class auto_menu_sidebar_widget ends here