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
					
					<form name="cmevents-searchform" id="cmevents-searchform" method="get">
						<input type="text" name="cmevents-citysearch" placeholder="city search" />
						<input type="text" name="cmevents-statesearch" placeholder="state/province search" />
						<input type="submit"/>
					</form>
					
					
                    <?php
	                
		            $cm_citysearch = isset( $_GET['cmevents-citysearch'] ) ? $_GET['cmevents-citysearch'] : NULL;
		            $cm_statesearch = isset( $_GET['cmevents-statesearch'] ) ? $_GET['cmevents-statesearch'] : NULL;
	                
	                $args = array(
						'post_type' => 'cmevents'
					);
					
					$cmevents = new WP_Query( $args );
					
					$filteredArray = array();
	                
	                $i = 0;
	                foreach( $cmevents->posts as $post ){
						$_venue_ 	= 	get_post_meta( $post->ID, '_goo_cmevent_address_venue', true );
						$_street_ 	= 	get_post_meta( $post->ID, '_goo_cmevent_address_street', true );
						$_city_ 	= 	get_post_meta( $post->ID, '_goo_cmevent_address_city', true );
						$_state_ 	= 	get_post_meta( $post->ID, '_goo_cmevent_address_state', true );
						$_zip_ 		= 	get_post_meta( $post->ID, '_goo_cmevent_address_zip', true );
						$_country_ 	= 	get_post_meta( $post->ID, '_goo_cmevent_address_country', true );
			
						// $_address_ = array( $_city_, $_state_, $_zip_, $_country_ );
						
						$_starttime = 	strtotime( get_post_meta( $post->ID, '_goo_cmevent_start', true ));
						$_endtime =  	strtotime( get_post_meta( $post->ID, '_goo_cmevent_end', true ) );
						
						$post->cmevents_starttime = $_starttime;
						$post->cmevents_endtime = $_endtime;
						$post->cmevents_venue = $_venue_;
						$post->cmevents_street = $_street_;
						$post->cmevents_city = $_city_;
						$post->cmevents_state = $_state_;
						$post->cmevents_zip = $_zip_;
						$post->cmevents_country = $_country_;
						
						if( $_endtime < mktime() ){
							array_splice( $cmevents->posts, $i, 1 );
						}
						$i++;
						
	                }
	                $filteredArray = $cmevents->posts;
	                array_multisort( $filteredArray, SORT_DESC, $cmevents->posts );
					
					$searchQuery = array();
	                if( $cm_citysearch || $cm_statesearch ){
		                
		                $i=0;
		                foreach( $filteredArray as $post ){
			                
			                if( preg_match( "/\b$cm_citysearch\b/i", $post->cmevents_city ) || !preg_match( "/\b$cm_statesearch\b/i", $post->cmevents_state ) ){
				                array_push( $searchQuery, $post );
			                }
			                
			                $i++;
			                
		                }
		                $filteredArray = $searchQuery;
	                }
	                
	                
	                
	                $cmevents->posts = $filteredArray;
	                echo "<pre>";
	                print_r( $cmevents->posts );
	                echo "</pre>";
	                
	               
	             
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
