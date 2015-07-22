<?php

class book_connection_sidebar_widget extends WP_Widget {
 
	function __construct() {
		
		parent::__construct(

			// Identifier
			'book_connection_widget',

			// Name
			__('Book Connection', 'book-connection'),

			// Description
			array(
				'description' => __( 'Book Connection display', 'book-connection' ), )
		);			
	}

	public function widget( $args, $instance ) {
		
		// echo "<pre>";
		// print_r( $instance );
		// echo "</pre>";
		
		$args = array(
			'posts_per_page'   => $instance['display_no'],
			'offset'           => 0,
			'category'         => '',
			'category_name'    => '',
			'orderby'          => 'post_date',
			'order'            => 'DESC',
			'include'          => '',
			'exclude'          => '',
			'meta_key'         => '',
			'meta_value'       => '',
			'post_type'        => 'bookconnection',
			'post_mime_type'   => '',
			'post_parent'      => '',
			'post_status'      => 'publish',
			'suppress_filters' => true 
		);
		
		$posts_array = get_posts( $args );
		
		// echo "<pre>";
		// print_r( $posts_array );
		// echo "</pre>";
		
		$i = 1;
		foreach( $posts_array as $index ){
			
			$_id = $index->ID;
			$_title = $index->post_title;
			$_author = get_post_meta( $_id, '_goo_book_author', true );
			$_excerpt = !empty( $index->post_excerpt ) ? $index->post_excerpt : $index->post_content;
			if( strlen( $_excerpt ) > 60 ){
				$_excerpt = substr( $_excerpt, 0, 60 );
				$_last_space = strrpos( $_excerpt, " ");
				$_excerpt = substr( $_excerpt, 0, $_last_space ) . " ...";
			}
			$_thumb = get_the_post_thumbnail( $_id, array( 95, 125 ) );
			$_url = get_permalink( $_id );
			?>
			<div id="book-connect-inst<?php echo $i; ?>" class="book-connect-inst">
				<div class="book-connect-inst-inner">
					<figure>
						<?php if( $_thumb ) echo $_thumb; ?>
					</figure>
					<section>
						<h3><?php if( $_title ) echo $_title; ?></h3>
						<p><?php if( $_excerpt ) echo $_excerpt; ?></p>
						<a href="<?php if( $_url ) echo $_url; ?>">Read More</a>
					</section>
				</div>
				<div class="book-connect-inst-clear"></div>
			</div>
			<?php
			$i++;
		}
	}




	// Widget Backend
	public function form( $instance ) {
		
		// echo "<pre>";
		// print_r($instance);
		// echo "</pre>";
		
		$title = isset( $instance[ 'title' ] ) ? $instance[ 'title' ] : NULL;
		$display_no = isset( $instance[ 'display_no' ] ) ? $instance[ 'display_no' ] : 3;
		

		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:' ); ?></label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'display_no' ); ?>"><?php _e( 'No. of Books to Display:' ); ?></label>
			<input type="number" class="widefat" id="<?php echo $this->get_field_id( 'display_no' ); ?>" name="<?php echo $this->get_field_name( 'display_no' ); ?>" value="<?php echo esc_attr( $display_no ); ?>" />
		</p>
		
		<?php				
	}

	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['display_no'] = strip_tags( $new_instance['display_no'] );

		return $instance;
	}
} // Class auto_menu_sidebar_widget ends here