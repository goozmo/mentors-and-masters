<?php
/*
Plugin Name: Business Directory Plugin - Regions Module
Description: Add the ability to filter your Business Birectory plugin listings by any region you can configure (city, state, county, village, etc).  Requires BD 2.2 or higher.
Plugin URI: http://www.businessdirectoryplugin.com
Version: 3.6.1
Author: D. Rodenbaugh
Author URI: http://businessdirectoryplugin.com
License: GPLv2 or any later version
*/

define('WPBDP_REGIONS_MODULE_BASENAME', trailingslashit(basename(dirname(__FILE__))));
define('WPBDP_REGIONS_MODULE_DIR', untrailingslashit(plugin_dir_path(__FILE__)));
define('WPBDP_REGIONS_MODULE_URL', untrailingslashit(plugin_dir_url(__FILE__)));

require_once(WPBDP_REGIONS_MODULE_DIR . '/api/form-fields.php');
require_once(WPBDP_REGIONS_MODULE_DIR . '/api/regions.php');
require_once(WPBDP_REGIONS_MODULE_DIR . '/admin/admin.php');
require_once(WPBDP_REGIONS_MODULE_DIR . '/frontend/frontend.php');
require_once(WPBDP_REGIONS_MODULE_DIR . '/installer.php');


function wpbdp_regions() {
    return WPBDP_RegionsPlugin::instance();
}


class WPBDP_RegionsPlugin {

    private static $instance = null;
    private $__temp__ = array();

    // registry of all options used
    public $options = array(
        // registered settings
        'wpbdp-regions-show-sidelist',

        // internal settings
        'wpbdp-regions-db-version',

        'wpbdp-regions-localization-enabled',
        'wpbdp-regions-create-default-regions',
        'wpbdp-regions-create-fields',
        'wpbdp-regions-show-fields',

        'wpbdp-regions-create-default-regions-error',
        'wpbdp-regions-create-fields-error',

        'wpbdp-regions-flush-rewrite-rules',
        'wpbdp-regions-factory-reset',

        'wpbdp-regions-form-fields',
        'wpbdp-regions-form-fields-options',
        'wpbdp-regions-max-level',

        'wpbdp-visible-regions-children',
        'wpbdp-sidelisted-regions-children',
    );


    const VERSION = '3.6.1';
    const REQUIRED_BD_VERSION = '3.6';

    const TAXONOMY = 'wpbdm-region';

    private function __construct() {
        $this->installer = new WPBDP_RegionsPluginInstaller();

        $file = WP_CONTENT_DIR . '/plugins/' . basename(dirname(__FILE__)) . '/' . basename(__FILE__);
        register_activation_hook($file, array($this->installer, 'activate'));
        register_deactivation_hook($file, array($this->installer, 'deactivate'));

        add_action('plugins_loaded', array($this, 'setup'));
    }

    public static function instance() {
        if (is_null(self::$instance))
            self::$instance = new WPBDP_RegionsPlugin();
        return self::$instance;
    }

    private function check_requirements() {
        return function_exists('wpbdp_get_version') && version_compare(wpbdp_get_version(), self::REQUIRED_BD_VERSION, '>=');
    }

    public function _admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        $template = '<div class="error"><p>%s</p></div>';

        if (!$this->check_requirements()) {
            $message = _x('Business Directory - Regions Module requires Business Directory Plugin >= %s.', 'regions-module', 'wpbdp-regions');
            echo sprintf($template, sprintf($message, self::REQUIRED_BD_VERSION));
        }

        if ($message = get_option('wpbdp-regions-create-fields-error')) {
            echo sprintf($template, $message);
        }

