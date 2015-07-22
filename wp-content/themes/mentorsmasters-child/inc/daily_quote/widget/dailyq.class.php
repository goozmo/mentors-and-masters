<?php

class dailyq_widget extends WP_Widget {
 
	function __construct() {
		
		parent::__construct(

			// Identifier
			'daily_quote_widget',

			// Name
			__('CM Daily Quote', 'daily_quote'),

			// Description
			array(
				'description' => __( 'Daily quote rotator', 'daily_quote' ), )
		);			
	}

	public function widget( $args, $instance ) {
		
		$title = isset( $instance['title'] ) ? $instance['title'] : NULL;
		
		$args = array(
			'posts_per_page'   => 100,
			'offset'           => 0,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'post_date',
			'order'            => 'DESC',
			'include'          => '',
			'exclude'          => '',
			'meta_key'         => '',
			'meta_value'       => '',
			'post_type'        => 'dailyquoterotator',
			'post_mime_type'   => '',
			'post_parent'      => '',
			'post_status'      => 'publish',
			'suppress_filters' => true 
		);
		$posts_array = get_posts( $args );
		?>
		
		<section class="goo-dailyq">
		
		<header class="goo-dailyq-header">
			<?php
				if( $title ){
					?>
					<h2><?php echo $title; ?></h2>
					<?php
				}
			?>
		</header>
		
		<div class="goo-dailyq-container">
			<div class="goo-dailyq-clipping-container">
		<?php
			if(  is_array( $posts_array ) && count( $posts_array ) ){
				// echo "<pre>";
				// print_r( $posts_array );	
				// echo "</pre>";
				$i=1;
				foreach( $posts_array as $index ){
					
					 $id = $index->ID;
					 $content = $index->post_content;
					 $author = get_post_meta( $id, '_goo_dailyq_author', true );
					 ?>
					 <div id="goo-dailyq-content-inst<?php echo $i; ?>" class="goo-dailyq-content-inst">
					 	
					 	<div class="goo-dailyq-content">
					 		<q><?php echo $content; ?></q>
					 	</div>
					 	
					 	<div class="goo-dialyq-author">
					 		<i><?php echo $author; ?></i>
					 	</div>
					 	
					 	<div class="dailyq_clear"></div>
					 </div>
					<?
					$i++;
				}
				
			}
			?>
			
			</div>
			<div class="dailyq_clear"></div>
		</div>
		</section>
		<?php		
	}


	// Widget Backend
	public function form( $instance ) {
		
		$title = isset( $instance['title'] ) ? $instance['title'] : NULL;
		
		//print_r($instance);
		// Widget admin form
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<?php	
	}

	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}
} // Class auto_menu_sidebar_widget ends here