<?php
/*
	*
	*	http redirect field
	*
	*
	*
	*
	*
*/

if ( is_admin() ) {
   $request = new Http_redirect_meta();
}



class Http_redirect_meta{
	

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
		
		$post_types = array( 'post', 'page', 'newsandpress' );     // limit to certain post types
		
		// reference array & apply wp add_meta_box
		//if ( in_array( $post_type, $post_types )) {
			add_meta_box(
				'goo_http_redirect_meta',
				__( 'Http 301 redirect' ),
				array( $this, 'render_meta' ),
				NULL,
				'advanced',
				'low',
				NULL
			);
		//}
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
		
		if ( ! isset( $_POST['http_redirect_meta_box_nonce'] ) ) {
			return;
		}
		
		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $_POST['http_redirect_meta_box_nonce'], 'http_redirect_meta_box' ) ) {
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
		wp_nonce_field( 'http_redirect_meta_box', 'http_redirect_meta_box_nonce' );
		
		/*
			*
			*
			*
		*/
		$_pre_artist_statement = get_post_meta( $post->ID, '_goo_http_redirect_url', true );
		
		
		// begin main container
		echo "<div id='_goo-artist-meta-container'>";
		//echo "the category is: ";
		//echo "<pre>";
		//print_r( get_the_category());
		//echo "</pre>";
		
		/*
			* artist statement
			*
		*/
		?>
		<label for="_goo_http_redirect_url"><?php _e( '301 Redirect URL' ); ?></label> <br/><br/>
		<input id="_goo_http_redirect_url" name="_goo_http_redirect_url" style="width: 100%; resize: none;" value="<?php echo esc_attr( $_pre_artist_statement ); ?>" /><br/><br/>
		
		<?php	
			
		echo '<div style="clear:both;width:100%;"></div>';
		echo "</div>";
	}
	
}