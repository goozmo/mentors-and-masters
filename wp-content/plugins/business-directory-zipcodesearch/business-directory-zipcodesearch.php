<?php
/*
Plugin Name: Business Directory Plugin - ZIP Search Module
Description: Add the search your Business Birectory plugin listings by ZIP or Postal Code within a given radius.  Requires BD 3.1 or higher.
Plugin URI: http://www.businessdirectoryplugin.com
Version: 3.6
Author: D. Rodenbaugh
Author URI: http://businessdirectoryplugin.com
*/

require_once( plugin_dir_path( __FILE__ ) . 'admin.php' );


class _WPBDP_DistanceSorter {
    public $center = null;
    public $distance_cb = null;

    public function sort( $a, $b ) {
        $dist = call_user_func( $this->distance_cb, $this->center, $a ) - call_user_func( $this->distance_cb, $this->center, $b );

        if ( $dist > 0.0 )
            return 1;
        elseif ( $dist < 0.0 )
            return -1;
        else
            return 0;
    }

}

class WPBDP_ZIPSearchWidget extends WP_Widget {

    public function __construct() {
        parent::__construct( false,
                             _x( 'Business Directory - Location Search', 'widget', 'wpbdp-zipcodesearch' ),
                             array( 'description' => _x( 'Searches the Business Directory listings by ZIP code.', 'widget', 'wpbdp-zipcodesearch' ) ) );
    }

    public function form( $instance ) {
        if ( isset( $instance['title'] ) )
            $title = $instance['title'];
        else
            $title = _x( 'Location Search', 'widgets', 'wpbdp-zipcodesearch' );

        if ( isset( $instance['field_label'] ) ) {
            $label = $instance['field_label'];
        } elseif ( $field_id = wpbdp_get_option( 'zipcode-field' ) ) {
            if ( $field = wpbdp_get_form_field( $field_id ) ) {
                $label = $field->get_label();
            } else {
                $label = '';
            }
        } else {
            $label = '';
        }

        echo sprintf( '<p><label for="%s">%s</label> <input class="widefat" id="%s" name="%s" type="text" value="%s" /></p>',
                      $this->get_field_id( 'title' ),
                      _x( 'Title:', 'widget', 'wpbdp-zipcodesearch' ),
                      $this->get_field_id( 'title' ),
                      $this->get_field_name( 'title' ),
                      esc_attr( $title )
                    );

        echo sprintf( '<p><label for="%s">%s</label> <input class="widefat" id="%s" name="%s" type="text" value="%s" /></p>',
                      $this->get_field_id( 'field_label' ),
                      _x( 'Field Label:', 'widget', 'wpbdp-zipcodesearch' ),
                      $this->get_field_id( 'field_label' ),
                      $this->get_field_name( 'field_label' ),
                      esc_attr( $label )
                    );

        $units = wpbdp_get_option( 'zipcode-units' );

        echo '<p>';
        echo sprintf( '<label for="%s">%s</label>', $this->get_field_id( 'units' ), _x( 'Units:', 'widget', 'wpbdp-zipcodesearch' ) );
        echo sprintf( '<select id="%s" name="%s">', $this->get_field_id( 'units' ), $this->get_field_name( 'units' ) );
        echo sprintf( '<option value="%s" %s>%s</option>', 'miles', $units == 'miles' ? 'selected="selected"' : '', _x( 'Miles', 'settings', 'wpbdp-zipcodesearch' ) );
        echo sprintf( '<option value="%s" %s>%s</option>', 'kilometers', $units == 'kilometers' ? 'selected="selected"' : '', _x( 'Kilometers', 'settings', 'wpbdp-zipcodesearch' ) );

        echo '</select>';
        echo '</p>';
    }

    public function update($new_instance, $old_instance) {
        $new_instance['title'] = strip_tags( $new_instance['title'] );
        $new_instance['label'] = strip_tags( $new_instance['label'] );

        if ( isset( $new_instance['units'] ) && in_array( $new_instance['units'], array( 'miles', 'kilometers' ), true ) ) {
            wpbdp_set_option( 'zipcode-units', $new_instance['units'] );
        }

        return $new_instance;
    }

