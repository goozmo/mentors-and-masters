<?php
/*
	*
	*	book connection meta custom fields
	*
	*
	*
	*
	*
*/

if ( is_admin() ) {
   $request = new CM_events_meta();
}



class CM_events_meta{
	

/*
	*
	*
	*
	*
*/
	public function __construct(){
		
		add_action( 'add_meta_boxes', array( $this, 'add_meta' ));
		add_action( 'save_post', array( $this, 'save_meta' ));
		
	}

/*
	*
	*
	*
	*
*/
	public function add_meta( $post_type ){
		
		$post_types = array( 'cmevents' );     // limit to certain post types
		
		// reference array & apply wp add_meta_box
		if ( in_array( $post_type, $post_types )) {
			add_meta_box(
				'cm_events_meta',
				__( 'Mentors & Masters Event Information' ),
				array( $this, 'render_meta' ),
				$post_type,
				'advanced',
				'high'
			);
		}
	}

/*
	*
	*
	*
	*
*/	
	public function save_meta( $post_id ){
		
		/*
			* We need to verify this came from our screen and with proper authorization,
			* because the save_post action can be triggered at other times.
		*/
		
		// Check if our nonce is set.
		
		if ( ! isset( $_POST['cm_events_meta_box_nonce'] ) ) {
			return;
		}
		
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['cm_events_meta_box_nonce'], 'cm_events_meta_box' ) ) {
			return;
		}
		
		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		
		// Check the user's permissions.
		if ( isset( $_POST['post_type'] ) && 'page' == $_POST['post_type'] ) { 
			if ( ! current_user_can( 'edit_page', $post_id ) ) {
				return;
			}
		} else { 
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}
		}
				
		foreach( $_POST as $key=>$value ){
			if( preg_match( '/^(_goo)/', $key )){
				$data = htmlspecialchars( $value );
				update_post_meta( $post_id, $key, $data );
			}
		}
		
	}

/*
	*
	*
	*
	*
*/
	public function render_meta( $post ){
		
		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'cm_events_meta_box', 'cm_events_meta_box_nonce' );
		
		/*
			*
			*
			*
		*/
		$_pre_cmevent_start = get_post_meta( $post->ID, '_goo_cmevent_start', true );
		$_pre_cmevent_end = get_post_meta( $post->ID, '_goo_cmevent_end', true );
		
		$_pre_cmevent_address_street = get_post_meta( $post->ID, '_goo_cmevent_address_street', true );
		$_pre_cmevent_address_city = get_post_meta( $post->ID, '_goo_cmevent_address_city', true );
		$_pre_cmevent_address_venue = get_post_meta( $post->ID, '_goo_cmevent_address_venue', true );
		$_pre_cmevent_address_state = get_post_meta( $post->ID, '_goo_cmevent_address_state', true );
		$_pre_cmevent_address_zip = get_post_meta( $post->ID, '_goo_cmevent_address_zip', true );
		$_pre_cmevent_address_country = get_post_meta( $post->ID, '_goo_cmevent_address_country', true );
		
		// begin main container
		echo "<div id='_goo-book-conn-meta-container'>";
		//echo "the category is: ";
		//echo "<pre>";
		//print_r( get_the_category());
		//echo "</pre>";
		
		
		?>
		<label for="_goo_cmevent_start"><?php _e( 'Start Date:' ); ?></label> <br/><br/>
		<input type="datetime-local" id="_goo_cmevent_start" name="_goo_cmevent_start" value="<?php echo esc_attr( $_pre_cmevent_start ); ?>" /><br/><br/>
		
		<label for="_goo_cmevent_end"><?php _e( 'End Date:' ); ?></label> <br/><br/>
		<input type="datetime-local" id="_goo_cmevent_end" name="_goo_cmevent_end" value="<?php echo esc_attr( $_pre_cmevent_end ); ?>" /><br/><br/>
		
		<label for="_goo_cmevent_address_street"><?php _e( 'Street Address:' ); ?></label> <br/><br/>
		<input type="text" id="_goo_cmevent_address_street" name="_goo_cmevent_address_street" value="<?php echo esc_attr( $_pre_cmevent_address_street ); ?>" /><br/><br/>
		
		<label for="_goo_cmevent_address_city"><?php _e( 'City:' ); ?></label> <br/><br/>
		<input type="text" id="_goo_cmevent_address_city" name="_goo_cmevent_address_city" value="<?php echo esc_attr( $_pre_cmevent_address_city ); ?>" /><br/><br/>
		
		<label for="_goo_cmevent_address_venue"><?php _e( 'Venue:' ); ?></label> <br/><br/>
		<input type="text" id="_goo_cmevent_address_venue" name="_goo_cmevent_address_venue" value="<?php echo esc_attr( $_pre_cmevent_address_venue ); ?>" /><br/><br/>
		
		<label for="_goo_cmevent_address_state"><?php _e( 'State:' ); ?></label> <br/><br/>
		<input type="text" id="_goo_cmevent_address_state" name="_goo_cmevent_address_state" value="<?php echo esc_attr( $_pre_cmevent_address_state ); ?>" /><br/><br/>
		
		<label for="_goo_cmevent_address_zip"><?php _e( 'Zip Code:' ); ?></label> <br/><br/>
		<input type="text" id="_goo_cmevent_address_zip" name="_goo_cmevent_address_zip" value="<?php echo esc_attr( $_pre_cmevent_address_zip ); ?>" /><br/><br/>
		
		<label for="_goo_cmevent_address_country"><?php _e( 'Country:' ); ?></label> <br/><br/>
		<input type="text" id="_goo_cmevent_address_country" name="_goo_cmevent_address_country" value="<?php echo esc_attr( $_pre_cmevent_address_country ); ?>" /><br/><br/>
		
		<?php
			
		echo '<div style="clear:both;width:100%;"></div>';
		echo "</div>";
	}
	
}