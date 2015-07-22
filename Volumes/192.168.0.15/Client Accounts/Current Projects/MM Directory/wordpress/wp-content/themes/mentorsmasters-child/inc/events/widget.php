<?php

class cm_events_widget extends WP_Widget {
 
	function __construct() {
		
		parent::__construct(

			// Identifier
			'cm_events_widget',

			// Name
			__('CM Events', 'cm_events_widget'),

			// Description
			array(
				'description' => __( 'Events Post Type', 'cm_events_widget' ), )
		);			
	}

	public function widget( $args, $instance ) {
		
		$title = isset( $instance['title'] ) ? $instance['title'] : NULL;
		$no_posts = isset( $instance['no_posts'] ) ? $instance['no_posts'] : 3;
		
		$args = array(
			'posts_per_page'   => 0,
			'offset'           => 0,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'post_date',
			'order'            => 'ASC',
			'include'          => '',
			'exclude'          => '',
			'meta_key'         => '',
			'meta_value'       => '',
			'post_type'        => 'cmevents',
			'post_mime_type'   => '',
			'post_parent'      => '',
			'post_status'      => 'publish',
			'suppress_filters' => true 
		);
		$posts_array = get_posts( $args );
		
		

		$current_time = mktime();
		$post_time = date( 'Y-m-d h:i:s' );
		$post_time = strtotime( $post_time );
		
		
		
		$i = 1;
		foreach( $posts_array as $index ){
			
			echo "what"?
			
			
			echo $current_time . "<br/>";
			echo $post_time;
			
			$_id = $index->ID;
			$_title = $index->post_title;
			$_excerpt = !empty( $index->post_excerpt ) ? $index->post_excerpt : $index->post_content;
			if( strlen( $_excerpt ) > 60 ){
				$_excerpt = substr( $_excerpt, 0, 60 );
				$_last_space = strrpos( $_excerpt, " ");
				$_excerpt = substr( $_excerpt, 0, $_last_space ) . " ...";
			}
			$_url = get_permalink( $_id );
			$_starttime = strtotime( get_post_meta( $_id, '_goo_cmevent_start', true ));
			$_date = date( "F j", $_starttime );
			?>
			
			<div class="cmevents-inst" id="cmevents-inst<?php echo $i; ?>">
				<div class="cmevents-inst-inner">
					
					<h3><a href="<?php echo $_url; ?>"><?php echo $_title; ?></a></h3>
					<h4><a href="<?php echo $_url; ?>"><?php echo $_date; ?></a></h4>
					<p><?php echo $_excerpt; ?></p>
				</div>
				<div class="cmevents-inst-clear"></div>
			</div>
			<?php
			$i++;
		}
		
			
	}


	// Widget Backend
	public function form( $instance ) {
		
		$title = isset( $instance['title'] ) ? $instance['title'] : NULL;
		$no_posts = isset( $instance['no_posts'] ) ? $instance['no_posts'] : 3;
		
		//print_r($instance);
		// Widget admin form
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'no_posts' ); ?>"><?php _e( 'No. Events to Show:' ); ?></label>
			<input type="number" min="0" class="widefat" id="<?php echo $this->get_field_id( 'no_posts' ); ?>" name="<?php echo $this->get_field_name( 'no_posts' ); ?>" value="<?php echo esc_attr( $no_posts ); ?>" />
		</p>
		<?php	
	}

	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['no_posts'] = strip_tags( $new_instance['no_posts'] );

		return $instance;
	}
} // Class auto_menu_sidebar_widget ends here