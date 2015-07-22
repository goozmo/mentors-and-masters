<?php
global $avia_config;
$blank = isset($avia_config['template']) ? $avia_config['template'] : "";

//reset wordpress query in case we modified it
wp_reset_query();


//get footer display settings
$the_id 				= avia_get_the_id(); //use avia get the id instead of default get id. prevents notice on 404 pages
$footer 				= get_post_meta($the_id, 'footer', true);
$footer_widget_setting 	= !empty($footer) ? $footer : avia_get_option('display_widgets_socket');


//check if we should display a footer
if(!$blank && $footer_widget_setting != 'nofooterarea' )
{
	if( $footer_widget_setting != 'nofooterwidgets' )
	{
		//get columns
		$columns = avia_get_option('footer_columns');
?>

<div class='container_wrap footer_color' id='footer'>
	<div class='container'>

		<?php
		do_action('avia_before_footer_columns');
		
		//create the footer columns by iterating
		
		
    	switch($columns)
    	{
    		case 1: $class = ''; break;
    		case 2: $class = 'av_one_half'; break;
    		case 3: $class = 'av_one_third'; break;
    		case 4: $class = 'av_one_fourth'; break;
    		case 5: $class = 'av_one_fifth'; break;
    		case 6: $class = 'av_one_sixth'; break;
    	}
    	
    	$firstCol = "first el_before_{$class}";
		
		//display the footer widget that was defined at appearenace->widgets in the wordpress backend
		//if no widget is defined display a dummy widget, located at the bottom of includes/register-widget-area.php
		for ($i = 1; $i <= $columns; $i++)
		{
			$class2 = ""; // initialized to avoid php notices
			if($i != 1) $class2 = " el_after_{$class}  el_before_{$class}";
			echo "<div class='flex_column {$class} {$class2} {$firstCol}'>";
			if (function_exists('dynamic_sidebar') && dynamic_sidebar('Footer - column'.$i) ) : else : avia_dummy_widget($i); endif;
			echo "</div>";
			$firstCol = "";
		}
		
		do_action('avia_after_footer_columns');
		
		?>
		
		
		<?php

			//copyright
			$copyright = do_shortcode( avia_get_option('copyright', "&copy; ".__('Copyright','avia_framework')."  - <a href='".home_url('/')."'>".get_bloginfo('name')."</a>") );

			// you can filter and remove the backlink with an add_filter function
			// from your themes (or child themes) functions.php file if you dont want to edit this file
			// you can also just keep that link. I really do appreciate it ;)
			$kriesi_at_backlink = "Powered by <a href='http://goozmo.com'>Goozmo</a> Systems. Printed on Recycled Data.";

			//you can also remove the kriesi.at backlink by adding [nolink] to your custom copyright field in the admin area
			if($copyright && strpos($copyright, '[nolink]') !== false)
			{
				$kriesi_at_backlink = "";
				$copyright = str_replace("[nolink]","",$copyright);
			}

			if( $footer_widget_setting != 'nosocket' )
			{

		?>
		<footer class='container_wrap socket_color' id='socket' <?php avia_markup_helper(array('context' => 'footer')); ?>>
			<div class='container'>
				<span class='copyright'><?php echo $copyright . $kriesi_at_backlink; ?></span>
				<?php
					if(avia_get_option('footer_social', 'disabled') != "disabled")
					{
						$social_args 	= array('outside'=>'ul', 'inside'=>'li', 'append' => '');
						echo avia_social_media_icons($social_args, false);
    				}

					echo "<nav class='sub_menu_socket' ".avia_markup_helper(array('context' => 'nav', 'echo' => false)).">";
					$avia_theme_location = 'avia3';
					$avia_menu_class = $avia_theme_location . '-menu';

					$args = array(
						'theme_location'=>$avia_theme_location,
						'menu_id' =>$avia_menu_class,
						'container_class' =>$avia_menu_class,
						'fallback_cb' => '',
						'depth'=>1
					);
					wp_nav_menu($args);
					echo "</nav>";
				?>
			</div>
			<!-- ####### END SOCKET CONTAINER ####### -->
		</footer>
	</div>
	<!-- ####### END FOOTER CONTAINER ####### -->
</div>
<?php   } //endif nofooterwidgets ?>



			

			

				


			<?php
			} //end nosocket check


		
		
		} //end blank & nofooterarea check
		?>
		<!-- end main -->
		</div>
		
		<?php
		//display link to previeous and next portfolio entry
		echo avia_post_nav();

		echo "<!-- end wrap_all --></div>";


		if(isset($avia_config['fullscreen_image']))
		{ ?>
			<!--[if lte IE 8]>
			<style type="text/css">
			.bg_container {
			-ms-filter:"progid:DXImageTransform.Microsoft.AlphaImageLoader(src='<?php echo $avia_config['fullscreen_image']; ?>', sizingMethod='scale')";
			filter:progid:DXImageTransform.Microsoft.AlphaImageLoader(src='<?php echo $avia_config['fullscreen_image']; ?>', sizingMethod='scale');
			}
			</style>
			<![endif]-->
		<?php
			echo "<div class='bg_container' style='background-image:url(".$avia_config['fullscreen_image'].");'></div>";
		}
	?>


