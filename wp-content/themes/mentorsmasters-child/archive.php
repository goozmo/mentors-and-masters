<?php
	global $avia_config, $more;

	/*
	 * get_header is a basic wordpress function, used to retrieve the header.php file in your theme directory.
	 */
	 get_header();
	
		
		$showheader = true;
		if(avia_get_option('frontpage') && $blogpage_id = avia_get_option('blogpage'))
		{
			if(get_post_meta($blogpage_id, 'header', true) == 'no') $showheader = false;
		}
		
	 	if($showheader)
	 	{
			echo avia_title(array('title' => avia_which_archive()));
		}
	?>

		<div class='container_wrap container_wrap_first main_color <?php avia_layout_class( 'main' ); ?>'>

			<div class='container template-blog '>
				<?php echo get_post_type_archive_link( 'post' ); ?>
				<main class='content <?php avia_layout_class( 'content' ); ?> units' <?php avia_markup_helper(array('context' => 'content','post_type'=>'post'));?>>
					
					<?php
						
						if( get_post_type() === 'cmevents' ){
							?>
							<div style="margin-bottom: 40px;padding-bottom: 40px; border-bottom: 1px solid rgb(220,220,220);">
								<div style="padding: 10px 20px;">
								<p style="text-align: center;"><em>"Success comes when people act together; failure tends to happen alone."</em>
Deepak Chopra</p>
							
<span style="font-family: Arial, serif;"><span style="font-size: medium;">Tap into the power of community for inspiration and support by attending events. Browse our calendar to discover the many worthwhile events happening in your neighborhood and around the world.</span></span>
								</div>
							</div>
							<?php
						}
						
					?>
					
                    <div class="category-term-description">
                        <?php echo term_description(); ?>
                    </div>

                    <?php
                    $avia_config['blog_style'] = apply_filters('avf_blog_style', avia_get_option('blog_style','multi-big'), 'archive');
                    if($avia_config['blog_style'] == 'blog-grid')
                    {
                        global $posts;
                        $post_ids = array();
                        foreach($posts as $post) $post_ids[] = $post->ID;

                        if(!empty($post_ids))
                        {
                            $atts   = array(
                                'type' => 'grid',
                                'items' => get_option('posts_per_page'),
                                'columns' => 3,
                                'class' => 'avia-builder-el-no-sibling',
                                'paginate' => 'yes',
                                'use_main_query_pagination' => 'yes',
                                'custom_query' => array( 'post__in'=>$post_ids, 'post_type'=>get_post_types() )
                            );

                            $blog = new avia_post_slider($atts);
                            $blog->query_entries();
                            echo "<div class='entry-content-wrapper'>".$blog->html()."</div>";
                        }
                        else
                        {
                            get_template_part( 'includes/loop', 'index' );
                        }
                    }
                    else
                    {
                        /* Run the loop to output the posts.
                        * If you want to overload this in a child theme then include a file
                        * called loop-index.php and that will be used instead.
                        */

                        $more = 0;
                        get_template_part( 'includes/loop', 'index' );
                    }
                    ?>

				<!--end content-->
				</main>

				<?php
				
				//get the sidebar
				$_goo_post_type = get_post_type();
				switch( $_goo_post_type ){
					case $_goo_post_type == 'cmevents' :
					case $_goo_post_type == 'bookconnection' :
						$avia_config['currently_viewing'] = 'page';
						break;
					default : 
						$avia_config['currently_viewing'] = 'blog';
				}
				

				get_sidebar();

				?>

			</div><!--end container-->

		</div><!-- close default .container_wrap element -->




<?php get_footer(); ?>
