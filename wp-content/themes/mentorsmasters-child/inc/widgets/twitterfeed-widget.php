<?php

class twitterfeed_widget extends WP_Widget {
 
	function __construct() {
		
		parent::__construct(

			// Identifier
			'twitter_feed_widget',

			// Name
			__('Twitter Feed Widget', 'twitter_feed_widget'),

			// Description
			array(
				'description' => __( 'Twitter Feed Widget', 				'twitter_feed_widget' ), )
		);			
	}

	public function widget( $args, $instance ) {
		?>
		<a class="twitter-timeline" href="https://twitter.com/MentorsMasters" data-widget-id="609407844773838848">Tweets by @MentorsMasters</a>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+"://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
		<?php
	}






	// Widget Backend
	public function form( $instance ) {

		if ( isset( $instance[ 'title' ] ) ) {
			$title = $instance[ 'title' ];
		}
		
		//print_r($instance);
		// Widget admin form
						
	}

	// Updating widget replacing old instances with new
	public function update( $new_instance, $old_instance ) {
		
		$instance = array();
		$instance['title'] = strip_tags( $new_instance['title'] );

		return $instance;
	}
} // Class auto_menu_sidebar_widget ends here