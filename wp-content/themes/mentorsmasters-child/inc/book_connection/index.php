<?php 
include( 'post-type.php' );
include( 'custom-fields.php' );
include( 'widget.php' );

add_action( 'wp_enqueue_scripts', 'book_connect_enqueue' );
function book_connect_enqueue() {
	
	wp_enqueue_style( 'book-connection-style', '/wp-content/themes/mentorsmasters-child/inc/book_connection/assets/style.css' );
	
    //wp_enqueue_script( 'candyjar-functions', '/wp-content/themes/mentorsmasters-child/js/candyjar.min.js' );
    //wp_enqueue_script( 'dailyq-functions', '/wp-content/themes/mentorsmasters-child/inc/daily_quote/assets/functions.min.js', array( 'candyjar-functions' ), false, true );
}