    public function widget($args, $instance) {
        extract($args);
        $title = apply_filters( 'widget_title', $instance['title'] );

        echo $before_widget;
        if ( ! empty( $title ) )
            echo $before_title . $title . $after_title;

        echo sprintf('<form action="%s" method="GET">', wpbdp_get_page_link() );
        echo '<input type="hidden" name="action" value="search" />';
        echo sprintf( '<input type="hidden" name="page_id" value="%s" />', wpbdp_get_page_id( 'main' ) );
        echo '<input type="hidden" name="dosrch" value="1" />';

        if ( $zip_field_id = wpbdp_get_option( 'zipcode-field' ) ) {
            if ( $field = wpbdp_get_form_field( $zip_field_id ) ) {
                // echo $field->render( null, 'search', $instance );

                $label = trim( $instance['field_label'] );
                if ( !$label )
                    $label = $field->get_label();


                echo '<label>' . esc_html( $label ) . '</label><br />';
                echo '<input type="text" name="_x[zs_zip]" value="" size="5" class="zipcode-search-zip" /><br />';
                echo '<div class="invalid-msg">Please enter a valid ZIP code.</div>';
                echo '<label><input type="radio" name="_x[zs_mode]" value="zip" checked="checked" /> ' . sprintf( _x(' Only this %s', 'settings', 'wpbdp-zipcodesearch' ), esc_html( $label ) ) . '</label>';
                echo '<br />';
                echo '<label><input type="radio" name="_x[zs_mode]" value="distance" /> ' . _x( 'Distance Search', 'settings', 'wpbdp-zipcodesearch' ) . '</label>';
                echo '<br/>';
                echo '<div class="zipcode-search-distance-fields" style="display: none;">';
                echo _x( 'Find listings within ', 'settings', 'wpbdp-zipcodesearch' );

                if ( wpbdp_get_option( 'zipcode-fixed-radius' ) && '' != wpbdp_get_option( 'zipcode-radius-options' ) ) {
                    echo '<select name="_x[zs_radius]">';

                    foreach ( explode( ',', wpbdp_get_option( 'zipcode-radius-options' ) ) as $r_ ) {
                        $r = round( floatval( $r_ ), 1 );
                        echo '<option value="' . $r . '">' . $r . '</option>';
                    }

                    echo '</select>';
                } else {
                    echo '<input type="text" name="_x[zs_radius]" value="0" size="5" /> ';
                }

                echo esc_attr( wpbdp_get_option( 'zipcode-units' ) ) . '.';
                echo '<div class="invalid-msg">Please enter a valid distance.</div>';
                echo '</div>';

                echo '<br/>';

                $cfield = wpbdp_get_form_fields( array( 'association' => 'category', 'unique' => true ) );
                echo $cfield->render( null, 'search' );
            }
        }

        echo sprintf( '<p><input type="submit" value="%s" class="submit wpbdp-search-widget-submit" /></p>', _x( 'Search', 'widget', 'wpbdp-zipcodesearch' ) );
        echo '</form>';
?>
        <script type="text/javascript">
        jQuery(function($) {
            $('.widget_wpbdp_zipsearchwidget input[type="radio"]').change(function(){
                var $widget = $(this).parents( '.widget' );
                var mode = $(this).val();

                if ( 'distance' == mode ) {
                    $( '.zipcode-search-distance-fields', $widget ).fadeIn( 'fast' );
                    $( '.zipcode-search-distance-fields input' ).focus();
                } else if ( 'zip' == mode ) {
                    $( '.zipcode-search-distance-fields', $widget ).fadeOut( 'fast' );
                    $( 'input.zipcode-search-zip', $widget ).focus();
                }
            });

            $('.widget_wpbdp_zipsearchwidget input[type="submit"]').click(function(e) {
                var $form = $(this).parents('form');
                var $widget = $(this).parents('.widget');
                var $zip = $( 'input.zipcode-search-zip', $form );
                var zip = $.trim( $zip.val() );
                var mode = $( 'input[type="radio"]:checked' ).val();
                var $distance = $( '.zipcode-search-distance-fields input, .zipcode-search-distance-fields select', $form );
                var distance = parseFloat( $distance.val() );

                var validation_errors = false;

                if ( ! zip ) {
                    $zip.addClass( 'invalid' );
//                    $zip.siblings('.invalid-msg').show();
                    validation_errors = true;
                }

                if ( '' === distance || distance < 0 || isNaN( distance ) ) {
                    $distance.addClass( 'invalid' );
//                    $distance.siblings('.invalid-msg').show();
                    validation_errors = true;
                }

                if ( validation_errors )
                    return false;

                return true;
            });
        });
        </script>
<?php
        echo $after_widget;
    }

}

class WPBDP_ZIPCodeSearchModule {

    const VERSION = '3.6';
    const REQUIRED_BD_VERSION = '3.5.2';
    const DB_VERSION = '0.3';

    const EARTH_RADIUS = 6372.797; // in km
    const KM_TO_MI = 0.621371192;
    const MI_TO_KM = 1.60934400061469;

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function __construct() {
        add_action( 'plugins_loaded', array( &$this, 'init' ) );
        add_action( 'admin_notices', array( &$this,'admin_notices' ) );
    }

