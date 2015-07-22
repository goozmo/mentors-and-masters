<?php
global $avia_config;


// check if we got posts to display:
if (have_posts()) :
	$first = true;

	$counterclass = "";
	$post_loop_count = 1;
	$page = (get_query_var('paged')) ? get_query_var('paged') : 1;
	if($page > 1) $post_loop_count = ((int) ($page - 1) * (int) get_query_var('posts_per_page')) +1;
	$blog_style = avia_get_option('blog_style','multi-big');


	while (have_posts()) : the_post();


	$the_id 		= get_the_ID();
	$parity			= $post_loop_count % 2 ? 'odd' : 'even';
	$last           = count($wp_query->posts) == $post_loop_count ? " post-entry-last " : "";
	$post_class 	= "post-entry-".$the_id." post-loop-".$post_loop_count." post-parity-".$parity.$last." ".$blog_style;
	$post_format 	= get_post_format() ? get_post_format() : 'standard';

	?>
	
	<?php echo wpbdp_render_listing(null, 'excerpt'); ?>
	
	

	<?php


		$first = false;
		$post_loop_count++;
		if($post_loop_count >= 100) $counterclass = "nowidth";
	endwhile;
	else:


?>

	<article class="entry entry-content-wrapper clearfix" id='search-fail'>
            <p class="entry-content" <?php avia_markup_helper(array('context' => 'entry_content')); ?>>
                <strong><?php _e('Nothing Found', 'avia_framework'); ?></strong><br/>
               <?php _e('Sorry, no posts matched your criteria. Please try another search', 'avia_framework'); ?>
            </p>

            <div class='hr_invisible'></div>

            <section class="search_not_found">
                <p><?php _e('You might want to consider some of our suggestions to get better results:', 'avia_framework'); ?></p>
                <ul>
                    <li><?php _e('Check your spelling.', 'avia_framework'); ?></li>
                    <li><?php _e('Try a similar keyword, for example: tablet instead of laptop.', 'avia_framework'); ?></li>
                    <li><?php _e('Try using more than one keyword.', 'avia_framework'); ?></li>
                </ul>

                <div class='hr_invisible'></div>
                <h3 class=''><?php _e('Feel like browsing some posts instead?', 'avia_framework'); ?></h3>

        <?php
        the_widget('avia_combo_widget', 'error404widget', array('widget_id'=>'arbitrary-instance-'.$id,
                'before_widget' => '<div class="widget avia_combo_widget">',
                'after_widget' => '</div>',
                'before_title' => '<h3 class="widgettitle">',
                'after_title' => '</h3>'
            ));
        echo '</section>';
	echo "</article>";

	endif;
	echo avia_pagination('', 'nav');
?>