<?php




	/* Always have wp_footer() just before the closing </body>
	 * tag of your theme, or you will break many plugins, which
	 * generally use this hook to reference JavaScript files.
	 */


	wp_footer();


?>
<a href='#top' title='<?php _e('Scroll to top','avia_framework'); ?>' id='scroll-top-link' <?php echo av_icon_string( 'scrolltop' ); ?>><span class="avia_hidden_link_text"><?php _e('Scroll to top','avia_framework'); ?></span></a>

<div id="fb-root"></div>

<script>
var footer_fix = {
	
	windowHeight : 0, bodyHeight : 0,
	
	init : function(){
		
		this.bodyHeight = document.getElementsByTagName('body')[0].offsetHeight;
		this.windowHeight = window.innerHeight;
		var footerEle = document.getElementById( 'footer' );
		
		// console.log( this.bodyHeight + "  " + this.windowHeight );
		
		if( this.bodyHeight < this.windowHeight ){
			diff = this.windowHeight - this.bodyHeight;
			// console.log( diff );
			// console.log( footerEle.offsetHeight );
			footer.style.height = (footerEle.offsetHeight + diff + 150) + "px";
		}
		
		if( document.getElementsByClassName('home') ){	
			if( document.getElementById( 'av_section_1' ) ){
				try{
					var goo_ele = document.getElementById( 'wrap_all' );
					var new_ele = document.createDocumentFragment();
				
					var new_ele_div = document.createElement( 'div' );
					new_ele_div.setAttribute('style', "width:100%; height: 10px; background-image: url(/wp-content/themes/mentorsmasters-child/img/border-blue.png);z-index: 100;clear:both; margin-top:10px; background-repeat: repeat-x");
			
					new_ele.appendChild( new_ele_div );
					goo_ele.insertBefore( new_ele, document.getElementById( 'after_section_1' ));
				}
				catch( error ){
					console.log( error );
				}
			}
			
			if( document.getElementById( 'av_section_2' ) ){
				try{
					var goo_ele = document.getElementById( 'wrap_all' );
					var new_ele = document.createDocumentFragment();
				
					var new_ele_div = document.createElement( 'div' );
					new_ele_div.setAttribute('style', "width:100%; height: 10px; background-image: url(/wp-content/themes/mentorsmasters-child/img/border-green.png);z-index: 100;");
			
					new_ele.appendChild( new_ele_div );
					goo_ele.insertBefore( new_ele, document.getElementById( 'av_section_2' ));
				}
				catch( error ){
					console.log( error );
				}
			}
		}
		
	}
	
}

footer_fix.init();


</script>

<script>
	
jQuery(document).ready(function() {
	jQuery("input:text").focus(function() {
		jQuery(this).select(); 
	});
	jQuery('input:text').mouseup(function(e) { 
		return false; 
	});
});
	
</script>

</body>
</html>