        if ($errors = get_option('wpbdp-regions-create-default-regions-error')) {
            $message = _x('There was one or more errors trying to create the default regions:<br/><br/>%s', 'regions-module', 'wpbdp-regions');
            $message = sprintf($message, '<strong>' . join('<br/>', $errors) . '</strong>');
            echo sprintf($template, $message);
        }
    }

    public function setup() {
        global $wpdb;

        // Load i18n.
        load_plugin_textdomain( 'wpbdp-regions', false, trailingslashit( basename( dirname( __FILE__ ) ) ) . 'translations/' );

        add_action('admin_notices', array($this, '_admin_notices'));

        if ( ! $this->check_requirements() )
            return;

        if ( ! wpbdp_licensing_register_module( 'Regions Module', __FILE__, self::VERSION ) )
           return;

        add_action('wpbdp_register_settings', array($this, 'register_settings'));

        $this->admin = new WPBDP_RegionsAdmin();
        $this->frontend = new WPBDP_RegionsFrontend();

        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        add_action('load-edit.php', array($this, 'disable_taxonomy_ui'));

        $regions = wpbdp_regions_api();
        $fields = wpbdp_regions_fields_api();

        // Metadata API integration
        $attribute = WPBDP_RegionsAPI::META_TYPE . 'meta';
        $wpdb->$attribute = WPBDP_REGIONS_MODULE_META_TABLE;

        // WP Query integratrion
        add_action('pre_get_posts', array($this, 'pre_get_posts'), 20);

        add_action('post_updated', array($this, 'post_updated'), 10, 3);
        add_action('trashed_post', array($this, 'post_trashed'), 10, 1);

        // Taxonomy API integration
		add_filter( 'term_link', array( &$this, 'term_link' ), 10, 3 );
        add_filter('taxonomy_template', array($this, 'taxonomy_template'));
        add_filter('terms_clauses', array($this, 'terms_clauses'), 10, 3);
        add_filter('get_terms', array($this, 'get_terms'), 10, 3);
        add_action('set_object_terms', array($this, 'set_object_terms'), 10, 6);

        // BD-related taxonomy filters
        add_filter( '_wpbdp_padded_count', array($this, '_padded_count'), 10, 2 );

        add_action(sprintf("created_%s", self::TAXONOMY), array($this, 'set_term_attributes'), 10, 2);

        add_action(sprintf("created_%s", self::TAXONOMY), array($regions, 'clean_regions_cache'));
        add_action(sprintf("edited_%s", self::TAXONOMY), array($regions, 'clean_regions_cache'));
        add_action(sprintf("delete_%s", self::TAXONOMY), array($regions, 'clean_regions_cache'));

        // Business Directory Form Fields API integration
        add_action( 'wpbdp_modules_init', array( &$this, 'fields_init' ) );

        // Business Directory Listings API integration
        add_action('WPBDP_Listing::set_field_values', array($this, 'update_listing'), 10, 2);
        add_action( 'wpbdp_action_page_region-listings', array( &$this, 'region_listings_page' ) );

        // Business Directory Search integration
        // add_action('wpbdp_after_search_fields', array($this->frontend, 'render_search_fields'));
        add_filter('wpbdp_search_where', array($this->frontend, 'search_where'), 10, 2);
    }

    public function fields_init() {
        $fieldsapi = wpbdp_formfields_api();
        $fieldsapi->register_association( 'region', _x('Post Region', 'form-fields api', 'wpbdp-regions'), array( 'private' ) );

        $fields = wpbdp_regions_fields_api();

        add_filter('wpbdp_form_field_value', array($fields, 'field_value'), 10, 3);
        add_filter('wpbdp_form_field_html_value', array($fields, 'field_html_value'), 10, 3);
        add_filter('wpbdp_form_field_plain_value', array($fields, 'field_plain_value'), 10, 3);
        add_action('wpbdp_form_field_pre_render', array($fields, 'field_attributes'), 10, 3);
        add_filter( 'wpbdp_form_field_select_option', array( $fields, 'field_option' ), 10, 2 );

        // Field settings (admin side)
        add_action('wpbdp_form_field_settings', array($fields, 'field_settings'), 10, 2);
        add_action('wpbdp_form_field_settings_process', array($fields, 'field_settings_process'), 10, 2);
    }

    public function init() {
        register_taxonomy(self::TAXONOMY, WPBDP_POST_TYPE, array(
            'label' => _x('Directory Regions', 'regions-module', 'wpbdp-regions'),
            'labels' => array(
                'name' => _x('Directory Regions', 'regions-module', 'wpbdp-regions'),
                'singular_name' => _x('Region', 'regions-module', 'wpbdp-regions'),
                'search_items' => _x('Search Regions', 'regions-module', 'wpbdp-regions'),
                'popular_items' => _x('Popular Regions', 'regions-module', 'wpbdp-regions'),
                'all_items' => _x('All Regions', 'regions-module', 'wpbdp-regions'),
                'parent_item' => _x('Parent Region', 'regions-module', 'wpbdp-regions'),
                'parent_item_colon' => _x('Parent Region:', 'regions-module', 'wpbdp-regions'),
                'edit_item' => _x('Edit Region', 'regions-module', 'wpbdp-regions'),
                'update_item' => _x('Update Region', 'regions-module', 'wpbdp-regions'),
                'add_new_item' => _x('Add New Region', 'regions-module', 'wpbdp-regions'),
                'new_item_name' => _x('New Region Name', 'regions-module', 'wpbdp-regions'),
                'menu_name' => _x('Manage Regions', 'regions-module', 'wpbdp-regions')
            ),
            'hierarchical' => true,
            'show_in_nav_menus' => true,
            'query_var' => true,

            'rewrite' => array('slug' => wpbdp_get_option('regions-slug', self::TAXONOMY))
        ));

        $this->installer->upgrade_check();

        if (get_option('wpbdp-clean-regions-cache'))
            $this->clean_regions_cache();

        if (get_option('wpbdp-regions-create-fields'))
            $this->create_fields();

        if (get_option('wpbdp-regions-show-fields'))
            $this->show_fields();

        if (get_option('wpbdp-regions-create-default-regions'))
            $this->create_default_regions();

        if (get_option('wpbdp-regions-flush-rewrite-rules'))
            $this->flush_rewrite_rules();

        $this->register_scripts();
    }

    /**
     * Disable Region taxonomy UI in Quick Edit form.
     *
     * If you set show_ui to false for a taxonomy duing load-edit.php action,
     * the quick edit form won't include edit UI for that taxonomy.
     */
    public function disable_taxonomy_ui() {
        global $wp_taxonomies;
        $wp_taxonomies[self::TAXONOMY]->show_ui = false;
    }

    public function register_settings($settings) {
        $url = add_query_arg(array('taxonomy' => self::TAXONOMY, 'post_type' => WPBDP_POST_TYPE), admin_url('edit-tags.php'));
        $help_text = _x('Go to <a href="%s">Manage Regions</a> settings to configure the Regions hierarchy.', 'admin settings', 'wpbdp-regions');
        $help_text = sprintf($help_text, $url);

        $g = $settings->add_group('regions', _x('Regions', 'admin settings', 'wpbdp-regions'), $help_text);

        $s = $settings->add_section($g, 'general', _x('General Settings', 'admin settings', 'wpbdp-regions'));
        $settings->add_setting($s, 'regions-slug', _x('Regions Slug', 'admin settings', 'wpbdp-regions'), 'text', 'wpbdm-region');
        $settings->add_setting($s,
                              'regions-hide-selector',
                              _x('Hide Region selector?', 'admin settings', 'wpbdp-regions'),
                              'boolean',
                              false,
                              _x('The region selector is the small bar displayed above listings that allows users to filter their listings based on location.  It is enabled by default.', 'admin settings', 'wpbdp-regions'));
        $settings->add_setting($s, 'regions-selector-open', _x('Show region selector open by default?', 'admin settings', 'wpbdp-regions'), 'boolean', false);
        $settings->add_setting($s, 'regions-show-counts', _x('Show counts in region selector?', 'admin settings', 'wpbdp-regions'), 'boolean', true);

        $s = $settings->add_section($g, 'sidelist', _x('Regions Sidelist', 'admin settings', 'wpbdp-regions'), _x('The Sidelist is a list of selected Regions shown in the main Business Directory pages. The Regions to show can be configured in the Manage Regions section.', 'region settings', 'wpbdp-regions'));
        $settings->add_setting($s, 'regions-show-sidelist', _x('Show Sidelist', 'admin settings', 'wpbdp-regions'), 'boolean', false, _x('Check to show the Sidelist, uncheck to hide the Sidelist.', 'admin settings', 'wpbdp-regions'));
        $settings->add_setting($s, 'regions-sidelist-counts', _x('Show counts in sidelist?', 'admin settings', 'wpbdp-regions'), 'boolean', true);
        $settings->add_setting($s, 'regions-sidelist-hide-empty', _x('Hide empty regions in sidelist?', 'admin settings', 'wpbdp-regions'), 'boolean', false);

        if ( function_exists( 'get_ancestors' ) )
            $settings->add_setting($s, 'regions-sidelist-expand-current', _x('Keep sidelist expanded on current region?', 'admin settings', 'wpbdp-regions'), 'boolean', false);

        $settings->add_setting($s, 'regions-sidelist-autoexpand', _x('Automatically expand sidelist on page load?', 'admin settings', 'wpbdp-regions'), 'boolean', false);

        $s = $settings->add_section($g,
                                   'default-regions',
                                   _x('Create Default Regions', 'region settings', 'wpbdp-regions'),
                                   _x('Use the button below to create or restore the default Regions. The module will attempt to create the default Regions (avoiding duplicates). Clicking the button does not remove other regions you may have created, but will restore the default regions you may have deleted.', 'regions settings', 'wpbdp-regions')
                                   );
        $settings->add_setting($s, 'regions-create-default-regions', _x('Create Default Regions', 'admin settings', 'wpbdp-regions'), 'custom', null, null, null, null, array($this, '_reset_button'));

        $s = $settings->add_section($g,
                                    'default-fields',
                                    _x('Restore Region Form Fields', 'region settings', 'wpbdp-regions'),
                                    _x('Use the button below to create or restore Region form fields. The module will check for missing fields and will restore them. Clicking the button does not remove any of the existing fields.', 'region settings', 'wpbdp-regions')
                                    );
        $settings->add_setting($s, 'regions-restore-fields', _x('Restore Region Form Fields', 'admin settings', 'wpbdp-regions'), 'custom', null, null, null, null, array($this, '_reset_button'));

        $s = $settings->add_section($g,
                                   'default-settings',
                                   _x('Restore to Default Settings', 'region settings', 'wpbdp-regions'),
                                   _x('Use the button below to restore the Regions module to its original state. All regions, fields and settings will be removed and replaced. This action cannot be undone.', 'region settings', 'wpbdp-regions')
                                   );
        $settings->add_setting($s, 'regions-restore-defaults', _x('Restore to Default Settings', 'admin settings', 'wpbdp-regions'), 'custom', null, null, null, null, array($this, '_reset_button'));        
    }

    public function _reset_button( $setting, $value=null ) {
        $link = '';
        $label = $setting->label;

        switch ( $setting->name ) {
            case 'regions-create-default-regions':
                $link = wp_nonce_url( add_query_arg( 'wpbdp-regions-create-default-regions', 1 ), 'wpbdp-regions-create-default-regions' );
                break;
            case 'regions-restore-fields':
                $link = wp_nonce_url( add_query_arg( 'wpbdp-regions-create-fields', 1 ), 'wpbdp-regions-create-fields' );
                break;
            case 'regions-restore-defaults':
                $link = wp_nonce_url( add_query_arg( 'wpbdp-regions-factory-reset', 1 ), 'wpbdp-regions-factory-reset' );
                break;
            default:
                break;
        }

        if ( $link )
            echo '<a href="' . $link . '" class="button">' . $label . '</a>';
    }


    private function register_scripts() {
        $base = WPBDP_REGIONS_MODULE_URL . '/resources';

        wp_register_style('wpbdp-regions-style', "$base/css/style.min.css", array(), self::VERSION);
        wp_register_script('wpbdp-regions-admin', "$base/js/admin.min.js", array('jquery-color', 'jquery-form', 'jquery-ui-tabs'), self::VERSION, true);
        wp_register_script('wpbdp-regions-frontend', "$base/js/frontend.min.js", array('jquery'), self::VERSION, true);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('wpbdp-regions-style');
        wp_enqueue_script('wpbdp-regions-frontend');

        $regions_api = wpbdp_regions_api();

        wp_localize_script('wpbdp-regions-frontend', "ignore = 'me'; jQuery.RegionsFrontend", array(
            'ajaxurl' => wpbdp_ajaxurl(),
            'UILoadingText' => _x( 'Loading...', 'regions-module', 'wpbdp-regions' ),
            'currentRegion' => intval( $regions_api->get_active_region() )
        ));
    }

    private function flush_rewrite_rules() {
        flush_rewrite_rules();
        update_option('wpbdp-regions-flush-rewrite-rules', false);
    }

    public function create_fields() {
        global $wpdb;

        $regions = wpbdp_regions_api();
        $regionfields = wpbdp_regions_fields_api();

        $errors = array();
        $fields = array();

        $oldfields = $regionfields->get_fields();

        $labels = array(1 => 'Continent', 2 => 'Country', 3 => 'State', 4 => 'City');
        $levels = $regions->get_max_level();

        for ($level = 1; $level <= $levels; $level++) {
            $field = isset($oldfields[$level]) ? wpbdp_get_form_field($oldfields[$level]) : null;

            // field already exists
            if ($field && $field->get_association() == 'region') {
                $fields[$level] = $field->get_id();
                continue;
            }

            $visible = $level > 1 ? 1 : 0;

            $field = new WPBDP_FormField( array(
                'label' => wpbdp_getv($labels, $level, "Regions Level $level"),
                'association' => 'region',
                'field_type' => 'select',
                'validators' => '',
                'display_flags' => $visible ? array( 'excerpt', 'listing', 'search', 'region-selector', 'regions-in-form' ) : array()
            ) );

            $res = $field->save();
            if ( !is_wp_error( $res ) ) {
                $fields[$level] = $field->get_id();
            } else {
                $msg = _x('There were one or more errors trying to create the Region Form Fields:<br/><br/>%s', 'regions-module', 'wpbdp-regions');
                $msg = sprintf($msg, '<strong>' . join('<br/>', $res->get_error_messages() ) . '</strong>');
                update_option('wpbdp-regions-create-fields-error', $msg);

                return;
            }
        }

        delete_option('wpbdp-regions-create-fields-error');
        update_option('wpbdp-regions-create-fields', false);
        delete_option('wpbdp-regions-ignore-warning');

        $regionfields->update_fields($fields);
    }

    private function show_fields() {
        wpbdp_regions_fields_api()->show_fields();
        delete_option('wpbdp-regions-show-fields');
    }

    private function clean_regions_cache() {
        wpbdp_regions_api()->clean_regions_cache();
        clean_term_cache(array(), self::TAXONOMY);
        update_option('wpbdp-clean-regions-cache', false);
    }

    private function _create_default_regions($name, $children, $parent=0) {
        $regions = wpbdp_regions_api();
        $taxonomy = wpbdp_regions_taxonomy();

        // Turns out that if there is another term, in a different taxonomy,
        // which has the same slug as one of the Regions, but a strictly
        // different name, the generated Region will have a slug with
        // with a number as suffix to remove the conflict.
        //
        // The problem is, the next time this function is called, it will
        // create duplicate Regions, because WP is unable to find the already
        // created Region due to the slightly different slug.
        //
        // That's why we need to try to find the Region by its name, instead
        // of using the slug (which is what WP does), and skip the insert
        // step if a Region is found in the desired level.
        if ($term_id = $regions->exists($name, $parent)) {
            $term = is_array($term_id) ? $term_id : array('term_id' => $term_id);
        } else {
            $term = wp_insert_term($name, $taxonomy, array('parent' => $parent));
        }

        if (is_wp_error($term)) {
            $code = $term->get_error_code();
            if ($code === 'term_exists') {
                continue;
            } else {
                $errors = get_option('wpbdp-regions-create-default-regions-error', array());
                $errors[] = $term->get_error_message();
                update_option('wpbdp-regions-create-default-regions-error', $errors);

                return;
            }
        }

        if (!is_array($children)) return;

        foreach ($children as $_name => $_children) {
            $this->_create_default_regions($_name, $_children, $term['term_id']);
        }
    }

    /**
     *
     * @return [type] [description]
     */
    public function create_default_regions() {
        // default continents and countries
        $continents = array(
            'Africa' => array('Algeria','Angola','Benin','Botswana','Burkina Faso','Burundi','Cameroon','Cape Verde','Central African Republic','Chad','Comoros','Côte d\'Ivoire','Djibouti','Egypt','Equatorial Guinea','Eritrea','Ethiopia','Gabon','Gambia','Ghana','Guinea','Guinea-Bissau','Kenya','Lesotho','Liberia','Libya','Madagascar','Malawi','Mali','Mauritania','Mauritius','Morocco','Mozambique','Namibia','Niger','Nigeria','Republic of the Congo','Rwanda','Sao Tome and Principe','Senegal','Seychelles','Sierra Leone','Somalia','South Africa','Sudan','Swaziland','Tanzania','Togo','Tunisia','Uganda','Western Sahara','Zambia','Zimbabwe'),
            'Asia' => array('Afghanistan','Armenia','Azerbaijan','Bahrain','Bangladesh','Bhutan','Brunei','Burma (Myanmar)','Cambodia','China','Georgia','Hong Kong','India','Indonesia','Iran','Iraq','Israel','Japan','Jordan','Kazakhstan','Korea, North','Korea, South','Kuwait','Kyrgyzstan','Laos','Lebanon','Malaysia','Maldives','Mongolia','Myanmar','Nepal','Oman','Pakistan','Philippines','Qatar','Russia','Saudi Arabia','Singapore','Sri Lanka','Syria','Taiwan','Tajikistan','Thailand','Turkey','Turkmenistan','United Arab Emirates','Uzbekistan','Vietnam','Yemen'),
            'Australia & Oceania' => array('Australia','Fiji','Kiribati','Marshall Islands','Micronesia','Nauru','New Zealand','Palau','Papua New Guinea','Samoa','Solomon Islands','Tonga','Tuvalu','Vanuatu'),
            'Europe' => array('Albania','Andorra','Austria','Belarus','Belgium','Bosnia and Herzegovina','Bulgaria','Croatia','Cyprus','Czech Republic','Denmark','Estonia','Finland','France','Germany','Greece','Hungary','Iceland','Ireland','Italy','Latvia','Liechtenstein','Lithuania','Luxembourg','Macedonia','Malta','Moldova','Monaco','Netherlands','Norway','Poland','Portugal','Romania','Russia','San Marino','Serbia and Montenegro','Slovakia (Slovak Republic)','Slovenia','Spain','Sweden','Switzerland','Turkey','Ukraine','United Kingdom','Vatican City'),
            'North America' => array('Antigua and Barbuda','The Bahamas','Barbados','Belize','Canada','Costa Rica','Cuba','Dominica','Dominican Republic','El Salvador','Greenland (Kalaallit Nunaat)','Grenada','Guatemala','Haiti','Honduras','Jamaica','Mexico','Nicaragua','Panama','Saint Kitts and Nevis','Saint Lucia','Saint Vincent and the Grenadines','Trinidad and Tobago','USA'),
            'South America' => array('Argentina','Bolivia','Brazil','Chile','Colombia','Ecuador','French Guiana','Guyana','Paraguay','Peru','Suriname','Uruguay','Venezuela')
        );

        $states = array('Alabama', 'Alaska', 'Arizona', 'Arkansas', 'California', 'Colorado', 'Connecticut', 'Delaware', 'Florida', 'Georgia', 'Hawaii', 'Idaho', 'Illinois', 'Indiana', 'Iowa', 'Kansas', 'Kentucky', 'Louisiana', 'Maine', 'Maryland', 'Massachusetts', 'Michigan', 'Minnesota', 'Mississippi', 'Missouri', 'Montana', 'Nebraska', 'Nevada', 'New Hampshire', 'New Jersey', 'New Mexico', 'New York', 'North Carolina', 'North Dakota', 'Ohio', 'Oklahoma', 'Oregon', 'Pennsylvania', 'Rhode Island', 'South Carolina', 'South Dakota', 'Tennessee', 'Texas', 'Utah', 'Vermont', 'Virginia', 'Washington', 'West Virginia', 'Wisconsin', 'Wyoming');

        update_option('wpbdp-regions-create-default-regions-error', false);

        foreach ($continents as $continent => $countries) {
            $countries = array_combine($countries, $countries);

            if (isset($countries['USA']))
                $countries['USA'] = array_combine($states, $states);

            $this->_create_default_regions($continent, $countries);
        }

        $errors = get_option('wpbdp-regions-create-default-regions-error', array());
        if (empty($errors)) {
            update_option('wpbdp-regions-create-default-regions', false);
            delete_option('wpbdp-regions-create-default-regions-error');
        }

        // After the regions have been restored, get_terms return
        // top level regions only. We need to wait until the next
        // request to be able to calculate how many regions levels
        // we have and how many fields we need to create.
        update_option('wpbdp-clean-regions-cache', true);
        update_option('wpbdp-regions-create-fields', true);
    }

    public function factory_reset() {
        // 1. delete all regions
        $terms = wpbdp_regions_api()->find(array(
            'get' => 'all',
            'hide_empty' => false,
            'wpbdp-regions-skip' => true
        ));

        foreach ($terms as $term) {
            wp_delete_term($term->term_id, self::TAXONOMY);
        }

        // 2. delete all fields
        wpbdp_regions_fields_api()->delete_fields();

        // 3. remove all settings
        foreach ($this->options as $option) {
            if ($option !== 'wpbdp-regions-db-version') {
                delete_option($option);
            }
        }

        // 4. restore everything
        $this->clean_regions_cache();
        $this->create_default_regions();
        $this->flush_rewrite_rules();
    }

    /* WP Query integration */
    private function set_query_options( &$query ) {
        $query->set( 'post_type', WPBDP_POST_TYPE );
        $query->set('posts_per_page', wpbdp_get_option( 'listings-per-page' ) > 0 ? wpbdp_get_option( 'listings-per-page' ) : -1);
        $query->set('orderby', wpbdp_get_option('listings-order-by', 'date'));
        $query->set('order', wpbdp_get_option('listings-sort', 'ASC'));
    }

    public function pre_get_posts(&$query) {
        $taxonomy = wpbdp_regions_taxonomy();
        $tax_query = $query->get('tax_query');

        if ($query->get($taxonomy)) {
            $this->set_query_options( $query );
            return;
        }

        if ($tax_query = $query->get('tax_query')) {
            foreach ($tax_query as $q) {
                if ( isset( $q['taxonomy'] ) && $q['taxonomy'] == $taxonomy) {
                    $this->set_query_options( $query );
//                    $query->set('post_type', WPBDP_POST_TYPE);
                    return;
                }
            }
        }

        if (!$query->is_admin && $query->get('post_type') == WPBDP_POST_TYPE && !$query->get('post__in') && !$query->get('region')) {
            $api = wpbdp_regions_api();
            $region = $api->get_active_region();

            if ( ! $region )
                return;

            $tax_query = array_filter((array) $query->get('tax_query'));
            $tax_query[] = array('taxonomy' => $taxonomy, 'field' => 'id', 'terms' => $region );
            $query->set('tax_query', $tax_query);
            // $query->set($taxonomy, $region->name);
        }
    }

    public function post_updated($post_id, $post_after, $post_before) {
        if ($post_after->post_type != WPBDP_POST_TYPE) return;

        if ($post_after->post_status == $post_before->post_status) return;

        wpbdp_regions_api()->clean_regions_count_cache();
    }

    public function post_trashed($post_id) {
        $post = get_post($post_id);

        if ($post->post_type != WPBDP_POST_TYPE) return;

        $args = array('orderby' => 'id', 'order' => 'DESC');
        $regions = wp_get_object_terms($post_id, self::TAXONOMY, $args);

        if (!empty($regions)) return;

        wpbdp_regions_api()->clean_regions_count_cache();
    }

    /* Taxonomy API integration */

	public function term_link( $link, $term, $taxonomy ) {
		if ( $taxonomy != self::TAXONOMY )
			return $link;

		$api = wpbdp_regions_api();
		return $api->region_listings_link( $term );
	}

    private function locate_template($template) {
        $template = $template ? (array) $template : array();

        $path = wpbdp_locate_template($template);

        if ($path) return $path;

        foreach ($template as $t) {
            $path = WPBDP_REGIONS_MODULE_DIR . '/templates/' . $t . '.tpl.php';
            if (file_exists($path))
                return $path;
        }
    }

    public function taxonomy_template($template) {
        if (get_query_var(self::TAXONOMY) && taxonomy_exists(self::TAXONOMY)) {
            return $this->locate_template(array('businessdirectory-region'));
        }

        return $template;
    }

    public function terms_clauses($clauses, $taxonomies, $args) {
        // out of jurisdiction
        $taxonomy = wpbdp_regions_taxonomy();
        if (!in_array($taxonomy, $taxonomies)) return $clauses;

        // if (isset($args['localized']) && $args['localized']) {
        //     $join = 'INNER JOIN ' . WPBDP_REGIONS_MODULE_META_TABLE . ' AS rml ';
        //     $join.= "ON (t.term_id = rml.region_id AND rml.meta_key = 'localized' AND rml.meta_value = 1)";
        //     // $where = 'AND (rm.meta_value IS NULL OR rm.meta_value = 1)';
        //     $clauses['join'] = sprintf("%s %s", $clauses['join'], $join);
        // }

        if (isset($args['enabled'])) {
            $join = 'LEFT JOIN ' . WPBDP_REGIONS_MODULE_META_TABLE . ' AS rme ';
            $join.= "ON (t.term_id = rme.region_id AND rme.meta_key = 'enabled')";
            $clauses['join'] = sprintf("%s %s", $clauses['join'], $join);

            if ($args['enabled']) {
                $where = 'AND (rme.meta_value IS NULL OR rme.meta_value = 1)';
                $clauses['where'] = sprintf("%s %s", $clauses['where'], $where);
            } else {
                $where = 'AND (rme.meta_value = 0)';
                $clauses['where'] = sprintf("%s %s", $clauses['where'], $where);
            }
        }

        if (isset($args['sidelist'])) {
            $join = 'LEFT JOIN ' . WPBDP_REGIONS_MODULE_META_TABLE . ' AS rms ';
            $join.= "ON (t.term_id = rms.region_id AND rms.meta_key = 'sidelist')";
            $clauses['join'] = sprintf("%s %s", $clauses['join'], $join);

            if ($args['sidelist']) {
                $where = 'AND (rms.meta_value IS NULL OR rms.meta_value = 1)';
                $clauses['where'] = sprintf("%s %s", $clauses['where'], $where);
            } else {
                $where = 'AND (rms.meta_value = 0)';
                $clauses['where'] = sprintf("%s %s", $clauses['where'], $where);
            }
        }

        return $clauses;
    }

    public function set_object_terms($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        if ($taxonomy == wpbdp_regions_taxonomy())
            wpbdp_regions_api()->clean_regions_count_cache();
    }

    public function set_term_attributes($term_id, $tt_id) {
        $api = wpbdp_regions_api();
        $region = $api->find_by_id($term_id);
        $parent = $api->find_by_id($region->parent);

        $api->set_enabled($term_id, $api->is_enabled($region->parent));
        $api->set_sidelist_status($term_id, $api->on_sidelist($region->parent));
    }

    public function get_terms($terms, $taxonomies, $args) {
        $category = WPBDP_CATEGORY_TAX;

        if (empty($terms) || !in_array($category, $taxonomies)) return $terms;
        // if ( wpbdp_get_option('show-category-post-count') == false ) return $terms;

        $regions = wpbdp_regions_api();
        $region = $regions->get_active_region();
        $region = is_null($region) ? null : $regions->find_by_id($region);

        if (is_wp_error($region) || is_null($region)) return $terms;

        $hide_empty = wpbdp_getv($args, 'hide_empty', 0);
        $count = $this->get_categories_count($region);

        $_terms = array();
        foreach ($terms as $i => $term) {
            if ( !isset( $term->taxonomy ) ) {
                $_terms[] = $term;
                continue;
            }

            if ($term->taxonomy == $category && isset($count[$term->term_id])) {
                $term->count = $count[$term->term_id]->count;
            } else if ($term->taxonomy == $category) {
                $term->count = 0;
            }

            if (!$hide_empty || $term->count > 0) {
                $_terms[] = $term;
            }
        }

        return $_terms;
    }

    private function get_categories_count($region) {
        global $wpdb;

        $count = (array) get_option('wpbdp-category-regions-count', array());
        if (isset($count[$region->term_id])) return $count[$region->term_id];

        // SELECT ctax.term_id, c.term_taxonomy_id, COUNT(p.ID) count FROM wp_posts p INNER JOIN wp_term_relationships r ON ( p.ID = r.object_id AND r.term_taxonomy_id = 221 ) INNER JOIN wp_term_relationships c ON ( p.ID = c.object_id ) INNER JOIN wp_term_taxonomy ctax ON ( c.term_taxonomy_id = ctax.term_taxonomy_id AND taxonomy = 'wpbdm-category' ) WHERE post_type='wpbdp_listing' GROUP BY c.term_taxonomy_id
        // SELECT ctax.term_id, cr.term_taxonomy_id, COUNT(p.ID) count FROM wp_posts p INNER JOIN wp_term_relationships rr ON ( p.ID = rr.object_id AND rr.term_taxonomy_id = 221 ) INNER JOIN wp_term_relationships cr ON ( p.ID = cr.object_id ) INNER JOIN wp_term_taxonomy ctax ON ( cr.term_taxonomy_id = ctax.term_taxonomy_id AND taxonomy = 'wpbdm-region' ) WHERE post_type='wpbdp_listing' GROUP BY cr.term_taxonomy_id

        $query = "SELECT ctax.term_id, cr.term_taxonomy_id, COUNT(p.ID) count FROM {$wpdb->posts} p ";
        // join with table of posts associated to the given Region
        $query.= "INNER JOIN {$wpdb->term_relationships} rr ON ( p.ID = rr.object_id AND rr.term_taxonomy_id = %d ) ";
        // then join to associate remaining posts with their category
        $query.= "INNER JOIN {$wpdb->term_relationships} cr ON ( p.ID = cr.object_id ) ";
        $query.= "INNER JOIN {$wpdb->term_taxonomy} ctax ON ( cr.term_taxonomy_id = ctax.term_taxonomy_id AND taxonomy = %s ) ";
        // whe only want Listings. group by category and count.
        $query.= "WHERE post_type=%s AND post_status = 'publish' GROUP BY cr.term_taxonomy_id";

        $query = $wpdb->prepare($query, $region->term_taxonomy_id, WPBDP_CATEGORY_TAX, WPBDP_POST_TYPE);

        $count[$region->term_id] = $wpdb->get_results($query, OBJECT_K);
        update_option('wpbdp-category-regions-count', $count);

        return $count[$region->term_id];
    }

    public function _padded_count( $count, $term ) {
        $regions = wpbdp_regions_api();
        $region = $regions->get_active_region();
        $region = is_null($region) ? null : $regions->find_by_id($region);
        
        if ( is_wp_error( $region ) || is_null( $region ) )
            return $count;
        
        global $wpdb;
    
        $region_tree_ids = array_merge( array( $region->term_id ), get_term_children( $region->term_id, wpbdp_regions_taxonomy() ) );
        $region_tt_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', $region_tree_ids ) . ") AND taxonomy = %s", wpbdp_regions_taxonomy() ) );
        
        $category_tree_ids = array_merge( array( $term->term_id ), get_term_children( $term->term_id, WPBDP_CATEGORY_TAX ) );
        $category_tt_ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', $category_tree_ids ) . ") AND taxonomy = %s", WPBDP_CATEGORY_TAX ) );
        
        // Query using EXISTS: SELECT r.object_id FROM wp_term_relationships r INNER JOIN wp_posts p ON p.ID = r.object_id WHERE p.post_type = 'wpbdp_listing' AND p.post_status = 'publish' AND r.term_taxonomy_id IN (276, 277, 279) AND EXISTS (SELECT 1 FROM wp_term_relationships WHERE term_taxonomy_id IN (21) AND object_id = r.object_id) GROUP BY r.object_id        
        // Query using INNER JOIN: SELECT tr.object_id FROM wp_term_relationships tr INNER JOIN wp_term_relationships tr2 ON tr.object_id = tr2.object_id WHERE tr.term_taxonomy_id IN (21) AND tr2.term_taxonomy_id IN (276, 277, 279) GROUP BY tr.object_id
        $query = $wpdb->prepare( "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_relationships} tr2 ON tr.object_id = tr2.object_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE p.post_status = %s AND p.post_type = %s AND tr.term_taxonomy_id IN (" . implode( ',', $region_tt_ids ) . ") AND tr2.term_taxonomy_id IN (" . implode( ',', $category_tt_ids ) . ")",
                                 'publish',
                                 WPBDP_POST_TYPE );
        return intval( $wpdb->get_var( $query ) );
    }

    /* Business Directory Listings API integration */

    public function update_listing($listing_id, $listingfields=null) {
        $regions = wpbdp_regions_api();
        $regionfields = wpbdp_regions_fields_api();
        $max = $regions->get_max_level();

        if ( is_object( $listing_id ) )
            $listing_id = $listing_id->get_id();

        for ($level = $max; $level > 0; $level--) {
            $field = $regionfields->get_field_by_level($level);

            if (is_null($field) || !isset($listingfields[$field->get_id()]))
                continue;

            $value = $listingfields[$field->get_id()];
            $value = is_array($value) ? reset($value) : $value;

            // support CSV import by allowing Region names as the value
            if (!is_numeric($value)) {
                $region = $regions->find_by_name($value, $level);
                $region_id = $region ? $region->term_id : 0;
            } else {
                $region_id = $value;
            }

            if ($region_id <= 0) continue;

            $hierarchy = array();
            $regions->get_region_level($region_id, $hierarchy);

            wp_set_post_terms($listing_id, $hierarchy, wpbdp_regions_taxonomy(), false);

            break;
        }
    }

    /* Temporary Data Storage */

    public function set($name, $value) {
        $this->__temp__[$name] = $value;
    }

    public function get($name, $default=null) {
        return wpbdp_getv($this->__temp__, $name, $default);
    }


    public function region_listings_page() {
        global $wp_query;

        $region = $wp_query->get( 'region' ) ? $wp_query->get( 'region' ) : ( isset( $_REQUEST['region'] ) ? $_REQUEST['region'] : '' );

        if ( ! $region )
            return '<p>' . _x( 'Region not found.', 'region page', 'wpbdp-regions' ) . '</p>';

        $limit = 0;

        if ( isset( $_REQUEST['limit'] ) )
            $limit = abs( intval( $_REQUEST['limit'] ) );

        global $wpbdp;
        echo $wpbdp->controller->view_listings( false, array( 'numberposts' => $limit ) );
    }
}

wpbdp_regions();
