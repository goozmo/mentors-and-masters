<?php
	
/*
	*
	*
	*
	*
	*
	*
	*
	*
*/

add_theme_support( 'html5', array( 'search-form' ) );


add_action( 'wp_enqueue_scripts', 'theme_enqueue_styles' );
function theme_enqueue_styles() {
    wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );
    wp_enqueue_style( 'child-style',
        get_stylesheet_directory_uri() . '/style.css',
        array('parent-style')
    );
}

include( 'inc/http-redirect/index.php' );

/*
	*
	*	sidebars
	*
	*
*/

function goo_load_sidebars(){
	$args = array(
		array(
			'name'          => __( 'Quote of the Day', 'dailyquote' ),
			'id'            => 'daily-quote',
			'description'   => 'Daily Quote Rotator',
			'class'         => 'daily-quote',
		),
		array(
			'name'          => __( 'Featured Homepage Business', 'homefeatured' ),
			'id'            => 'home-featured',
			'description'   => 'featured homepage business',
			'class'         => 'home-featured',
		),
		array(
			'name'          => __( 'Book Connections Area', 'bookconnections' ),
			'id'            => 'book-connections',
			'description'   => 'Book Connections Sidebar',
			'class'         => 'book-connections',
		),
		array(
			'name'          => __( 'Events Roll', 'eventsroll' ),
			'id'            => 'events-roll',
			'description'   => 'Events Roll Sidebar',
			'class'         => 'events-roll',
		),
		array(
			'name'          => __( 'Twitter Feed', 'twitterfeed' ),
			'id'            => 'twitter-feed',
			'description'   => 'Twitter Feed',
			'class'         => 'twitter-feed',
		),
		array(
			'name'          => __( 'Facebook Feed', 'facebookfeed' ),
			'id'            => 'facebook-feed',
			'description'   => 'Facebook Feed',
			'class'         => 'facebook-feed',
		),
		
	);
	
	foreach( $args as $arg ){
		register_sidebar( $arg );
	}
	
	
}
add_action( 'widgets_init', 'goo_load_sidebars' );

/*
	*
	*	widgets
	*
	*
*/

include( $_SERVER['DOCUMENT_ROOT'].'/wp-content/themes/mentorsmasters-child/inc/widgets/auto_menu_sidebar_widget.class.php' );
include( $_SERVER['DOCUMENT_ROOT'].'/wp-content/themes/mentorsmasters-child/inc/widgets/twitterfeed-widget.php' );
include( $_SERVER['DOCUMENT_ROOT'].'/wp-content/themes/mentorsmasters-child/inc/widgets/mm_sidebar_callout_widget.php' );

function goo_load_widget() {
	
	$widgets = array(
		'auto_menu_sidebar_widget',
		'book_connection_sidebar_widget',
		'cm_events_widget',
		'twitterfeed_widget',
		'mm_callout_sidebar_widget'
	);
	
	foreach($widgets as $key => $value){
		register_widget($value);
	}

}
add_action( 'widgets_init', 'goo_load_widget' );

/*
	*
	*	custom posts
	*
	*
*/

include( $_SERVER['DOCUMENT_ROOT'].'/wp-content/themes/mentorsmasters-child/inc/daily_quote/index.php' );
include( $_SERVER['DOCUMENT_ROOT'].'/wp-content/themes/mentorsmasters-child/inc/book_connection/index.php' );
include( $_SERVER['DOCUMENT_ROOT'].'/wp-content/themes/mentorsmasters-child/inc/events/index.php' );


if ( ! function_exists( 'unregister_post_type' ) ) :
function unregister_post_type( $post_type ) {
    global $wp_post_types;
    if ( isset( $wp_post_types[ $post_type ] ) ) {
        unset( $wp_post_types[ $post_type ] );
        return true;
    }
    return false;
}
endif;

unregister_post_type( 'portfolio' );

/*
	*
	*	custom login
	*
	*
*/

add_action('login_head', 'custom_login_logo');
function custom_login_logo() {
    echo "<style type=\"text/css\">
    	body.login
    	{
	        background-size: cover;
	        background-position : center center;
	        background-color: rgb(255,255,255);
        }
        html
        {
	        background-color: rgb(255,255,255);
        }
    	.login #login
    	{
	    	max-width: 500px;
	    	width: 100%;
    	}
        .login #login h1 a
        {
	        background-image:url(/wp-content/uploads/logoMM.png);
	        width: 100%;
	        background-size: contain;
        }
        .login #login form
        {
	        padding: 30px 40px 40px;
	        border-radius: 4px;
	        background : rgb( 66,164,199 );
        }
        .login #login form label
        {
	        color : rgb( 255,255,255 );
        }
        .login #login #nav,
        .login #login #backtoblog
        {
	        text-align: center;
	    }
	    .login #login #nav a,
        .login #login #backtoblog a
        {
	        color: rgb( 90,90,90 );
        }
        #login form #pass_strength_msg
        {
	        color: rgb( 255,255,255 );
        }
    </style>";
}


