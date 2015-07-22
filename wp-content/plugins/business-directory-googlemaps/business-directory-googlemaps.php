<?php
/*
 * Plugin Name: Business Directory Plugin - Google Maps Module
 * Description: Adds support for Google Maps for display in a Business Directory listing.  Allows you to map any set of fields to the address for use by Google Maps.  REQUIRES Business Directory 3.0 or higher to run.
 * Plugin URI: http://www.businessdirectoryplugin.com
 * Version: 3.6
 * Author: D. Rodenbaugh
 * Author URI: http://businessdirectoryplugin.com
 * License: GPLv2 or any later version
 */

class BusinessDirectory_GoogleMapsPlugin {

    const VERSION = '3.6';
    const REQUIRED_BD_VERSION = '3.5.2';

    const GOOGLE_MAPS_JS_URL = 'https://maps.google.com/maps/api/js';

    private $maps_handle = '';
    private $maps_handles_remove = array();

    private $map_locations = array();
    private $doing_map = false;


    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'load_i18n' ) );
        add_action('admin_notices', array($this, '_admin_notices'));
        add_action( 'wpbdp_modules_loaded', array( $this, '_initialize' ) );
    }

    public function fix_scripts_src( $src, $handle ) {
        // Make sure the original src was used (no args removed, etc).
        if ( $this->maps_handle == $handle ) {
            global $wp_scripts;
            return $wp_scripts->registered[ $handle ]->src;
        }

        // Load dummy JS for other instances of Google Maps API, as to not break dependencies.
        if ( in_array( $handle, $this->maps_handles_remove, true ) )
            return plugins_url( '/resources/dummy.js', __FILE__ );

        return $src;
    }

    public function load_i18n() {
        load_plugin_textdomain( 'wpbdp-googlemaps', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );
    }

    public function _initialize() {
        if ( version_compare( WPBDP_VERSION, self::REQUIRED_BD_VERSION, '<' ) )
            return;

        if ( ! wpbdp_licensing_register_module( 'Google Maps Module', __FILE__, self::VERSION ) )
           return;

        add_action( 'wpbdp_register_settings', array( &$this, 'register_settings' ) );
        add_action( 'wpbdp_modules_init', array( &$this, '_setup_actions' ) );
    }

    public function _setup_actions() {
        if ( !wpbdp_get_option( 'googlemaps-on' ) )
            return;

        add_action( 'wp_enqueue_scripts', array( &$this, 'enqueue_scripts' ), 9999 ); // We run with a huge priority in order to be last.
        add_action( 'script_loader_src', array( &$this, 'fix_scripts_src' ), 9999, 2 );
        add_action( 'save_post', array( $this, 'update_listing_geolocation' ), 10, 1 );
        add_action( 'wpbdp_save_listing', array( &$this, 'update_listing_geolocation' ), 10, 1 );
        add_filter( 'wpbdp_listing_view_after', array( $this, 'add_map_to_listing' ), 10, 2 );

        add_action( 'wpbdp_listing_form_extra_sections', array( &$this, '_show_place_chooser' ) );
        add_action( 'wpbdp_listing_form_extra_sections_save', array( &$this, '_save_location_override' ) );

        if ( wpbdp_get_option( 'googlemaps-show-category-map' ) )
            add_action( 'wpbdp_after_category_page', array( $this, '_category_map' ) );

        if ( wpbdp_get_option( 'googlemaps-show-viewlistings-map' ) )
            add_action( 'wpbdp_after_viewlistings_page', array( $this, '_view_listings_map' ) );

        if ( wpbdp_get_option( 'googlemaps-show-search-map' ) )
            add_action( 'wpbdp_after_search_results', array( $this, '_search_map' ) );
    }

    public function enqueue_scripts() {
        global $wpbdp;

        if ( method_exists( $wpbdp, 'is_plugin_page' ) && ! $wpbdp->is_plugin_page() )
            return;

        $key = wpbdp_get_option( 'googlemaps-apikey' );
        wp_enqueue_style( 'wpbdp-googlemaps-css', plugins_url( '/resources/googlemaps' . ( $wpbdp->is_debug_on() ? '' : '.min' ) . '.css', __FILE__ ) );

        $this->obtain_google_maps_handle();
        if ( ! $this->maps_handle ) {
            wp_register_script( 'googlemaps-api',
                                self::GOOGLE_MAPS_JS_URL . '?' . ( $key ? 'key=' . $key . '&' : '' ) . 'sensor=false',
                                null,
                                null );
            $this->maps_handle = 'googlemaps-api';
        }
        wp_register_script( 'oms-js',
                            plugins_url( '/resources/oms.min.js', __FILE__ ),
                            array( $this->maps_handle ) );

        wp_enqueue_script( 'wpbdp-googlemaps-js',
                           plugins_url( '/resources/googlemaps' . ( $wpbdp->is_debug_on() ? '' : '.min' ) . '.js', __FILE__ ),
                           array( 'jquery', 'oms-js' ) );

        if ( wpbdp_get_option( 'googlemaps-fields-latlong-enabled' ) ) {
            wp_enqueue_style( 'wpbdp-googlemaps-place-chooser-css',
                               plugins_url( '/resources/place-chooser' . ( $wpbdp->is_debug_on() ? '' : '.min' ) . '.css', __FILE__ ) );
            wp_enqueue_script( 'wpbdp-googlemaps-place-chooser-js',
                               plugins_url( '/resources/place-chooser' . ( $wpbdp->is_debug_on() ? '' : '.min' ) . '.js', __FILE__ ),
                               array( 'jquery' ) );
        }

        if ( wpbdp_get_option( 'googlemaps-show-directions' ) ) {
            wp_localize_script( 'wpbdp-googlemaps-js',
                                'WPBDP_googlemaps_directions_l10n',
                                array(
                                    'submit_normal' => _x( 'Show Directions', 'wpbdp-googlemaps' ),
                                    'submit_working' => _x( 'Working...', 'wpbdp-googlemaps' ),
                                    'titles_driving' => _x( 'Driving directions to "%s"', 'wpbdp-googlemaps' ),
                                    'titles_cycling' => _x( 'Cycling directions to "%s"', 'wpbdp-googlemaps' ),
                                    'titles_transit' => _x( 'Public Transit directions to "%s"', 'wpbdp-googlemaps' ),
                                    'walking' => _x( 'Walking directions to "%s"', 'wpbdp-googlemaps' ),
                                    'errors_no_route' => _x( 'Could not find a route from your location.', 'wpbdp-googlemaps' )
                              ) );
            add_thickbox();
        }
    }

    private function obtain_google_maps_handle() {
        global $wp_scripts;
        $candidates = array();

        foreach ( $wp_scripts->registered as $script ) {
            if ( ( ( false !== stripos( $script->src, 'maps.google.com/maps/api' ) || 
                     false !== stripos( $script->src, 'maps.googleapis.com/maps/api' ) ) &&
                     false === stripos( $script->src, 'callback' ) ) &&
                    in_array( $script->handle, $wp_scripts->queue ) ) {
                $candidates[] = $script->handle;
            }
        }

        if ( $candidates ) {
            $this->maps_handle = array_shift( $candidates );
            $this->maps_handles_remove = $candidates;
        }
    }

    public function register_settings($settingsapi) {
        $g = $settingsapi->add_group('googlemaps', _x('Google Maps', 'settings', 'wpbdp-googlemaps'));

        // General settings
        $s = $settingsapi->add_section($g, 'googlemaps', _x('General Settings', 'settings', 'wpbdp-googlemaps'));
        $settingsapi->add_setting($s, 'googlemaps-on', _x('Turn on Google Maps integration?', 'settings', 'wpbdp-googlemaps'), 'boolean', true);
        $settingsapi->add_setting( $s,
                                   'googlemaps-apikey',
                                   _x( 'Google Maps Server API Key (optional)', 'settings', 'wpbdp-googlemaps' ),
                                   'text',
                                   '',
                                   str_replace( '<a>', '<a href="https://code.google.com/apis/console" target="_blank">',
                                                _x('Enter your API key only if you are using Maps for Business or have Automated Billing enabled in your <a>Google Console</a>.',
                                                   'settings',
                                                   'wpbdp-googlemaps' ) ) );
        $settingsapi->add_setting($s, 'googlemaps-show-category-map', _x( 'Show listings map in categories?', 'settings', 'wpbdp-googlemaps' ), 'boolean', false  );
        $settingsapi->add_setting($s, 'googlemaps-show-viewlistings-map', _x( 'Show listings map in "View Listings"?', 'settings', 'wpbdp-googlemaps' ), 'boolean', false  );
        $settingsapi->add_setting($s, 'googlemaps-show-search-map', _x( 'Show listings map in search results?', 'settings', 'wpbdp-googlemaps' ), 'boolean', false  );
        $settingsapi->add_setting( $s,
                                   'googlemaps-listings-on-page',
                                   _x( 'Current page listings to show on map', 'settings', 'wpbdp-googlemaps' ),
                                   'choice',
                                   'all',
                                   '',
                                   array( 'choices' => array( array( 'all', _x( 'All listings', 'settings', 'wpbdp-googlemaps' ) ),
                                                              array( 'page', _x( 'Only visible listings on page', 'settings', 'wpbdp-googlemaps' ) ) ) ) );
        $settingsapi->add_setting( $s,
                                   'googlemaps-show-directions',
                                   _x( 'Allow visitors to get directions to listings?', 'settings', 'wpbdp-googlemaps' ),
                                   'boolean',
                                   false
                                 );
        $settingsapi->add_setting( $s,
                                   'googlemaps-fields-latlong-enabled',
                                   _x( 'Allow users to manually adjust the location of their listings?', 'settings', 'wpbdp-googlemaps' ),
                                   'boolean',
                                   false,
                                   _x( 'Allows users to grab the pin and physically place it where the actual location is, rather than rely on Google\'s geolocation.  Helpful for rural addresses.', 'settings', 'wpbdp-googlemaps' )
                                 );

        // Appearance
        $s = $settingsapi->add_section($g, 'appearance', _x('Appearance', 'settings', 'wpbdp-googlemaps'));
        $settingsapi->add_setting( $s,
                                   'googlemaps-position',
                                   _x( 'Display Map position', 'settings', 'wpbdp-googlemaps' ),
                                   'choice',
                                   'bottom',
                                   _x( 'Applies only to category, "View Listings" and search results maps.', 'settings', 'wpbdp-googlemaps' ),
                                   array( 'choices' => array( array( 'top', _x( 'Above all listings', 'settings', 'wpbdp-googlemaps' ) ),
                                                              array( 'bottom', _x( 'Below all listings', 'settings', 'wpbdp-googlemaps' ) ) ) ) );

        $settingsapi->add_setting($s, 'googlemaps-size', _x('Map Size', 'settings', 'wpbdp-googlemaps'),
                                  'choice', 'auto', null, array('choices' => array(array('small', _x('Small map (250x250px)', 'settings', 'wpbdp-googlemaps')),
                                                                                 array('large', _x('Large map (400x600px)', 'settings', 'wpbdp-googlemaps')),
                                                                                 array('auto', _x('Automatic', 'settings', 'wpbdp-googlemaps')),
                                                                                 array('custom', _x('Custom size', 'settings', 'wpbdp-googlemaps'))
                                                                                 ) ));
        $settingsapi->add_setting( $s, 'googlemaps-size-custom-w', _x( 'Custom map size width (px)', 'settings', 'wpbdp-googlemaps' ), 'text', '250', _x( 'Applies only to the "Custom size" map size', 'settings', 'wpbdp-googlemaps' ) );
        $settingsapi->add_setting( $s, 'googlemaps-size-custom-h', _x( 'Custom map size height (px)', 'settings', 'wpbdp-googlemaps' ), 'text', '250', _x( 'Applies only to the "Custom size" map size', 'settings', 'wpbdp-googlemaps' ) );        
        $settingsapi->add_setting( $s,
                                   'googlemaps-size-auto',
                                   _x( 'Auto-resize map when container is stretched (makes Maps responsive)', 'settings', 'wpbdp-googlemaps' ),
                                   'boolean',
                                   false );

        $settingsapi->add_setting($s, 'googlemaps-maptype', _x('Map Type', 'settings', 'wpbdp-googlemaps'),
                                  'choice', null, null, array('choices' => array(
                                        array('roadmap', _x('Roadmap', 'settings', 'wpbdp-googlemaps')),
                                        array('satellite', _x('Satellite', 'settings', 'wpbdp-googlemaps')),
                                        array('hybrid', _x('Hybrid', 'settings', 'wpbdp-googlemaps')),
                                        array('terrain', _x('Terrain', 'settings', 'wpbdp-googlemaps')),
                                    )));
        $settingsapi->add_setting($s, 'googlemaps-animate-marker', _x('Animate markers', 'settings', 'wpbdp-googlemaps'), 'boolean');

        // Field Options
        $fields_api = wpbdp_formfields_api();
        $s = $settingsapi->add_section( $g,
                                        'googlemaps-fields',
                                        _x( 'Fields for Maps to Use for Location', 'settings', 'wpbdp-googlemaps' ),
                                        'Please select at least one field from your listings to use for location information that maps can use to find a pin on Google Maps.  The more fields you use, the more accurate the pin\'s location.', 'settings', 'wpbdp-googlemaps' );

        $choices = array();
        $choices[] = array('0', _x('-- None --', 'settings', 'wpbdp-googlemaps'));
        
        foreach ( $fields_api->get_fields( true ) as $field ) {
            $choices[] = array( $field->id, esc_attr( $field->label ) );
        }

        foreach (array('googlemaps-fields-address' => _x('address', 'settings', 'wpbdp-googlemaps'),
                       'googlemaps-fields-city' => _x('city', 'settings', 'wpbdp-googlemaps'),
                       'googlemaps-fields-state' => _x('state', 'settings', 'wpbdp-googlemaps'),
                       'googlemaps-fields-zip' => _x('ZIP code', 'settings', 'wpbdp-googlemaps'),
                       'googlemaps-fields-country' => _x('country', 'settings', 'wpbdp-googlemaps')) as $k => $v) {
            $settingsapi->add_setting($s, $k,
                                      sprintf(_x('Use this field as %s:', 'settings', 'wpbdp-googlemaps'), $v),
                                      'choice', null, null, array('choices' => $choices));
        }
    }

    /**
     * Builds the address for a given listing using the current settings.
     * @param int $listing_id the listing ID
     * @param bool $pretty whether to pretty-format the address or not (defaults to FALSE)
     * @return string the listing full address
     */
    public function get_listing_address( $listing_id, $pretty=false ) {
        $settingsapi = wpbdp_settings_api();
        $fieldsapi = wpbdp_formfields_api();

        $address = '';
        foreach ( array( 'address', 'city', 'state', 'zip', 'country' ) as $field_name ) {
            if ( $field_id = wpbdp_get_option( 'googlemaps-fields-' . $field_name ) ) {
                $field = $fieldsapi->get_field( $field_id );

                if ( !$field )
                    continue;

                if ( $value = $field->plain_value( $listing_id ) ) {
                    $address .= $value . ( $pretty ? "\n" : ',' ); // TODO: this probably could be prettier like Address<EOL>City, State<EOL>...
                }
            }
        }

        $address = esc_attr( substr( $address, 0, -1) );

        return trim( $address );
    }

    /**
     * @since 3.5.1
     */
    public function get_address_from_state( $state ) {
        $address = '';

        foreach ( array( 'address', 'city', 'state', 'zip', 'country' ) as $field_name ) {
            $field_id = wpbdp_get_option( 'googlemaps-fields-' . $field_name );

            if ( ! $field_id )
                continue;

            $field = wpbdp_get_form_field( $field_id );

            if ( ! $field )
                continue;

            $value = ( 'category' == $field->get_association() ) ? $state->categories : ( isset( $state->fields[ $field_id ] ) ? $state->fields[ $field_id ] : false );

            if ( ! $value )
                continue;

            switch ( $field->get_association() ) {
                case 'category':
                    $value = get_terms( WPBDP_CATEGORY_TAX, array( 'hide_empty' => 0, 'include' => array_keys( $value ), 'fields' => 'names' ) );
                case 'tags':
                    $address .= implode( ', ', $value );
                    break;
                case 'region':
                    $terms = get_terms( wpbdp_regions_taxonomy(), array( 'hide_empty' => 0, 'include' => $value, 'fields' => 'names' ) );
                    $address .= implode( ', ', $terms );
                    break;
                default:
                    if ( in_array( $field->get_field_type_id(), array( 'checkbox', 'select', 'multiselect' ), true ) )
                        $value = is_array( $value ) ? implode( ', ', $value ) : $value;

                    $address .= $value;
                    break;
            }

            $address .= ',';
        }

        $address = esc_attr( substr( $address, 0, -1) );
        return trim( $address );
    }

    /**
     * Returns a hash code used to verify that our location cache is kept current.
     * @return string
     */
    public function field_hash() {
        $hash = '';

        foreach ( array( 'address', 'city', 'state', 'zip', 'country' ) as $field_name ) {
            $field_id = wpbdp_get_option( 'googlemaps-fields-' . $field_name );
            $field_id = !$field_id ? 0 : $field_id;
            $hash .= $field_id . '-';
        }

        return substr( $hash, 0, -1 );
    }

    /**
     * Returns the latitude & longitude for the address of a given listing.
     * @param int $listing_id the listing ID.
     * @param bool $nocache wheter to bypass the cache or not. Default is FALSE.
     * @return bool|object an object with lat (latitude) & lng (longitude) keys or FALSE if geolocation fails.
     * @since 1.4
     */
    public function listing_geolocate( $listing_id, $nocache=false ) {
        if ( !$listing_id )
            return false;

        $address = $this->get_listing_address( $listing_id );
        if ( !$address )
            return false;

        $location = !$nocache ? get_post_meta( $listing_id, '_wpbdp[googlemaps][geolocation]', true ) : '';
        if ( $location && ( !isset( $location->field_hash ) || $location->field_hash != $this->field_hash() ) ) {
            return $this->listing_geolocate( $listing_id, true );
        }

        if ( $location && isset( $location->lat ) && isset( $location->lng ) )
            return $location;

        $location = $this->geolocate( $address );

        if ( !$location )
            return false;

        $location->field_hash = $this->field_hash();

        update_post_meta( $listing_id, '_wpbdp[googlemaps][geolocation]', $location );
        return $location;
    }

    /**
     * @since 3.5.1
     */
    public function listing_geolocation_override( $listing_id ) {
        if ( ! $listing_id )
            return false;

        $override = get_post_meta( $listing_id, '_wpbdp[googlemaps][geolocation_override]', true );

        if ( ! $override || ( $override->field_hash != $this->field_hash() ) )
            return false;

        return $override;
    }

    /**
     * @since 3.5.1
     */
    private function toggle_warning( $name = '', $warn = true ) {
        if ( 'all' == $name ) {
            $this->toggle_warning( 'over-query-limit', $warn );
            $this->toggle_warning( 'request-denied', $warn );
            return;
        }

        $warnings = get_option( 'wpbdp-googlemaps-warnings', array() );
        $key = array_search( $name, is_array( $warnings ) ? $warnings : array(), true );

        if ( $warn ) {
            if ( false === $key )
                $warnings[] = $name;
        } else {
            if ( false !== $key )
                unset( $warnings[ $key ] );
        }

        update_option( 'wpbdp-googlemaps-warnings', $warnings );
        return true;
    }

    /**
     * @since 3.5.1
     */
    private function get_warnings() {
        $warnings = get_option( 'wpbdp-googlemaps-warnings', array() );

        if ( ! is_array( $warnings ) )
            return array();

        $texts = array();

        foreach ( $warnings as $warning_name ) {
            $txt = '';

            switch ( $warning_name ) {
                case 'over-query-limit':
                    $txt .= '<b>' . __( 'Business Directory - Google Maps Module has detected some issues while trying to contact the Google Maps API.', 'wpbdp-googlemaps' ) . '</b><br />';
                    $txt .= __( 'This usually happens because Google imposes a daily limit on the number of requests a site can make. If you have been seeing this warning for more than 24 hours it could be because:', 'wpbdp-googlemaps' );
                    $txt .= '<br />';
                    $txt .= __( '- You have a huge number of listings that need to be geocoded. If this is the case you might need to wait several days before Business Directory has cached all the locations.', 'wpbdp-googlemaps' );
                    $txt .= '<br />';
                    $txt .= __( '- You are on a shared hosting and other sites are using up the request allowance for your IP.', 'wpbdp.-googlemaps' );
                    $txt .= '<br />';
                    $txt .= __( '- The number of requests or Google map views in use by your site really exceeds the Google Maps API limits.', 'wpbdp-googlemaps' );
                    $txt .= '<br /><br />';
                    $txt .= str_replace( '<a>',
                            '<a href="http://businessdirectoryplugin.com/docs/#premium-maps" target="_blank">',
                            __( 'You might need to apply for an API key with Google. Please read <a>our documentation on the subject</a>.', 'wpbdp-googlemaps' ) );

                    break;
                case 'request-denied':
/*                    if ( ! wpbdp_get_option( 'googlemaps-apikey' ) )
                        break;*/

                    $txt .= '<b>' . __( 'Business Directory - Google Maps Module: Invalid API Key.', 'wpbdp-googlemaps' ) . '</b><br />';
                    $txt .= str_replace( '<a>',
                                         '<a href="' . admin_url( 'admin.php?page=wpbdp_admin_settings&groupid=googlemaps#googlemaps-apikey' ) . '">',
                                         __( 'The Google Maps <a>API key</a> that you have configured for use with the Google Maps module is invalid. Maps on directory pages will not appear until this issue is addressed.', 'wpbdp-googlemaps' ) ) . '<br />';
                    $txt .= str_replace( '<a>',
                                         '<a href="https://console.developers.google.com" target="_blank">',
                                         __( 'Please visit the "APIs & Auth" section of your <a>Google API Console </a> and make sure that:', 'wpbdp-googlemaps' ) ) . '<br/><br />';
                    $txt .= __( '1. Your API key is a <i>Server Key</i> and that it is currently active.', 'wpbdp-googlemaps' );
                    $txt .= '<br >';
                    $txt .= __( '2. You have enabled both the "Google Maps Geocoding API" and "Google Maps JavaScript API v3" APIs in your console.', 'wpbdp-googlemaps' );
                    $txt .= '<br >';
                    $txt .= __( '3. You have disabled IP restrictions for this key (using IP address restrictions doesn\'t seem to work with our module).', 'wpbdp-googlemaps' );
                    $txt .= '<br /><br />';
                    $txt .= str_replace( '<a>',
                                         '<a href="http://businessdirectoryplugin.com/docs/#premium-maps" target="_blank">',
                                         __( 'For information on how to correctly setup your API key, visit <a>this link</a>.', 'wpbdp-googlemaps' ) );
                    break;
                default:
                    break;
            }

            if ( $txt )
                $texts[] = $txt;
        }

        return $texts;
    }

    /**
     * Obtains the latitude & longitude for a given plain text address.
     * @param string $address the address.
     * @return bool|object an object with lat (latitude) & lng (longitude) keys or FALSE if geolocation fails.
     * @since 1.4
     */
    public function geolocate( $address='' ) {
        $address = trim ( $address );

        if ( !$address )
            return false;

        $key = wpbdp_get_option( 'googlemaps-apikey' );

        $response = wp_remote_get( 'https://maps.googleapis.com/maps/api/geocode/json?' . ( $key ? 'key=' . $key . '&' : '' ) . 'sensor=false&address=' . urlencode( $address ),
                                   array( 'timeout' => 15 )  );

        if ( is_wp_error( $response ) )
            return false;

        $response = json_decode( $response['body'] );

        if ( ! $response || is_null( $response ) )
            return false;

        if ( 'OVER_QUERY_LIMIT' == $response->status )
            $this->toggle_warning( 'over-query-limit', true );
        elseif ( 'REQUEST_DENIED' == $response->status )
            $this->toggle_warning( 'request-denied', true );
        else
            $this->toggle_warning( 'all', false );

        if ( 'OK' != $response->status )
            return false;

        return $response->results[0]->geometry->location;
    }

    public function add_map_to_listing($html = '', $listing_id) {
        $show_google_maps = apply_filters( 'wpbdp_show_google_maps', wpbdp_get_option( 'googlemaps-on' ), $listing_id );

        if ( !$show_google_maps )
            return $html;

        $this->add_listing_to_map( $listing_id );
        return $html . $this->map( array( 'listingID' => $listing_id, 'show_directions' => wpbdp_get_option( 'googlemaps-show-directions' ) ) );
    }

    public function map( $args=array() ) {
        static $uid = 0;

        $args = wp_parse_args( $args, array(
                'map_uid' => $uid,
                'map_type' => wpbdp_get_option( 'googlemaps-maptype', 'roadmap' ),
                'animate_markers' => wpbdp_get_option( 'googlemaps-animate-marker', false ),
                'map_size' => wpbdp_get_option( 'googlemaps-size', 'small' ),
                'map_style_attr' => wpbdp_get_option( 'googlemaps-size' ) == 'custom' ? sprintf('width: %dpx; height: %dpx;', wpbdp_get_option( 'googlemaps-size-custom-w' ), wpbdp_get_option( 'googlemaps-size-custom-h' ) ) : '',
                'position' => wpbdp_get_option( 'googlemaps-position', 'bottom' ),
                'auto_resize' => wpbdp_get_option( 'googlemaps-size-auto', 0 ),
                'show_directions' => false,
                'listingID' => 0
        ) );

        if ( !$this->map_locations )
            return '';

        $uid += 1;

        return wpbdp_render_page( plugin_dir_path( __FILE__ ) . '/templates/map.tpl.php',
                                  array( 'locations' => $this->map_locations,
                                         'settings' => $args )
                         );
    }

    public function _doing_map_on() {
        $this->doing_map = true;
        $this->map_locations = array();
    }

    /**
     * Adds a listing to the current map locations.
     * @param int $post_id listing ID.
     */
    public function add_listing_to_map( $post_id ) {
        $address = $this->get_listing_address( $post_id );
        $geolocation = $this->listing_geolocate( $post_id );
        $override = $this->listing_geolocation_override( $post_id );

        if ( $override )
            $geolocation = $override;

        if ( !$address || ! $geolocation )
            return;

        $this->map_locations[] = array(
            'address' => $address,
            'geolocation' => $geolocation,
            'title' => get_the_title( $post_id ),
            'url' => get_permalink( $post_id ),
            'content' => $this->get_listing_address( $post_id, true )
        );
    }

    public function _category_map( $category ) {
        if ( ! $category )
            return;

        global $wp_query;
        $q = function_exists( 'wpbdp_current_query' ) ? wpbdp_current_query() : $wp_query;

        // try to respect the query as much as we can to be compatible with Regions and other plugins
        $args = array_merge( $q ? $q->query : array(), array() );
        $args['post_type'] = WPBDP_POST_TYPE;
        $args['post_status'] = 'publish';

        if ( 'all' == wpbdp_get_option( 'googlemaps-listings-on-page' ) ) {
            $args['posts_per_page'] = -1;

            if ( isset( $args['paged'] ) ) unset( $args['paged'] );
            if ( isset( $args['numberposts'] ) ) unset( $args['paged'] );
            if ( isset( $args['paged'] ) ) unset( $args['paged'] );
        }

        if ( !isset( $args['tax_query'] ) )
            $args['tax_query'][] = array( 'taxonomy' => WPBDP_CATEGORY_TAX, 'field' => 'id', 'terms' => $category->term_id );
        $args['fields'] = 'ids';
        $args['suppress_filters'] = false;

        if ( $listings = get_posts( $args ) ) {
            array_walk( $listings, array( $this, 'add_listing_to_map' ) );
            echo $this->map();
            $this->map_locations = array();
        }
    }

    public function _search_map() {
        global $wp_query;

        if ( !$wp_query ) return;

        $posts = $wp_query->query['post__in'];
        if ( !$posts ) return;

        array_walk( $posts,  array( $this, 'add_listing_to_map' ) );
        echo $this->map();
        $this->map_locations = array();
    }

    public function _view_listings_map() {
        global $wp_query;

        $q = function_exists( 'wpbdp_current_query' ) ? wpbdp_current_query() : $wp_query;

        // try to respect the query as much as we can to be compatible with Regions and other plugins
        $args = array_merge( $q ? $q->query : array(), array() );
        $args['post_type'] = WPBDP_POST_TYPE;
        $args['post_status'] = 'publish';
        $args['fields'] = 'ids';

        if ( 'all' == wpbdp_get_option( 'googlemaps-listings-on-page' ) ) {
            $args['posts_per_page'] = -1;

            if ( isset( $args['paged'] ) ) unset( $args['paged'] );
            if ( isset( $args['numberposts'] ) ) unset( $args['paged'] );
            if ( isset( $args['paged'] ) ) unset( $args['paged'] );
        }

        $args['suppress_filters'] = false;

        if ( $listings = get_posts( $args ) ) {
            array_walk( $listings, array( $this, 'add_listing_to_map' ) );
            echo $this->map();
            $this->map_locations = array();
        }

    }

    public function update_listing_geolocation( $listing ) {
        $listing_id = is_object( $listing ) ? $listing->get_id() : $listing;

        if ( !$listing_id || wp_is_post_revision( $listing_id ) )
            return;

        if ( get_post_type( $listing_id ) != WPBDP_POST_TYPE )
            return;

        global $wpbdp;
        if ( isset( $wpbdp->_importing_csv ) && $wpbdp->_importing_csv )
            return;

        $this->listing_geolocate( $listing_id, true );
    }

    /* Activation */
    private function check_requirements() {
        return function_exists('wpbdp_get_version') && version_compare(wpbdp_get_version(), self::REQUIRED_BD_VERSION, '>=');
    }

    public function _admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( ! $this->check_requirements() ) {
            printf( '<div class="error"><p>Business Directory - Google Maps Module requires Business Directory Plugin >= %s.</p></div>', self::REQUIRED_BD_VERSION );
            return;
        }

        $warnings = $this->get_warnings();
        foreach ( $warnings as &$w ) {
            echo '<div class="error"><p>' . $w . '</p></div>';
        }
    }

    /**
     * @since 3.5.1
     */
    function _show_place_chooser( $state ) {
        if ( ! wpbdp_get_option( 'googlemaps-fields-latlong-enabled' ) )
            return;

        $vars = array();
        $vars['address'] = $this->get_address_from_state( $state );
        $vars['location'] = $this->geolocate( $vars['address'] );

        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/adjust-location.tpl.php', $vars );
    }

    /**
     * @since 3.5.1
     */
    function _save_location_override( &$state ) {
        if ( ! wpbdp_get_option( 'googlemaps-fields-latlong-enabled' ) )
            return;

        $override = isset( $_POST['location_override'] ) ? $_POST['location_override'] : false;

        if ( ! $override || ! isset( $override['lat'] ) || ! isset( $override['lng'] ) )
            return;

        $location = (object) $override;
        $location->lat = floatval( $location->lat );
        $location->lng = floatval( $location->lng );
        $location->field_hash = $this->field_hash();

        update_post_meta( $state->listing_id, '_wpbdp[googlemaps][geolocation_override]', $location );
    }

}


new BusinessDirectory_GoogleMapsPlugin();
