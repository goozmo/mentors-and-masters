<?php
/*
 * Plugin Name: Business Directory Plugin - File Attachments Module
 * Plugin URI: http://www.businessdirectoryplugin.com
 * Version: 3.6
 * Author: D. Rodenbaugh
 * Description: Adds ability for you to upload files and attach them to listings.
 * Author URI: http://www.skylineconsult.com
*/

require_once( plugin_dir_path( __FILE__ ) . 'admin.php' );


if ( !class_exists( 'WPBDP_ListingAttachmentsModule' ) ) {

class WPBDP_ListingAttachmentsModule {

    const VERSION = '3.6';
    const REQUIRED_BD_VERSION = '3.5.2';

    public function __construct() {
        add_action( 'plugins_loaded', array( &$this, 'initialize' ) );
        add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
    }

    public function initialize() {
        $this->load_i18n();

        if ( ! defined( 'WPBDP_VERSION' ) || version_compare( WPBDP_VERSION, self::REQUIRED_BD_VERSION, '<' ) )
            return;

        if ( ! wpbdp_licensing_register_module( 'File Attachments Module', __FILE__, self::VERSION ) )
           return;

        add_action( 'wpbdp_modules_init', array( $this, 'init' ) );
        add_action( 'wpbdp_enqueue_scripts', array( &$this, '_enqueue_scripts' ) );

        add_filter( 'wpbdp_listing_form_attachments_config', array( &$this, 'fee_attachments_config' ), 0, 2 );

        $this->admin = new WPBDP_ListingAttachmentsModule_Admin();
        $this->admin->module = $this;
    }

    public function admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( function_exists( 'wpbdp_get_version' ) && version_compare( wpbdp_get_version(), self::REQUIRED_BD_VERSION, '>=' ) ) {
        } else {
            echo sprintf( __( '<div class="error"><p>Business Directory - File Attachments Module requires Business Directory Plugin >= %s.</p></div>',
            				  'wpbdp-attachments' ),
            			  WPBDP_ListingAttachmentsModule::REQUIRED_BD_VERSION );
        }
    }

    public function load_i18n() {
        load_plugin_textdomain( 'wpbdp-attachments', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );        
    }    

    public function _enqueue_scripts() {
        wp_enqueue_script( 'wpbdp-attachments', plugins_url( '/resources/attachments.min.js', __FILE__ ), array( 'jquery' ) );
        wp_enqueue_style( 'wpbdp-attachments', plugins_url( '/resources/styles.min.css', __FILE__ ) );
    }    

    public function init() {
        $settings = wpbdp_settings_api();

        $s = $settings->add_section( 'listings', 'listing-attachments', __( 'Attachments', 'wpbdp-attachments' ) );
        $settings->add_setting( $s,
                                'attachments-enabled',
                                __( 'Enable listing attachments', 'wpbdp-attachments' ),
                                'boolean',
                                true );
        $settings->add_setting( $s,
                                'attachments-header',
                                __( 'Attachments Header Text', 'wpbdp-attachments' ),
                                'text',
                                __( 'Listing Attachments', 'wpbdp-attachments' ),
                                __( 'Customize the header text that appears during the submit and on listings.', 'wpbdp-attachments' ) );
        $settings->add_setting( $s,
                                'attachments-icons',
                                __( 'Enable icons for attachments?', 'wpbdp-attachments' ),
                                'boolean',
                                true );
        $settings->add_setting( $s,
                                'attachments-count',
                                __( 'Maximum number of attachments per listing', 'wpbdp-attachments' ),
                                'text',
                                '5' );
        $settings->add_setting( $s,
                                'attachments-maxsize',
                                __( 'Maximum attachment size (in KB)', 'wpbdp-attachments' ),
                                'text',
                                '5000' );
        $settings->add_setting( $s,
                                'attachments-allowed-types',
                                __( 'Allowed File Types', 'wpbdp-attachments' ),
                                'choice',
                                array( 'pdf' ),
                                '',
                                array( 'choices' => array(
                                        'pdf' => 'PDF',
                                        'png' => 'PNG',
                                        'jpg' => 'JPG',
                                        'gif' => 'GIF',
                                        'rtf' => 'RTF',
                                        'txt' => 'TXT'
                                       ),
                                       'use_checkboxes' => true,
                                       'multiple' => true )
                              );


    	if ( !wpbdp_get_option( 'attachments-enabled' ) )
    		return;   

        add_action( 'wpbdp_submit_state_init', array( $this, 'load_attachments' ) );
        add_action( 'wpbdp_listing_form_extra_sections', array( &$this, 'dispatch' ) );
        add_action( 'wpbdp_listing_form_extra_sections_save', array( &$this, 'save_attachments' ) );
        
        add_filter( 'wpbdp_single_listing_fields', array( $this, 'display_attachments' ), 21, 2 );
        add_filter( 'wpbdp_listing_template_vars', array( &$this, 'include_attachments_in_template' ), 10, 2 );
    }

    public function get_supported_extensions( $q = 'ext' ) {
        static $ext_to_mime = array(
            'pdf' => array( 'application/pdf', 'application/x-pdf', 'application/vnd.pdf' ),
            'png' => array( 'image/png' ),
            'jpg' => array( 'image/jpg', 'image/jpeg', 'image/pjpeg' ),
            'gif' => array( 'image/gif' ),
            'rtf' => array( 'application/rtf', 'application/x-rtf', 'text/richtext', 'text/rtf' ),
            'txt' => array( 'text/plain' )
        );

        if ( $q == 'ext' )
            return array_keys( $ext_to_mime );

        return $ext_to_mime;
    }

    public function get_attachments_config() {
        $ext_to_mime = $this->get_supported_extensions( 'ext=>mime' );

        $mimetypes = array();
        $extensions = array();
        
        foreach ( wpbdp_get_option( 'attachments-allowed-types' ) as $ext ) {
            if ( isset( $ext_to_mime[ $ext ] ) )
                $mimetypes = array_merge( $mimetypes, $ext_to_mime[ $ext ] );
            $extensions[] = $ext;
        }

        $config = array(
            'enabled' => wpbdp_get_option( 'attachments-enabled' ),
            'limit' => max( 0, intval( wpbdp_get_option( 'attachments-count' ) ) ),
            'mimetypes' => $mimetypes,
            'extensions' => $extensions,
            'filesize' => intval( wpbdp_get_option( 'attachments-maxsize' ) ) * 1024
        );

        return $config;
    }

    public function load_attachments( &$state ) {
        $attachments = get_post_meta( $state->listing_id, '_wpbdp[attachments]', true );
        $attachments = !$attachments || !is_array( $attachments) ? array() : $attachments;

        $state->extra['attachments'] = $attachments;
    }

    public function fee_attachments_config( $config, $state ) {
        if ( class_exists( 'WPBDP_FeaturedLevelsModule' ) )
            return $config;

        $fees = array_map( 'wpbdp_get_fee', array_values( $state->categories ) );

        $limit = 0;
        $filesize = 0;

        foreach ( $fees as &$fee ) {
            if ( isset( $fee->extra_data['attachments'] ) && isset( $fee->extra_data['attachments']['mode'] ) && $fee->extra_data['attachments']['mode'] == 'custom' ) {
                $fee_limit = wpbdp_getv( $fee->extra_data['attachments'], 'count', $config['limit'] );
                $fee_maxsize = wpbdp_getv( $fee->extra_data['attachments'], 'maxsize', $config['filesize'] / 1024 );
            } else {
                $fee_limit = $config['limit'];
                $fee_maxsize = $config['filesize'] / 1024;
            }

            $limit = max( $limit, abs( intval( $fee_limit ) ) );
            $filesize = max( $filesize, abs( intval( $fee_maxsize ) ) );
        }

        $config['limit'] = $limit;
        $config['filesize'] = $filesize * 1024;

        return $config;
    }

    public function dispatch( &$state ) {
        $config = $this->get_attachments_config();
        $config = apply_filters( 'wpbdp_listing_form_attachments_config', $config, $state );

        if ( !$config['enabled'] || $config['limit'] == 0 )
            return;

        if ( !isset( $state->extra['attachments'] ) ) {
            $state->extra['attachments'] = array();
        } else {
            // Clear missing attachments.
            foreach ( $state->extra['attachments'] as $i => &$attachment ) {
                if ( !file_exists( $attachment['path'] ) )
                    unset( $state->extra['attachments'][ $i ] );
            }
        }

        $errors = array();
        $status_message = '';

        // Handle upload.
        if ( isset( $_POST['attachment-upload'] ) && isset( $_FILES['upload_file'] ) && $_FILES['upload_file']['error'] == 0 ) {
            // TODO: do not re-upload recently uploaded files.
            if ( $config['limit'] > count( $state->extra['attachments'] ) ) {
                $upload = array( 'file' => $_FILES['upload_file'],
                                 'description' => trim( $_POST['upload_description'] ) );

                $constraints = array(
                    'max-size' => $config['filesize'],
                    'mimetypes' => $config['mimetypes']
                );

                if ( $bd_upload = wpbdp_media_upload( $upload['file'], false, false, $constraints, $upload_err ) ) {
                    $upload['path'] = $bd_upload['file'];
                    $upload['url'] = $bd_upload['url'];
                    $upload['key'] = md5( $upload['path'] . '/' . time() );

                    $state->extra['attachments'][ $upload['key'] ] = $upload;
                    $status_message = sprintf( __( 'File "%s" was uploaded successfully.', 'wpbdp-attachments' ), esc_attr( $upload['file']['name'] ) );
                } else {
                    $errors[] = sprintf( __( 'An error was found while uploading your file: %s.', 'wpbdp-attachments' ), $upload_err );
                }
            } else {
                $errors[] = __( 'You have reached your attachment limit. You can remove existing attachments to make space for new ones.', 'wpbdp-attachments' );
            }
        } elseif ( isset( $_POST['attachment-remove'] ) ) {
            $key = trim( $_POST['attachment-remove'] );

            if ( isset( $state->extra['attachments'][ $key ] ) ) {
                @unlink( $state->extra['attachments'][ $key ]['path'] );
                unset( $state->extra['attachments'][ $key ] );

                $status_message = __( 'Attachment deleted.', 'wpbdp-attachments' );
            }
        }

        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/listing-attachments.tpl.php',
                                array( 'errors' => $errors,
                                       'status' => $status_message,
                                       'state' => $state,
                                       'config' => $config )
                              );
    }

    public function save_attachments( &$state ) {
        $prev_attachments = get_post_meta( $state->listing_id, '_wpbdp[attachments]', true );
        $prev_attachments = $prev_attachments ? $prev_attachments : array();

        $attachments = isset( $state->extra['attachments'] ) ? $state->extra['attachments'] : array();

        // Delete unused old attachments.
        foreach ( $prev_attachments as &$attachment ) {
            if ( ! array_key_exists( $attachment['key'], $attachments ) )
                @unlink( $attachment['path'] );
        }

        update_post_meta( $state->listing_id, '_wpbdp[attachments]', $attachments );
    }

    /**
     * Displays attachments on a listing's single view.
     * Callback for `wpbdp_single_listing_fields` filter.
     */
    public function display_attachments( $html, $listing_id ) {
        $config = apply_filters( 'wpbdp_listing_attachments_config', $this->get_attachments_config(), $listing_id );
        $attachments = get_post_meta( $listing_id, '_wpbdp[attachments]', true );

        if ( !$config['enabled'] || !$attachments )
            return $html;

        $this->maybe_add_attachments_icons( $attachments );

        $html .= wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/attachments-display.tpl.php',
                                    array( 'attachments' => $attachments ) );

        return $html;
    }

    function include_attachments_in_template( $vars, $listing_id ) {
        $config = apply_filters( 'wpbdp_listing_attachments_config', $this->get_attachments_config(), $listing_id );

        if ( ! $config['enabled'] )
            $attachments = array();
        else
            $attachments = get_post_meta( $listing_id, '_wpbdp[attachments]', true );

        $vars['attachments'] = (object) array( 'html' => $this->display_attachments( '', $listing_id ),
                                               'data' => $this->maybe_add_attachments_icons( $attachments ) );
        return $vars;
    }

    /**
     * Adds icon URLs for all attachments (if the setting is enabled).
     * @since 3.5.1
     */
    private function maybe_add_attachments_icons( &$attachments ) {
        if ( ! $attachments || ! wpbdp_get_option( 'attachments-icons' ) )
            return $attachments;

        static $ICONS = array(
            'default' => 'appbar.page.png',
            'application/pdf' => 'appbar.page.pdf.png',
            'application/rtf' => 'appbar.book.open.text.image.png',
            'application/x-rtf' => 'appbar.book.open.text.image.png',
            'text/richtext' => 'appbar.book.open.text.image.png',
            'text/rtf' => 'appbar.book.open.text.image.png',
            'text/plain' => 'appbar.page.text.png',
            'image/png' => 'appbar.page.image.png',
            'image/jpg' => 'appbar.page.image.png',
            'image/jpeg' => 'appbar.page.image.png',
            'image/pjpeg' => 'appbar.page.image.png',
            'image/gif' => 'appbar.page.image.png'
        );

        foreach ( $attachments as &$attachment ) {
            $mimetype = $attachment['file']['type'];

            if ( isset( $ICONS[ $mimetype ] ) ) {
                $attachment['icon'] = plugin_dir_url( __FILE__ ) . 'resources/icons/' . $ICONS[ $mimetype ];
            } else {
                $attachment['icon'] = plugin_dir_url( __FILE__ ) . 'resources/icons/' . $ICONS[ 'default' ];
            }
        }

        return $attachments;
    }

    /**
     * Obtains the list of attachments for a given listing.
     * @param int $listing_id the listing ID
     * @return array list of attachment items (associative array with keys: )
     */
    public static function get_attachments( $listing_id ) {
        $attachments = get_post_meta( $listing_id, '_wpbdp[attachments]', true );

        if ( !$attachments )
            return array();

        return $attachments;
    }


}

new WPBDP_ListingAttachmentsModule();

}
