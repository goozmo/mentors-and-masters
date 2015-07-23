<?php

class mm_callout_sidebar_widget extends WP_Widget {
 


	function __construct() {
		
		parent::__construct(

			// Identifier
			'mm_callout_sidebar_widget',

			// Name
			__('MM Sidebar Callout', 'mm_callout_sidebar_widget_domain'),

			// Description
			array(
				'description' => __( 'Callout links for sidebar', 'mm_callout_sidebar_widget_domain' ), )
		);			
	}





	public function widget( $args, $instance ) {
		
		// echo "<pre>";
		// print_r( $instance );
		// echo "</pre>";
		
		$header = isset( $instance['header'] ) ? $instance['header'] : NULL;
		$link_text = isset( $instance['link-text'] ) ? $instance['link-text'] : NULL;
		$link = isset( $instance['link'] ) ? $instance['link'] : NULL;
		
		?>
		<div class="mm-callout-widget-inst">
			<div class="mm-callout-inst-inner">
				<div class="mm-callout-icon">
					<img src="/wp-content/uploads/mm_icon.png" alt="Mentors & Masters Directory" />
				</div>
				<div class="mm-callout-content">
					<h3><?php echo $header; ?></h3>
					<h4><a href="<?php echo $link; ?>"><?php echo $link_text; ?></a></h4>
				</div>
			</div>
		</div>
		
		
		<?php
		
	}






	// Widget Backend
	public function form( $instance ) {

		if ( isset( $instance[ 'header' ] ) ) {
			$header = $instance[ 'header' ];
		}
		if ( isset( $instance[ 'link-text' ] ) ) {
			$link_text = $instance[ 'link-text' ];
		}
		if( isset( $instance['link'] ) ) {
			$link = $instance['link'];
		}
		//print_r($instance);
		// Widget admin form
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'header' ); ?>"><?php _e( 'Header:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'header' ); ?>" name="<?php echo $this->get_field_name( 'header' ); ?>" type="text" value="<?php echo esc_attr( $header ); ?>" />
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'link-text' ); ?>"><?php _e( 'link-text:' ); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'link-text' ); ?>" name="<?php echo $this->get_field_name( 'link-text' ); ?>" type="text" value="<?php echo esc_attr( $link_text ); ?>" />
		</p>
		
		
		
		<p>
			<label for="<?php echo $this->get_field_id( 'link' ); ?>"><?php _e( 'Link:' ); ?></label><br/>
			<select name="<?php echo $this->get_field_name('link'); ?>">
			<?php
			
			//echo "<pre>";
			//print_r( get_pages() );
			//echo "</pre>";
			
			$pages = get_pages();
			
			foreach( $pages as $page ){
				
				?>
				<option value="<?php echo get_permalink( $page->ID ); ?>" <?php if(isset($link) && ($link == get_permalink( $page->ID ))){ echo "selected='true'"; } ?>><?php echo $page->post_title; ?></option>
				<?php
				
			}
			
			?>
			</select>
		</p>
		
		<?php		
	}






	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		
		$instance = array();
		$instance['header'] = strip_tags( $new_instance['header'] );
		$instance['link-text'] = strip_tags( $new_instance['link-text'] );
		$instance['link'] = strip_tags( $new_instance['link'] );

		return $instance;
	}
} // Class mm_callout_sidebar_widget ends here