<?php
/*
 Plugin Name: Business Directory Plugin - Featured Levels Module
 Plugin URI: http://www.businessdirectoryplugin.com
 Version: 3.6.1
 Author: D. Rodenbaugh
 Description: Adds support to restrict available features/fields on a per paid level basis, for payment plans or featured listings.  Requires BD 3.1 or higher.
 Author URI: http://www.skylineconsult.com
*/

require_once( plugin_dir_path( __FILE__ ) . 'restrictions.php' );


if (!class_exists('WP_List_Table')) require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

class BusinessDirectory_FeaturedLevelsTable extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => __('featured level', 'wpbdp-featured-levels'),
            'plural' => __('featured levels', 'wpbdp-featured-levels'),
            'ajax' => false
        ));
    }

    public function get_columns() {
        return array(
            'order' => __('Order', 'wpbdp-featured-levels'),
            'name' => __('Name', 'wpbdp-featured-levels'),
            'cost' => __('Cost', 'wpbdp-featured-levels'),
            'extra' => ''
        );
    }

    public function prepare_items() {
        global $wpdb;

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
        $this->items = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpbdp_x_featured_levels ORDER BY weight ASC" );
    }

    /* Rows */

    public function column_order($level) {
        if ( $level->id == 'normal' || $level->id == 'sticky' )
            return '—';

        return sprintf( '<a href="%s"><strong>↑</strong></a> | <a href="%s"><strong>↓</strong></a>' ,
                        esc_url( add_query_arg( array('action' => 'leveldown', 'id' => $level->id) ) ),
                        esc_url( add_query_arg( array('action' => 'levelup', 'id' => $level->id) ) )
                       );
    }

    public function column_name($level) {
        $html  = '';
        $html .= sprintf( '<strong><a href="%s">%s</a></strong>',
                          add_query_arg( array('action' => 'edit', 'id' => $level->id ) ),
                          esc_attr( $level->name )
                        );

        $row_actions = array();
        $row_actions['edit'] = sprintf( '<a href="%s">%s</a>',
                                        add_query_arg( array('action' => 'edit', 'id' => $level->id) ),
                                        __('Edit', 'wpbdp-featured-levels')
                                      );
        if ($level->id != 'normal' && $level->id != 'sticky') {
            $row_actions['delete'] = sprintf( '<a href="%s">%s</a>',
                                            add_query_arg( array('action' => 'delete', 'id' => $level->id) ),
                                            __('Delete', 'wpbdp-featured-levels')
                                          );
        }

        $html .= $this->row_actions( $row_actions );

        return $html;
    }

    public function column_cost($level) {
        return wpbdp_format_currency( $level->cost );
    }

    public function column_description($level) {
        return esc_attr( $level->description );
    }

    public function column_extra() {}

}


class WPBDP_FeaturedLevelsModule {

    const VERSION = '3.6.1';
    const DB_REVISION = '1';
    const REQUIRED_BD_VERSION = '3.5.2';

    public static function instance() {
        static $instance = null;

        if ( !$instance ) {
            $instance = new self;
        }

        return $instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'load_i18n' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
        add_action('init', array( &$this, 'init' ) );

        $this->_restrictions_module = new WPBDP_FeaturedLevelsModule_Restrictions();
    }

    private function check_requirements() {
        return defined( 'WPBDP_VERSION' ) && version_compare( WPBDP_VERSION, self::REQUIRED_BD_VERSION, '>=' );
    }