add_shortcode( 'getRegLinkShortcode', 'getRegLink' );
function getRegLink(){
	
	if( is_user_logged_in() ){
		
	}
	
}



if(!function_exists('_goo_which_archive'))
{
	/**
	 *  checks which archive we are viewing and returns the archive string
	 */

	function _goo_which_archive()
	{
		$output = "";

		if ( is_category() )
		{
			$output = __('Archive for category:','avia_framework')." ".single_cat_title('',false);
		}
		elseif (is_day())
		{
			$output = __('Archive for date:','avia_framework')." ".get_the_time( __('F jS, Y','avia_framework') );
		}
		elseif (is_month())
		{
			$output = __('Archive for month:','avia_framework')." ".get_the_time( __('F, Y','avia_framework') );
		}
		elseif (is_year())
		{
			$output = __('Archive for year:','avia_framework')." ".get_the_time( __('Y','avia_framework') );
		}
		elseif (is_search())
		{
			global $wp_query;
			if(!empty($wp_query->found_posts))
			{
				if($wp_query->found_posts > 1)
				{
					// Goozmo edited this
					// $output =  $wp_query->found_posts ." ". __('search results for:','avia_framework')." ".esc_attr( get_search_query() );
					$output = __('search results for:','avia_framework')." ".esc_attr( get_search_query() );
				}
				else
				{
					// Goozmo edited this
					// $output =  $wp_query->found_posts ." ". __('search result for:','avia_framework')." ".esc_attr( get_search_query() );
					$output = __('search result for:','avia_framework')." ".esc_attr( get_search_query() );
				}
			}
			else
			{
				if(!empty($_GET['s']))
				{
					$output = __('Search results for:','avia_framework')." ".esc_attr( get_search_query() );
				}
				else
				{
					$output = __('To search the site please enter a valid term','avia_framework');
				}
			}

		}
		elseif (is_author())
		{
			$curauth = (get_query_var('author_name')) ? get_user_by('slug', get_query_var('author_name')) : get_userdata(get_query_var('author'));
			$output = __('Author Archive','avia_framework')." ";

			if(isset($curauth->nickname) && isset($curauth->ID))
            {
                $name = apply_filters('avf_author_nickname', $curauth->nickname, $curauth->ID);
		$output .= __('for:','avia_framework') ." ". $name;
            }

		}
		elseif (is_tag())
		{
			$output = __('Tag Archive for:','avia_framework')." ".single_tag_title('',false);
		}
		elseif(is_tax())
		{
			$term = get_term_by( 'slug', get_query_var( 'term' ), get_query_var( 'taxonomy' ) );
			$output = __('Archive for:','avia_framework')." ".$term->name;
		}
		else
		{
			$output = __('Archives','avia_framework')." ";
		}

		if (isset($_GET['paged']) && !empty($_GET['paged']))
		{
			$output .= " (".__('Page','avia_framework')." ".$_GET['paged'].")";
		}

        	$output = apply_filters('avf_which_archive_output', $output);
        	
		return $output;
	}
}







/*
add_action('pre_get_posts','alter_query');
 
function alter_query($query) {
	//gets the global query var object
	
	// global $wp_query;
 
	//gets the front page id set in options
	//$front_page_id = get_option('page_on_front');
 
	//if ( 'page' != get_option('show_on_front') || $front_page_id != $wp_query->query_vars['page_id'] )
	//return;
 
	//if ( !$query->is_main_query() )
	//return;
 
	// if ( is_search() ) {
	//	$query->set('post_type' , array( 'wpbdp_listing', 'attachment', 'nav_menu_item' ));
	//}
 
	//we remove the actions hooked on the '__after_loop' (post navigation)
	//remove_all_actions ( '__after_loop');
}
*/










/*
add_filter('posts_orderby', 'cmevents_sort' );
function cmevents_sort( $orderby_statement ){
	
	// echo $orderby_statement;
	
	//global $wp_query;
	//if( $wp_query->query['post_type'] === 'cmevents' ){
		// echo "<pre>";
		// print_r( $wp_query );
		// echo "</pre>";
	//}
}
*/





?>