    public function init() {
        // Load i18n.
        load_plugin_textdomain( 'wpbdp-zipcodesearch', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );

        if ( !defined( 'WPBDP_VERSION' ) || version_compare( WPBDP_VERSION, self::REQUIRED_BD_VERSION, '<' ) )
            return;

        if ( ! wpbdp_licensing_register_module( 'ZIP Search Module', __FILE__, self::VERSION ) )
           return;

        $this->admin = new WPBDP_ZipCodeSearchModule_Admin( $this );

        $this->install_or_update(); // Install or update.
        add_action( 'wpbdp_register_settings', array( $this, '_register_settings' ), 10, 1 ); // Register settings.

        if ( !$this->check_db() )
            return;

        add_shortcode( 'bd-zip', array( &$this, '_shortcode' ) );

        add_action( 'widgets_init', array( &$this, '_register_widgets' ) );

        add_action( 'wpbdp_form_field_store_value', array( &$this, 'field_store_value' ), 10, 3 );
        add_action( 'before_delete_post', array( &$this, 'delete_listing_cache' ) );

        add_filter( 'wpbdp_render_field_inner', array( &$this, 'search_form_integration' ), 10, 5 );
        add_filter( 'wpbdp_search_where', array( &$this, 'search_form_query' ), 10, 2 );
        add_filter( 'wpbdp_search_query_posts_args', array( &$this, 'change_search_order' ), 10, 2 );
        add_action( 'wp_enqueue_scripts', array( &$this, '_enqueue_scripts' ) );
    }

