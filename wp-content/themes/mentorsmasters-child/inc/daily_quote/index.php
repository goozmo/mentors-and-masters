<?php

define( '__ROOT_DIR__', $_SERVER['DOCUMENT_ROOT'] .'/wp-content/themes/mentorsmasters-child/inc/daily_quote/' );

include( __ROOT_DIR__.'admin/post_type.php' );
include( __ROOT_DIR__.'admin/daily_quote_fields.php' );

add_action( 'wp_enqueue_scripts', 'dailyq_enqueue_styles' );
function dailyq_enqueue_styles() {
	
	wp_enqueue_style( 'dailyq-style', '/wp-content/themes/mentorsmasters-child/inc/daily_quote/assets/style.css' );
	
    wp_enqueue_script( 'candyjar-functions', '/wp-content/themes/mentorsmasters-child/js/candyjar.min.js' );
    wp_enqueue_script( 'dailyq-functions', '/wp-content/themes/mentorsmasters-child/inc/daily_quote/assets/functions.min.js', array( 'candyjar-functions' ), false, true );
}



include( __ROOT_DIR__.'widget/dailyq.class.php' );
register_widget( 'dailyq_widget' );