    public function admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( ! $this->check_requirements() )
            echo sprintf( __( '<div class="error"><p>Business Directory - Featured Levels Module requires Business Directory Plugin >= %s.</p></div>', 'wpbdp-featured-levels' ) , self::REQUIRED_BD_VERSION );
    }

    public function load_i18n() {
        load_plugin_textdomain( 'wpbdp-featured-levels', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );        
    }

    public function init() {
        if ( ! $this->check_requirements() )
            return;

        if ( ! wpbdp_licensing_register_module( 'Featured Levels Module', __FILE__, self::VERSION ) )
           return;

        add_action('wpbdp_modules_init', array($this, '_register_levels'));
        add_action('wpbdp_admin_menu', array($this, '_admin_menu'));

        $this->_install_or_update();
    }

    public function _install_or_update() {
        global $wpdb;

        $db_version = get_option( 'wpbdp-featured-levels-db-rev', '0' );

        if ( $db_version != self::DB_REVISION ) {
            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

            $sql = "CREATE TABLE {$wpdb->prefix}wpbdp_x_featured_levels (
                id VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL PRIMARY KEY,
                name VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
                weight INT(5) NOT NULL DEFAULT 0,
                description TEXT NULL,
                cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                form_fields BLOB NULL,
                extra_data BLOB NULL
            ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";

            dbDelta($sql);
        }

        update_option( 'wpbdp-featured-levels-db-rev', self::DB_REVISION );
    }        

    private function refresh_defaults() {
        global $wpdb;

        $api = wpbdp_listing_upgrades_api();
        $levels = array( 'normal' => $api->get( 'normal' ),
                         'sticky' => $api->get( 'sticky' ) );
        $levels_in_db = array();

        foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT id, cost FROM {$wpdb->prefix}wpbdp_x_featured_levels WHERE id IN (%s, %s)",
                                                      'normal',
                                                      'sticky' ) ) as $r ) {
            $levels_in_db[ $r->id] = $r->cost;
        }

        // Update database if needed.
        $weight = 0;
        foreach ( $levels as $level_id => &$level ) {
            if ( ! array_key_exists( $level_id, $levels_in_db ) ) {
                $wpdb->insert( "{$wpdb->prefix}wpbdp_x_featured_levels", array( 'id' => $level->id,
                                                                                'name' => $level->name,
                                                                                'description' => $level->description,
                                                                                'cost' => $level->cost,
                                                                                'weight' => $weight
                ) );
            } elseif ( $levels_in_db[ $level_id ] != $level->cost ) {
                    $wpdb->update( "{$wpdb->prefix}wpbdp_x_featured_levels", array(
    /*                    'name' => $level->name,
                         'description' => $level->description,*/
                        'cost' => $level->cost
                    ), array( 'id' => $level->id ) );
            }

            $weight++;
        }
    }

    public function _register_levels() {
        global $wpdb;

        $this->refresh_defaults();

        // register new upgrades
        $upgrades_api = wpbdp_listing_upgrades_api();

        // $extra_levels = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}wpbdp_x_featured_levels WHERE weight >= %d ORDER BY weight ASC", 2 ) );
        $extra_levels = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wpbdp_x_featured_levels" );

        foreach ( $extra_levels as $i => $l ) {
            $upgrades_api->register( $l->id,
                                     null,
                                     array(
                                        'name' => $l->name,
                                        'cost' => $l->cost,
                                        'description' => $l->description
                                     )
                                   ); 
        }
    }

    public function _admin_menu($menu) {
        add_submenu_page($menu,
                         __('Manage Featured Levels', 'wpbdp-featured-levels'),
                         __('Manage Featured Levels', 'wpbdp-featured-levels'),
                         'administrator',
                         'wpbdp-featured-levels',
                         array($this, '_dispatch'));
        add_submenu_page( $menu,
                          __( 'Manage Restrictions', 'wpbdp-featured-levels' ),
                          __( 'Manage Restrictions', 'wpbdp-featured-levels' ),
                          'administrator',
                          'wpbdp-restrictions',
                          array( $this->_restrictions_module, '_dispatch' )
                        );
    }

    public function _dispatch() {
        $action = wpbdp_getv($_REQUEST, 'action');
        $_SERVER['REQUEST_URI'] = remove_query_arg(array('action', 'id'), $_SERVER['REQUEST_URI']);

        switch ( $action ) {
            case 'add':
            case 'edit':
                $this->featuredLevelsProcessForm();
                break;
            case 'delete':
                $this->featured_levels_delete();
                break;
            case 'levelup':
            case 'leveldown':
                $this->reorder_level( $_REQUEST['id'], $action == 'levelup' ? 1 : -1 );
                $this->featuredLevelsTable();
                break;                
            default:
                $this->featuredLevelsTable();
                break;
        }
    }

    private function featuredLevelsTable() {
        // $this->refresh_defaults();

        echo wpbdp_admin_header(null, null, array(
            array(__('Add Featured Level', 'wpbdp-featured-levels'), esc_url( add_query_arg( 'action', 'add' ) ))
        ));
        echo wpbdp_admin_notices();

        echo '<div class="wpbdp-note"><p>';
        _e( 'Business Directory will always have two levels: The "Normal Listing" and "Featured Listing". They cannot be deleted, but you can change what users have access to.',
            'wpbdp-featured-levels' );
        echo '</p></div>';

        echo '<div id="wpbdp-featured-levels">';

        $table = new BusinessDirectory_FeaturedLevelsTable();
        $table->prepare_items();
        $table->display();

        echo '</div>';

        echo wpbdp_admin_footer();
    }

    private function featuredLevelsProcessForm() {
        if ( isset($_POST['level']) ) {
            $newlevel = $_POST['level'];

            if ( $this->save_level( $newlevel, $errors ) ) {
                wpbdp()->admin->messages[] = __( 'Featured levels updated.', 'wpbdp-featured-levels' );
                return $this->featuredLevelsTable();
            } else {
                $errmsg = '';
                foreach ($errors as $err)
                    $errmsg .= sprintf('&#149; %s<br />', $err);
                
                wpbdp()->admin->messages[] = array($errmsg, 'error');                
            }
        }

        $level = isset($_GET['id']) ? $this->get_level($_GET['id']) : null;

        wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/featured-level-form.tpl.php',
                           array('level' => $level), true );
    }

    private function featured_levels_delete() {
        global $wpdb;

        $level = $this->get_level($_REQUEST['id']);

        if ( !$level || $level->id == 'normal' || $level->id == 'sticky' || !$level->downgrade )
            return;

        if ( isset($_POST['doit']) ) {
            $wpdb->query( $wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = %s AND meta_value = %s", $level->downgrade, '_wpbdp[sticky_level]', $level->id) );
            $wpdb->query( $wpdb->prepare("DELETE FROM {$wpdb->prefix}wpbdp_x_featured_levels WHERE id = %s", $level->id) );

            wpbdp()->admin->messages[] = __('Level deleted.', 'wpbdp-featured-levels');
            return $this->featuredLevelsTable();
        } else {
            echo wpbdp_admin_header();
            
            echo '<p>';
            echo sprintf( __('Are you sure you want to delete the "%s" featured level?. All listings on this level will be automatically downgraded.', 'wpbdp-featured-levels'), esc_attr( $level->name) ); 
            echo '</p>';
            echo '<form action="" method="POST">';
            echo sprintf( '<input type="hidden" name="id" value="%s" />', $level->id );
            echo '<input type="hidden" name="doit" value="1" />';
            submit_button( __('Delete Featured Level', 'wpbdp-featured-levels'), 'delete' );
            echo '</form>';

            echo wpbdp_admin_footer();
        }

    }

    /*
     * API
     */
    public function reorder_level($id, $delta) {
        global $wpdb;

        $api = wpbdp_listing_upgrades_api();

        $level = $this->get_level( $id );

        if ($level->weight < 2)
            return;

        if ( $delta < 0 && $level->weight == 2 )
            return;

        if ( $delta > 0 ) {
            $next_level = $level->upgrade ? $this->get_level( $level->upgrade ) : null;

            if (!$next_level) {
                return;
            }

            $wpdb->update("{$wpdb->prefix}wpbdp_x_featured_levels", array('weight' => $level->weight), array('id' => $next_level->id));
            $wpdb->update("{$wpdb->prefix}wpbdp_x_featured_levels", array('weight' => $next_level->weight), array('id' => $level->id));
        } else {
            if ($level->downgrade) {
                return $this->reorder_level( $level->downgrade, 1 );
            }
        }

    }

    public function save_level($level=array(), &$errors = null) {
        global $wpdb;

        $upgrades_api = wpbdp_listing_upgrades_api();

        if (!is_array($errors)) $errors = array();

        if ( isset($level['weight']) ) {
            $level['weight'] = intval($level['weight']);
        } else {
            if ( isset( $level['id'] ) && $level['id'] == 'normal' )
                    $level['weight'] = 0;
                elseif ( isset( $level['id'] ) && $level['id']  == 'sticky' )
                    $level['weight'] = 1;
                else
                    $level['weight'] = intval( $wpdb->get_var( "SELECT MAX(weight) + 1 FROM {$wpdb->prefix}wpbdp_x_featured_levels" ) );
        }

        if ( !isset($level['name']) || trim($level['name']) == '' )
            $errors[] = __('Level name is required.', 'wpbdp-featured-levels');

        if ( isset( $level['id'] ) && $level['id'] == 'normal' )
            $level['cost'] = 0.0;

        if ( !isset($level['cost']) || trim($level['cost']) == '' || !is_numeric($level['cost']) || floatval($level['cost']) < 0.0 )
            $errors[] = __('Level cost must be a non-negative decimal number.', 'wpbdp-featured-levels');

        if ( isset($level['form_fields']) ) {
            $level['form_fields'] = serialize( array_map( 'intval', $level['form_fields'] ) );
        } else {
            $errors[] = __('At least one field must be selected for the level.', 'wpbdp-featured-levels');
            $level['form_fields'] = null;
        }

        if ( !$errors ) {
            if ( isset($level['id']) ) {
                if ( $level['id'] == 'sticky' ) {
                    wpbdp_set_option( 'featured-price', $level['cost'] );
                    wpbdp_set_option( 'featured-description', $level['description'] );
                }

                return $wpdb->update("{$wpdb->prefix}wpbdp_x_featured_levels", $level, array('id' => $level['id'])) !== false;
            } else {
                $level['id'] = $upgrades_api->unique_id( $level['name'] );
                return $wpdb->insert("{$wpdb->prefix}wpbdp_x_featured_levels", $level);
            }
        }

        return false;
    }

    public function get_level($id) {
        global $wpdb;

        $level = wpbdp_listing_upgrades_api()->get( $id );
        $level->weight = intval( $wpdb->get_var( $wpdb->prepare( "SELECT weight FROM {$wpdb->prefix}wpbdp_x_featured_levels WHERE id = %s", $id ) ) );
        $level->form_fields = $wpdb->get_var( $wpdb->prepare( "SELECT form_fields FROM {$wpdb->prefix}wpbdp_x_featured_levels WHERE id = %s", $id ) );

        if ( !$level->form_fields )
            $level->form_fields = array();
        else
            $level->form_fields = unserialize( $level->form_fields );

        return $level;
    }    

}

WPBDP_FeaturedLevelsModule::instance();