    public function admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( function_exists( 'wpbdp_get_version' ) && version_compare( wpbdp_get_version(), WPBDP_ZIPCodeSearchModule::REQUIRED_BD_VERSION, '>=' ) ) {
        } else {
            echo sprintf( __( '<div class="error"><p>Business Directory - ZIP Code Search Module requires Business Directory Plugin >= %s.</p></div>', 'wpbdp-zipcodesearch' ) , WPBDP_ZIPCodeSearchModule::REQUIRED_BD_VERSION );
        }
    }

    private function install_or_update() {
        global $wpdb;

        $db_version = get_option( 'wpbdp-zipcodesearch-db-version', '0.0' );

        if ( $db_version != self::DB_VERSION ) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

            $sql = "CREATE TABLE {$wpdb->prefix}wpbdp_zipcodes (
                   zip varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
                   latitude FLOAT NOT NULL,
                   longitude FLOAT NOT NULL,
                   country varchar(2) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
                   city varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                   state varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                   KEY (zip)
               ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
            dbDelta($sql);

            $sql = "CREATE TABLE {$wpdb->prefix}wpbdp_zipcodes_listings (
                   listing_id bigint(20),
                   zip varchar(10) CHARACTER SET utf8 COLLATE utf8_general_ci NULL,
                   latitude FLOAT NULL,
                   longitude FLOAT NULL,
                   PRIMARY KEY (listing_id)
               ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
            dbDelta($sql);
        }

        update_option( 'wpbdp-zipcodesearch-db-version', self::DB_VERSION );
    }

    public function _enqueue_scripts() {
        wp_enqueue_style( 'wpbdp-zip-module', plugins_url( '/resources/styles.min.css', __FILE__ ) );
    }

    public function _register_widgets() {
        register_widget( 'WPBDP_ZIPSearchWidget' );
    }

    public function _register_settings( &$settingsapi ) {
        $g = $settingsapi->add_group( 'zipsearch', _x( 'ZIP Search', 'wpbdp-zipcodesearch' ) );

        $s = $settingsapi->add_section( $g, 'zipsearch/general', _x( 'General Settings', 'wpbdp-zipcodesearch' )  );

        $fields = array();
        $fields[] = array( 0, __( '-- Select a field --', 'wpbdp-zipcodesearch' ) );
        foreach ( wpbdp_get_form_fields( 'association=meta' ) as $f ) {
            $fields[] = array( $f->get_id() , esc_attr( $f->get_label() ) );
        }

        $settingsapi->add_setting( $s,
                                   'zipcode-field',
                                   _x( 'Use this field for postal code/ZIP code information', 'settings', 'wpbdp-zipcodesearch' ),
                                   'choice',
                                   '',
                                   '',
                                   array( 'choices' => $fields ),
                                   array( &$this, 'validate_zip_field_setting' ) );
        $settingsapi->add_setting( $s,
                                   'zipcode-units',
                                   _x( 'Units', 'settings', 'wpbdp-zipcodesearch' ),
                                   'choice',
                                   'miles',
                                   '',
                                   array( 'choices' => array(
                                                                array( 'miles', _x( 'Miles', 'settings', 'wpbdp-zipcodesearch' ) ),
                                                                array( 'kilometers', _x( 'Kilometers', 'settings', 'wpbdp-zipcodesearch' ) )
                                    ) ) );

        $settingsapi->add_setting( $s,
                                   'zipcode-fixed-radius',
                                   _x( 'Use these custom distance options for searches with postal codes', 'settings', 'wpbdp-zipcodesearch' ),
                                   'boolean',
                                   false );
        $settingsapi->add_setting( $s,
                                   'zipcode-radius-options',
                                   _x( 'Radius options', 'settings', 'wpbdp-zipcodesearch' ),
                                   'text',
                                   '1,5,10,20',
                                   _x( 'Comma separated list', 'settings', 'wpbdp-zipcodesearch' ),
                                   array( 'use_textarea' => true, 'textarea_rows' => 2 ) );
        $settingsapi->register_dep( 'zipcode-radius-options', 'requires-true', 'zipcode-fixed-radius' );
        $settingsapi->add_setting( $s,
                                   'zipcode-force-order',
                                   _x( 'Sort listings from closest to farthest in the search results?', 'settings', 'wpbdp-zipcodesearch' ),
                                   'boolean',
                                   true );


        $s = $settingsapi->add_section( $g, 'zipsearch/database', _x( 'Database Information', 'wpbdp-zipcodesearch' ) );
        $settingsapi->add_setting($s, 'zipsearch-database-in-use', _x('Installed ZIP/Postal Code databases', 'admin settings', 'wpbdp-zipcodesearch'), 'custom', null, null, null, null, array( &$this->admin, 'manage_databases'));
        $settingsapi->add_setting($s, 'zipsearch-cache-status', _x('Cache Status', 'admin settings', 'wpbdp-zipcodesearch'), 'custom', null, null, null, null, array( &$this->admin, 'cache_status'));
    }

    public function _shortcode( $atts ) {
        $a = shortcode_atts( array(
            'zip' => null,
            'distance' => 0.0,
            'distance_paid' => null,
            'listings' => null,
            'max_paid' => null,
            'featured' => 'top'
        ), $atts );

        if ( !$a['zip'] )
            return;

        $radius = max( 0.0, floatval( $a['distance'] ) );
        if ( is_numeric( $a['distance_paid'] ) )
            $radius = max( floatval( $a['distance_paid'] ), $radius );

        $results = $this->find_listings( array( 'center' => $a['zip'], 'radius' => $radius ) );
        $post_ids = $this->sort_results( $results, $a );

        $html = '';

        // global $wp_query;
        // $old_query = $wp_query;
        // $wp_query = new WP_Query ( array( 'numberposts' => 0, 'post__in' => $post_ids ? $post_ids : array(-1), 'post_type' => WPBDP_POST_TYPE, 'orderby' => 'post__in' ) );
        $posts = get_posts ( array( 'numberposts' => -1, 'post__in' => $post_ids ? $post_ids : array(-1), 'post_type' => WPBDP_POST_TYPE, 'orderby' => 'post__in' ) );

        $html  = '';
        $html .= '<div id="wpbdp-view-listings-page" class="wpbdp-view-listings-page wpbdp-page">';
        $html .= '<div class="wpbdp-page-content">';

        if ( !$posts ) {
            $html .= _x("No listings found.", 'templates', "wpbdp-zipcodesearch");
        } else {
            $html .= '<div class="listings">';

            foreach ( $posts as &$p ) {
                $html .= wpbdp_render_listing( $p->ID, 'excerpt' );
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        // $wp_query = $old_query;
        // wp_reset_query();

        return $html;
    }

    public function field_store_value( &$field, $post_id, $value ) {
        $zip_field_id = intval( wpbdp_get_option( 'zipcode-field' ), 0 );

        if ( $field->get_id() == $zip_field_id ) {
            $this->cache_listing_zipcode( $post_id );
        }
    }

    public function validate_zip_field_setting( $setting, $newvalue, $oldvalue=null ) {
        if ( $newvalue != $oldvalue )
            $this->delete_listing_cache( 'all' );

        return $newvalue;
    }

    public function delete_listing_cache( $postidordb ) {
        global $wpdb;

        if ( is_numeric( $postidordb ) ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE listing_id = %d", $postidordb ) );
        } else {
            if ( $postidordb == 'all' ) {
                $wpdb->query( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes_listings" );
            } elseif ( $postidordb == 'null' || $postidordb == 'NULL' ) {
                $wpdb->query( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip IS NULL" );
            } else {
                return $this->delete_listing_cache( 'all' ); // Delete cache for everything since being DB-specific takes a lot of time.
                // $wpdb->query( $wpdb->prepare( "DELETE zc FROM {$wpdb->prefix}wpbdp_zipcodes_listings zc INNER JOIN {$wpdb->prefix}wpbdp_zipcodes z ON zc.zip = z.zip WHERE z.country = %s", $postidordb ) );
            }
        }
    }

    public function search_form_integration( $field_inner, &$field, $value, $render_context, &$extra=null ) {
        $field_id = intval( wpbdp_get_option( 'zipcode-field', 0 ) );

        if ( $render_context != 'search' || !$field_id || $field_id != $field->get_id() || !$this->get_installed_databases() )
            return $field_inner;

        $html = '';

        $post_values = isset( $_REQUEST['_x'] ) ? $_REQUEST['_x'] : array();

        $html .= '<input type="text" name="_x[zs_zip]" value="' . ( wpbdp_getv( $post_values, 'zs_zip', '' ) ) . '" size="5" class="zipcode-search-zip" /><br />';
        $html .= '<label><input type="radio" name="_x[zs_mode]" value="zip" ' . ( wpbdp_getv( $post_values, 'zs_mode', 'distance' ) == 'zip' ? 'checked="checked"' : '' ) . ' onchange="if (this.checked) { jQuery(\'.zipcode-search-distance-fields\').hide(); } " /> ' . _x(' Only this ZIP/Postal Code', 'settings', 'wpbdp-zipcodesearch' ) . '</label><br />';
        $html .= '<label><input type="radio" name="_x[zs_mode]" value="distance" ' . ( wpbdp_getv( $post_values, 'zs_mode', 'distance' ) == 'distance' ? 'checked="checked"' : '' ) . ' onchange="if (this.checked) { jQuery(\'.zipcode-search-distance-fields\').show(); } " /> ' . _x( 'Distance Search', 'settings', 'wpbdp-zipcodesearch' ) . '</label>';
        $html .= '<br/>';
        $html .= '<div class="zipcode-search-distance-fields" style="' . ( wpbdp_getv( $post_values, 'zs_mode', 'distance' ) == 'zip' ? 'display: none;' : '' )  . '">';
        $html .= _x( 'Find listings within ', 'settings', 'wpbdp-zipcodesearch' );

        if ( wpbdp_get_option( 'zipcode-fixed-radius' ) && '' != wpbdp_get_option( 'zipcode-radius-options' ) ) {
            $html .= '<select name="_x[zs_radius]">';

            foreach ( explode( ',', wpbdp_get_option( 'zipcode-radius-options' ) ) as $r_ ) {
                $r = round( floatval( $r_ ), 1 );
                $html .= '<option value="' . $r . '" ' . ( $r == floatval( wpbdp_getv( $post_values, 'zs_radius', 0 ) ) ? 'selected="selected"' : ''  ) . '>' . $r . '</option>';
            }

            $html .= '</select>';
        } else {
            $html .= '<input type="text" name="_x[zs_radius]" value="' . ( round( floatval( wpbdp_getv( $post_values, 'zs_radius', 0 ) ), 1 ) )  . '" size="5" /> ';
        }

        $html .= esc_attr( wpbdp_get_option( 'zipcode-units' ) ) . '.';
        $html .= '</div>';

        return $html;
    }

    public function search_form_query( $where, $search_args ) {
        $time = microtime( true );

        $args = array( 'zip' => '', 'mode' => 'zip', 'radius' => 0, 'units' => 'miles' );

        if ( isset( $search_args['extra'] ) ) {
            $args['zip'] = trim( wpbdp_getv( $search_args['extra'], 'zs_zip', '' ) );
            $args['mode'] = wpbdp_getv( $search_args['extra'], 'zs_mode', 'zip' );
            $args['radius'] = max( 0, round( floatval( wpbdp_getv( $search_args['extra'], 'zs_radius', 0 ) ), 1) );
            $args['units'] = wpbdp_get_option( 'zipcode-units' );
        }

        extract( $args );

        if ( !$zip )
            return $where;

        if ( $units == 'kilometers' )
            $radius = $radius * 0.621371192;

        // TODO: for even faster queries we could JOIN with the correct tables instead of calculating post_ids first.
        $post_ids = $this->find_listings( array( 'center' => $zip, 'radius' => ( $mode == 'zip' ? 0.0 : $radius ), 'fields' => 'ids' ) );

        if ( !$post_ids ) {
            $where .= ' AND 1=0';
        } else {
            global $wpdb;
            $post_ids = implode(',', $post_ids);

            $where .= " AND {$wpdb->posts}.ID IN ({$post_ids})";
        }

        wpbdp_log( sprintf( 'ZIP search ended [took %s secs].', (microtime( true ) - $time) ) );

        return $where;
    }

    public function change_search_order( $args, $search_args ) {
        if ( ! wpbdp_get_option( 'zipcode-force-order' ) )
            return $args;

        if ( ! isset( $args['post__in'] ) || ! $args['post__in'] || ( 1 == count( $args['post__in'] ) && 0 == $args['post__in'][0] ) )
            return $args;

        $data = array();

        foreach ( $args['post__in'] as $pid ) {
            $zip = $this->get_zipcode( $this->get_listing_zipcode( $pid ) );

            if ( ! $zip )
                continue;

            $data[ $pid ] = $zip;
        }

        if ( ! $data )
            return $args;
		
/*		echo "<pre>";
		print_r( $search_args );
		echo "</pre>";*/
		
        $center = $this->get_zipcode( $search_args['extra']['zs_zip'] );
        
/*        echo "<pre>";
        echo $center;
        echo "</pre>";*/
        
        $this->sort_by_distance( $data, $center );

        $args['post__in'] = array_keys( $data );
        $args['orderby'] = 'post__in';
        $args['order'] = 'ASC';

        return $args;
    }


    /*
     * API
     */

    public function check_db() {
        if ( !$this->get_installed_databases() )
            return false;

        return true;
    }

    public function get_no_cached_listings() {
        global $wpdb;
        return intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_zipcodes_listings" ) );
    }

    // TODO: this function should be more correct since not all listings have zip codes or not even a database is installed.
    public function is_cache_valid() {
        global $wpdb;
        // $invalid_cache = intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != %s", WPBDP_POST_TYPE, 'auto-draft' ) ) ) > $this->get_no_cached_listings();

        $query = $wpdb->prepare( "SELECT 1 AS invalid FROM {$wpdb->posts} p WHERE p.ID NOT IN (SELECT listing_id FROM {$wpdb->prefix}wpbdp_zipcodes_listings) AND p.post_type = %s AND p.post_status = %s LIMIT 1", WPBDP_POST_TYPE, 'publish' );

        $invalid = intval( $wpdb->get_var( $query ) );
        return $invalid == 0;
    }

    public function cache_listing_zipcode( $post_id ) {
        global $wpdb;

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE listing_id = %d", $post_id ) );
        $field = wpbdp_get_formfield( intval( wpbdp_get_option( 'zipcode-field', 0 ) ) );

        $zipcode = $field ? $this->get_zipcode( $field->plain_value( $post_id ) ) : null;

        $data = array();
        $data['listing_id'] = $post_id;

        if ( $zipcode ) {
            $data['zip'] = $zipcode->zip;
            $data['latitude'] = $zipcode->latitude;
            $data['longitude'] = $zipcode->longitude;
        }

        $wpdb->insert( $wpdb->prefix . 'wpbdp_zipcodes_listings', $data );

        return true;
    }

    public function get_listing_zipcode( $post_id ) {
        global $wpdb;

        if ( $this->is_cache_valid() ) {
            return $wpdb->get_var( $wpdb->prepare( "SELECT zip FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE listing_id = %d", $post_id ) );
        } else {
            $field = wpbdp_get_formfield( intval( wpbdp_get_option( 'zipcode-field', 0 ) ) );

            if ( $field )
                return $field->plain_value( $post_id );
        }

        return null;
    }

    public function get_latlng_distance( $p1, $p2, $miles = true ) {
        if ( !is_object( $p1 ) || !is_object( $p2 ) )
            return null;

        $lat1 = deg2rad( $p1->latitude );
        $lng1 = deg2rad( $p1->longitude );
        $lat2 = deg2rad( $p2->latitude );
        $lng2 = deg2rad( $p2->longitude );

        $r = self::EARTH_RADIUS; // mean radius of Earth in km
        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;
        $a = sin($dlat / 2) * sin($dlat / 2) + cos($lat1) * cos($lat2) * sin($dlng / 2) * sin($dlng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $km = $r * $c;

        return ($miles ? ($km * 0.621371192) : $km);
    }

    /**
     * Returns information for a given zip code from the database.
     * Spaces and case are ignored.
     * @param  string $zip the ZIP code.
     * @return object an object with ZIP code information as properties (zip, latitude, longitude, country, city, state) or NULL if nothing was found.
     */
    public function get_zipcode( $zip ) {
        global $wpdb;

        if ( ! $zip )
            return null;

        $zip = trim( strtolower( str_replace( ' ', '', $zip ) ) );
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_zipcodes WHERE zip = %s", $zip ) );
    }

    // Radius in MI.
    public function get_lat_long_rect( $p0, $radius ) {
        // Clever algorithm to determine area around a given location on earth based on http://mathforum.org/library/drmath/view/66987.html
        $radius_km = min( $radius * self::MI_TO_KM, self::EARTH_RADIUS );

        // max_lon = lon1 + arcsin(sin(D/R)/cos(lat1))
        $max_lon = rad2deg( deg2rad( $p0->longitude ) + asin( sin( $radius_km / self::EARTH_RADIUS ) / cos( deg2rad( $p0->latitude ) ) ) );

        // min_lon = lon1 - arcsin(sin(D/R)/cos(lat1))
        $min_lon = rad2deg( deg2rad( $p0->longitude ) - asin( sin( $radius_km / self::EARTH_RADIUS ) / cos( deg2rad( $p0->latitude ) ) ) );

        // max_lat = lat1 + (180/pi)(D/R)
        $max_lat = $p0->latitude + ( 180.0 / M_PI ) * ( $radius_km / self::EARTH_RADIUS );

        // min_lat = lat1 - (180/pi)(D/R)
        $min_lat = $p0->latitude - ( 180.0 / M_PI ) * ( $radius_km / self::EARTH_RADIUS );

        // Add some tolerance.
        $min_lon = round( $min_lon - 0.05, 2 );
        $max_lon = round( $max_lon + 0.05, 2 );
        $min_lat = round( $min_lat - 0.05, 2 );
        $max_lat = round( $max_lat + 0.05, 2 );

        return (object) array( 'longitude' => array( $min_lon, $max_lon ), 'latitude' => array( $min_lat, $max_lat ) );
    }

    // Radius in MI.
    public function find_listings( $args ) {
        global $wpdb;

        $args = wp_parse_args( $args, array(
            'center' => null,
            'radius' => 0.0,
            'fields' => 'all'
        ) );
        extract( $args );

        $results = array();

        $field_id = intval( wpbdp_get_option( 'zipcode-field', 0 ) );

        if ( !$field_id )
            return $results;

        $center = $this->get_zipcode( $center );
        if ( !$center )
            return $results;

        if ( $radius == 0.0 ) {
            if ( $this->is_cache_valid() ) {
                if ( $fields == 'ids' ) {
                    $results = $wpdb->get_col( $wpdb->prepare( "SELECT listing_id FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip = %s", $center->zip ) );
                } else {
                    $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip = %s", $center->zip ) );
                }
            } else {
                if ( $fields == 'ids' ) {
                    $results = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->prefix}wpbdp_zipcodes zc ON pm.meta_value = zc.zip WHERE pm.meta_key = %s AND zc.zip = %s",
                                                               '_wpbdp[fields][' . $field_id . ']',
                                                               $center->zip ) );
                } else {
                    $results = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT pm.post_id AS listing_id, zc.zip, zc.latitude, zc.longitude FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->prefix}wpbdp_zipcodes zc ON pm.meta_value = zc.zip WHERE pm.meta_key = %s AND zc.zip = %s",
                                                                   '_wpbdp[fields][' . $field_id . ']',
                                                                   $center->zip ) );
                }
            }
        } else {
            $rect = $this->get_lat_long_rect( $center, $radius );

            if ( $this->is_cache_valid() ) {
                // Use cache (faster).

                if ( $fields == 'ids' ) {
                    $query = $wpdb->prepare( "SELECT listing_id FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip IS NOT NULL AND longitude IS NOT NULL AND latitude IS NOT NULL AND longitude >= %f AND longitude <= %f AND latitude >= %f AND latitude <= %f", $rect->longitude[0], $rect->longitude[1], $rect->latitude[0], $rect->latitude[1] );
                    $results = $wpdb->get_col( $query );
                } else {
                    $results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_zipcodes_listings WHERE zip IS NOT NULL AND longitude IS NOT NULL AND latitude IS NOT NULL AND longitude >= %f AND longitude <= %f AND latitude >= %f AND latitude <= %f", $rect->longitude[0], $rect->longitude[1], $rect->latitude[0], $rect->latitude[1] ) );
                }
            } else {
                // Perform slower query.
                $rect = $this->get_lat_long_rect( $center, $radius );

                if ( $fields == 'ids' ) {
                    $results = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->prefix}wpbdp_zipcodes zc ON pm.meta_value = zc.zip WHERE pm.meta_key = %s AND zc.latitude >= %f AND zc.latitude <= %f AND zc.longitude >= %f AND zc.longitude <= %f",
                                                               '_wpbdp[fields][' . $field_id . ']',
                                                               $rect->latitude[0],
                                                               $rect->latitude[1],
                                                               $rect->longitude[0],
                                                               $rect->longitude[1] ) );
                } else {
                    $results = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT pm.post_id AS listing_id, zc.zip, zc.latitude, zc.longitude FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->prefix}wpbdp_zipcodes zc ON pm.meta_value = zc.zip WHERE pm.meta_key = %s AND zc.latitude >= %f AND zc.latitude <= %f AND zc.longitude >= %f AND zc.longitude <= %f",
                                                                   '_wpbdp[fields][' . $field_id . ']',
                                                                   $rect->latitude[0],
                                                                   $rect->latitude[1],
                                                                   $rect->longitude[0],
                                                                   $rect->longitude[1] ) );
                }
            }
        }

        if ( 0.0 == $radius )
            return $results;


        // Filter results checking accuracy when using distance search.
        // $def_results = array();

        // foreach ( $results as $r ) {
        //     if ( $zipcode = $this->get_zipcode( $this->get_listing_zipcode( $r ) ) ) {
        //         wpbdp_debug( $zipcode, $this->get_latlng_distance( $center, $zipcode ) );
        //     }
        // }

        // return $def_results;
        return $results;
    }

    private function sort_results( $results, $args ) {
        if ( !$results )
            return array();

        $res = array();
        $paid = array();
        $normal = array();

        $zip = $this->get_zipcode( $args['zip'] );

        foreach ( $results as $r ) {
            $is_paid = $this->listing_is( $r->listing_id, 'paid' );

            if ( $is_paid ) {
                if ( $args['distance_paid'] && ( $this->get_latlng_distance( $zip, $r ) > $args['distance_paid'] ) )
                    continue;

                $r->normal = false;
                $r->paid = true;
                $r->featured = $this->listing_is( $r->listing_id, 'sticky' );

                $paid[] = $r;
            } else {
                if ( $this->get_latlng_distance( $zip, $r ) > $args['distance'] )
                    continue;

                $r->normal = true;
                $r->paid = false;
                $r->featured = false;

                $normal[] = $r;
            }
        }

        // sort by distance
        $this->sort_by_distance( $paid, $zip );
        $this->sort_by_distance( $normal, $zip );

        $max_paid = intval( $args['max_paid'] );
        if ( $max_paid > 0 ) {
            $paid = array_slice( $paid, 0, $max_paid );
        }

        // handle 'featured attribute'
        if ( $args['featured'] == 'top' || $args['featured'] == 'bottom' ) {
            // sort paid: featured first
            $listings_f = array();
            $listings_p = array();

            foreach ( $paid as &$p ) {
                if ( $p->featured )
                    $listings_f[] = $p;
                else
                    $listings_p[] = $p;
            }

            if ( $args['featured'] == 'top' ) {
                $listings = array_merge( $listings_f, $listings_p, $normal );
            } elseif( $args['featured'] == 'bottom' ) {
                $listings = array_merge( $listings_p, $normal, $listings_f );
                wpbdp_debug( $listings );
            }
        } elseif ( $args['featured'] == 'inline' ) {
            $listings = array_merge( $paid, $normal );
            $this->sort_by_distance( $listings, $zip );
        }

        foreach ( $listings as $p ) {
            $res[] = intval( $p->listing_id );
        }

        return array_slice( $res, 0, $args['listings'] );
    }

    public function sort_by_distance( &$listings, $center ) {
        $sorter = new _WPBDP_DistanceSorter();
        $sorter->center = $center;
        $sorter->distance_cb = array( $this, 'get_latlng_distance' );

        uasort( $listings, array( $sorter, 'sort') );
    }

    public function listing_is( $listing_id, $condition='sticky' ) {
        $api = wpbdp_listings_api();

        if ( $condition == 'sticky' ) {
            return $api->get_sticky_status( $listing_id ) == 'sticky';
        } elseif ( $condition == 'non-sticky' ) {
            return $api->get_sticky_status( $listing_id ) == 'normal';
        } elseif ( $condition == 'paid' ) {
            if ( $this->listing_is( $listing_id, 'sticky' ) )
                return true;

            $fees = $api->get_listing_fees( $listing_id );
            foreach ( $fees as &$fee_info ) {
                $fee = unserialize( $fee_info->fee );

                if ( $fee['id'] != 0 || ( $fee['amount'] > 0.0 ) )
                    return true;
            }
        }

        return false;
    }

    public function get_db_name( $dbid ) {
        $dbid = strtolower( $dbid );
        $databases = $this->get_supported_databases();

        return isset( $databases[ $dbid ] ) ? $databases[ $dbid ][0] : $dbid;
    }

    public function get_supported_databases() {
        $databases = array();
        $databases['us'] = array( _x( 'United States', 'databases', 'wpbdp-zipcodesearch' ), '20140408' );
        $databases['uk'] = array( _x( 'United Kingdom (Great Britain)', 'databases', 'wpbdp-zipcodesearch' ), '20131218' );
        $databases['au'] = array( _x( 'Australia', 'databases', 'wpbdp-zipcodesearch' ), '20131218' );
        $databases['ca'] = array( _x( 'Canada', 'databases', 'wpbdp-zipcodesearch' ), '20131218' );
        $databases['mx'] = array( _x( 'Mexico', 'databases', 'wpbdp-zipcodesearch' ), '20140204' );
        $databases['at'] = array( _x( 'Austria', 'databases', 'wpbdp-zipcodesearch' ), '20141103' );
        $databases['de'] = array( _x( 'Germany', 'databases', 'wpbdp-zipcodesearch' ), '20140820' );
        $databases['be'] = array( _x( 'Belgium', 'databases', 'wpbdp-zipcodesearch' ), '20150109' );
        $databases['ch'] = array( _x( 'Switzerland', 'databases', 'wpbdp-zipcodesearch' ), '20150109' );
        $databases['li'] = array( _x( 'Liechtenstein', 'databases', 'wpbdp-zipcodesearch' ), '20150109' );

        return $databases;
    }

    public function get_installed_databases() {
        return get_option( 'wpbdp-zipcodesearch-db-list', array() );
    }

    public function delete_database( $db ) {
        global $wpdb;

        $databases = get_option( 'wpbdp-zipcodesearch-db-list', array() );
        unset( $databases[ $db ] );
        update_option( 'wpbdp-zipcodesearch-db-list', $databases );

        $this->delete_listing_cache( $db ); // Delete cache associated to this database.
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}wpbdp_zipcodes WHERE country = %s", $db ) );
    }

}

$search_module = WPBDP_ZIPCodeSearchModule::instance();
