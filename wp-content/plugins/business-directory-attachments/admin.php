<?php

if ( !class_exists( 'WPBDP_ListingAttachmentsModule' ) ) {

class WPBDP_ListingAttachmentsModule_Admin {

	public function __construct() {
        add_action( 'admin_init', array( &$this, 'admin_init' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'save_post', array( &$this, 'save_post') );
        add_action( 'wp_ajax_wpbdp-attachments-upload', array( &$this, 'attachments_ajax_upload' ) );

        add_action( 'wpbdp_admin_fee_form_extra_settings', array( &$this, 'attachments_fee_config' ) );
        add_action( 'wpbdp_fee_before_save', array( &$this, 'attachments_fee_config_save' ) );
	}

    public function admin_init() {
        if ( !current_user_can( 'administrator' ) )
            return;

        add_meta_box( 'wpbdp-listing-attachments',
                     __( 'Listing Attachments', 'wpbdp-attachments' ),
                     array( $this, 'admin_attachments_metabox' ),
                     WPBDP_POST_TYPE,
                     'side',
                     'low' );
    }

    public function enqueue_scripts() {
        wp_enqueue_script( 'wpbdp-attachments-admin', plugins_url( '/resources/attachments.min.js', __FILE__ ), array( 'jquery' ) );
        wp_enqueue_style( 'wpbdp-attachments-admin', plugins_url( '/resources/admin.min.css', __FILE__ ) );        
    }

    public function admin_attachments_metabox( $post ) {
    	$attachments = WPBDP_ListingAttachmentsModule::get_attachments( $post->ID );

        echo '<div class="add-attachment">';
        printf( '<a id="add-listing-attachment-link" href="%s" class="thickbox button">%s</a>',
                add_query_arg( array( 'action' => 'wpbdp-attachments-upload',
                                      'listing_id' => $post->ID,
                                      'width' => '400',
                                      'height' => '300',
                                      'TB_iframe' => 1 ),
                                admin_url( 'admin-ajax.php' ) ),
                __( 'Add Attachment', 'wpbdp-attachments' ) );
        echo '</div>';

        echo '<div class="listing-attachments">';
        echo '<div class="empty" style="' . ( !empty( $attachments ) ? 'display: none;' : '' ) .  '">';
        _e( 'This listing has no attachments.', 'wpbdp-attachments' );
        echo '</div>';

        foreach ( $attachments as &$attachment )
            echo $this->theme_listing_attachment( $attachment );
        echo '</div>';

        echo '<br class="clear" />';
    }

    public function save_post( $listing_id ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
            return;

        if ( !is_admin() || !isset( $_POST['post_type'] ) || $_POST['post_type'] != WPBDP_POST_TYPE || !isset( $_POST['attachment-remove'] ) )
            return;

        $attachment_key = trim( $_POST['attachment-remove'] );
        $attachments = get_post_meta( $listing_id, '_wpbdp[attachments]', true );

        if ( isset( $attachments[ $attachment_key ] ) ) {
            @unlink( $attachments[ $attachment_key ]['path'] );
            unset( $attachments[ $attachment_key ] );
            
            update_post_meta( $listing_id, '_wpbdp[attachments]', $attachments );
        }

    }

    /**
     * Renders a listing attachment line for the attachments admin metabox.
     * @param object $attachment
     * @return string HTML output
     */
    public function theme_listing_attachment( &$attachment ) {
        $html  = '';
        $html .= '<div class="listing-attachment">';
        $html .= sprintf( '<strong><a href="%s" target="_blank">%s</a></strong>',
                            esc_url( $attachment['url'] ),
                            esc_attr( $attachment['file']['name'] )
                          );
        $html .= '<br />';
        $html .= sprintf( '<a href="#" class="delete-link" data-attachment-key="%s">%s</a>',
                          $attachment['key'],
                          __( 'Delete', 'wpbdp-attachments' ) );
        $html .= size_format( filesize( realpath( $attachment['path'] ) ), 2 );
        $html .= '</div>';

        return $html;
    }

    public function attachments_ajax_upload() {
        if ( !current_user_can( 'administrator' ) )
            return;

        $mimetypes = array();
        foreach ( $this->module->get_supported_extensions( 'ext=>mime' ) as $ext => $ext_types ) {
            $mimetypes = array_merge( $mimetypes, $ext_types );
        }

        $success = false;
        $errors = array();
        $upload = array();

        if ( wpbdp_media_upload_check_env( $error ) ) {
            if ( isset( $_POST['listing_id'] ) ) {
                $listing_id = intval( $_POST['listing_id'] );

                if ( $listing_id > 0 && isset( $_FILES['file'] ) && $_FILES['file']['error'] == 0 ) {
                    $upload = array( 'file' => $_FILES['file'],
                                     'description' => trim( $_POST['description'] ) );

                    if ( $bd_upload = wpbdp_media_upload( $upload['file'], false, false, array( 'mimetypes' => $mimetypes ), $upload_error ) ) {
                        $upload['path'] = $bd_upload['file'];
                        $upload['url'] = $bd_upload['url'];
                        $upload['key'] = md5( $upload['path'] . '/' . time() );

                        $attachments = WPBDP_ListingAttachmentsModule::get_attachments( $listing_id );
                        $attachments[ $upload['key'] ] = $upload;
                        update_post_meta( $listing_id, '_wpbdp[attachments]', $attachments );

                        $success = true;
                    } else {
                        $errors[] = $upload_error;
                    }
                } else {
                    $errors[] = __( 'Please upload a valid file.', 'wpbdp-attachments' );
                }
            }
        } else {
            $errors[] = $error;            
        }

        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/admin-ajax-upload.tpl.php',
                                array(
                                    'success' => $success,
                                    'errors' => $errors,
                                    'upload' => $upload,
                                    'upload_html' => $upload && $success ? $this->theme_listing_attachment( $upload ) : null
                                ) );

        die();
    }

    /*
     * Fee-specific config.
     */

    public function attachments_fee_config( &$fee ) {
        echo '<h3>' . __( 'Fee-Specific Attachment Settings', 'wpbdp-attachments' ) . '</h3>';

        if ( class_exists( 'WPBDP_FeaturedLevelsModule' ) ) {
            $msg = __( 'You have the "Featured Levels" module installed. To configure fee or level specific settings for the Attachments module go to <a>Manage Restrictions</a>.', 'wpbdp-attachments' );
            echo str_replace( array( '<a>', '</a>' ),
                              array( '<a href="' . admin_url( 'admin.php?page=wpbdp-restrictions&group=premium' )  . '">', '' ),
                              $msg );
            return;
        }


        $config = array(
            'mode' => 'defaults',
            'count' => intval( wpbdp_get_option( 'attachments-count' ) ),
            'maxsize' => intval( wpbdp_get_option( 'attachments-maxsize' ) )
        );

        if ( $fee && isset( $fee->extra_data['attachments'] ) ) {
            $config = $fee->extra_data['attachments'];
        } elseif ( isset( $_POST['attachments'] ) && isset( $_POST['attachments']['mode'] ) && $_POST['attachments']['mode'] == 'custom' ) {
            $config = $_POST['attachments'];
            $config['count'] = abs( intval( $_POST['attachments']['count'] ) );
            $config['maxsize'] = abs( intval( $_POST['attachments']['maxsize'] ) );
        }

        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/admin-fee-config.tpl.php',
                                array(
                                    'config' => $config
                                ) ); 
    }

    public function attachments_fee_config_save( &$fee ) {
        if ( class_exists( 'WPBDP_FeaturedLevelsModule' ) )
            return;

        if ( isset( $fee['extra_data']['attachments'] ) && ( !isset( $_POST['attachments'] ) || !isset( $_POST['attachments']['mode'] ) || $_POST['attachments']['mode'] != 'custom' ) ) {
            unset( $fee['extra_data']['attachments'] );
            return;
        }

        $post_values = isset( $_POST['attachments'] ) ? $_POST['attachments'] : array();

        if ( !$post_values )
            return;

        $mode = wpbdp_getv( $post_values, 'mode', 'defaults' );
        $count = abs( intval( wpbdp_getv( $post_values, 'count', 0 ) ) );
        $maxsize = abs( intval( wpbdp_getv( $post_values, 'maxsize', 0 ) ) );

        if ( $mode != 'custom' )
            return;
        
        $fee['extra_data']['attachments'] = compact( 'mode', 'count', 'maxsize' );
    }

}


}
