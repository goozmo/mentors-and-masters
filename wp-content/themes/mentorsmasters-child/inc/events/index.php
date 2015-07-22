<?php
	
include( 'widget.php' );
include( 'post-type.php' );
include( 'custom-fields.php' );

add_action( 'wp_enqueue_scripts', 'cmevents_enqueue_styles' );
function cmevents_enqueue_styles() {
	
	wp_enqueue_style( 'emevents-style', '/wp-content/themes/mentorsmasters-child/inc/events/assets/style.css' );
	
    // wp_enqueue_script( 'candyjar-functions', '/wp-content/themes/mentorsmasters-child/js/candyjar.min.js' );
    // wp_enqueue_script( 'dailyq-functions', '/wp-content/themes/mentorsmasters-child/inc/daily_quote/assets/functions.min.js', array( 'candyjar-functions' ), false, true );
}