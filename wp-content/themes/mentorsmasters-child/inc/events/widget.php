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
		
		$i = 0;
		foreach( $posts_array as $index ){
			$_id = $index->ID;	
			
			$_starttime = strtotime( get_post_meta( $_id, '_goo_cmevent_start', true ));
			$posts_array[$i]->start_time = $_starttime;
			
			$_endtime =  strtotime( get_post_meta( $_id, '_goo_cmevent_end', true ) );
			$posts_array[$i]->end_time = $_endtime;
			
			$_city = get_post_meta( $_id, '_goo_cmevent_address_city', true );
			$posts_array[$i]->city = $_city;
			
			$_state = get_post_meta( $_id, '_goo_cmevent_address_state', true );
			$posts_array[$i]->state = $_state;
			
			$_country = get_post_meta( $_id, '_goo_cmevent_address_country', true );
			$posts_array[$i]->country = $_country;
			
			$i++;
		}
		
		$sort_array = array();
		
		foreach( $posts_array as $index ){
			foreach( $index as $key => $value ){
				$sort_array[$key][] = $value;
			}
		}
		
		if( !empty( $sort_array ) ){
			array_multisort( $sort_array['end_time'], 'SORT_ASC', $posts_array );
		}
		
		//echo "<pre>";
		//print_r( $sort_array );
		//echo "</pre>";
		
		
		$current_time = mktime();
		$i = 1;
		foreach( $posts_array as $index ){
			$_id = $index->ID;		
			$post_time =  get_post_meta( $_id, '_goo_cmevent_end', true );
			$post_time = strtotime( $post_time );
			// echo $post_time;
			if( $post_time >= $current_time && $i <= $no_posts ){
				
				$_title = $index->post_title;
				
				$_url = get_permalink( $_id );
				$_starttime = strtotime( get_post_meta( $_id, '_goo_cmevent_start', true ));
				$_date = date( "F j", $_starttime );
				$_endtime = date( "F j", $post_time );
				
				$_city =  get_post_meta( $_id, '_goo_cmevent_address_city', true ) ?  get_post_meta( $_id, '_goo_cmevent_address_city', true ) : NULL;
				$_state =  get_post_meta( $_id, '_goo_cmevent_address_state', true ) ?  get_post_meta( $_id, '_goo_cmevent_address_state', true ) : NULL;
				$_country =  get_post_meta( $_id, '_goo_cmevent_address_country', true ) ?  get_post_meta( $_id, '_goo_cmevent_address_country', true ) : NULL;
				
				$_address = array( $_city, $_state, $_country );
				$_app_address = "";
				$nth = 0;
				foreach( $_address as $_val ){
					if( $_val !== NULL ){
						$_app_address.= $_val;
						if( $nth < count( $_address ) - 1 ){
							$_app_address.= ", ";
						} 
						$nth++;
					}
				}
				
				?>
				
				<div class="cmevents-inst" id="cmevents-inst<?php echo $i; ?>">
					<div class="cmevents-inst-inner">
						
						<h3><a href="<?php echo $_url; ?>"><?php echo $_title; ?></a></h3>
						<h4><a href="<?php echo $_url; ?>"><?php echo $_date; ?> - <?php echo $_endtime; ?></a></h4>
						<h5><a href="<?php echo $_url; ?>"><?php echo $_app_address; ?></a></h5>
						
					</div>
					<div class="cmevents-inst-clear"></div>
				</div>
				<?php
				$i++;
			}
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