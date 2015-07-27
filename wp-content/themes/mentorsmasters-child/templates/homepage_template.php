<?php
/*
	*
	*	template name: Homepage
	*
	*
	*
*/

global $avia_config;

	/*
	 * get_header is a basic wordpress function, used to retrieve the header.php file in your theme directory.
	 */
	 get_header();


 	 if( get_post_meta(get_the_ID(), 'header', true) != 'no') echo avia_title();
	 ?>
		<div class='container_wrap container_wrap_first main_color <?php avia_layout_class( 'main' ); ?>'>
			
			<div id="_goo-slider-container">	
				<?php wd_slider(1); ?>
				<div id="_goo-slider-cover">
					<div class="container">
						<h1>Your journey, many guides, one directory.<span class="mini-me">&trade;</span></h1>
						<h2>We are here to help you connect with business mentors, life coaches, healers, teachers, and other masters of transformation who offer expert guidance and inspired products and services to help you move forward in all areas of your life.</h2>
						<?php get_search_form( true ); ?>
					</div>
					
				</div>
				
			</div>
			<div class="border-container">
					
			</div>
			<div class="border-container-2">
					
			</div>
			
			<div class='container'>
				<main class='template-page content  <?php avia_layout_class( 'content' ); ?> units' <?php avia_markup_helper(array('context' => 'content','post_type'=>'page'));?>>	 
                    <?php
                    /* Run the loop to output the posts.
                    * If you want to overload this in a child theme then include a file
                    * called loop-page.php and that will be used instead.
                    */

                    $avia_config['size'] = avia_layout_class( 'main' , false) == 'entry_without_sidebar' ? '' : 'entry_with_sidebar';
                    get_template_part( 'templates/loop', 'page' );
                    ?>

				<!--end content-->
				</main>

				<?php

				//get the sidebar
				$avia_config['currently_viewing'] = 'page';
				get_sidebar();

				?>

			</div><!--end container-->

		</div><!-- close default .container_wrap element -->



<?php get_footer(); ?>