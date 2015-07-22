<?php

global $wpdb;

define('WPBDP_REGIONS_MODULE_META_TABLE', $wpdb->prefix . 'wpbdp_regionmeta');

class WPBDP_RegionsPluginInstaller {

    public function __construct() {
    }

    public function activate() {
        global $wpdb;

        update_option('wpbdp-regions-flush-rewrite-rules', true);

        // Form Fields are hidden when the module is deactivated
        // so we have to show them again when the module is
        // activated.
        update_option('wpbdp-regions-show-fields', true);
    }

    public function deactivate() {
        wpbdp_regions_fields_api()->hide_fields();
    }

    private function install() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // create Region Meta table
        $sql = "CREATE TABLE IF NOT EXISTS " . WPBDP_REGIONS_MODULE_META_TABLE . " (
            `meta_id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `region_id` BIGINT(20) UNSIGNED NOT NULL,
            `meta_key` VARCHAR(255),
            `meta_value` LONGTEXT,
            PRIMARY KEY  (`meta_id`)
        ) DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;";
        dbDelta($sql);
    }

    public function uninstall() {
        foreach (wpbdp_regions()->options as $option) {
            delete_option($option);
        }
    }

    public function upgrade_check() {
        global $wpdb;

        $plugin_version = WPBDP_RegionsPlugin::VERSION;
        $installed_version = get_option('wpbdp-regions-db-version', '0');

        $sql = sprintf("SHOW TABLES LIKE '%s'", WPBDP_REGIONS_MODULE_META_TABLE);
        if (strcmp($wpdb->get_var($sql), WPBDP_REGIONS_MODULE_META_TABLE) === 0) {
            return $this->upgrade($installed_version, $plugin_version);
        }
        
        $this->install();

        update_option('wpbdp-regions-create-default-regions', true);
        update_option('wpbdp-regions-db-version', $plugin_version);
    }

    private function upgrade($oldversion, $newversion) {
        if ($oldversion == $newversion)
            return;

        if (version_compare($oldversion, '1.1', '<=') && version_compare($newversion, '1.2dev', '>=')) {
            $fields = wpbdp_get_form_fields(array('association' => 'region', 'display_flags' => array('search')));

            foreach ($fields as &$f) {
                if (!$f->has_display_flag('region-selector')) {
                    $f->add_display_flag('region-selector');
                    $f->save();
                }
            }

        }

        return update_option('wpbdp-regions-db-version', $newversion);
    }
}

// function wpbdp_regions_uninstall() {
//     wpbdp_regions()->installer->uninstall();

//     $taxonomy = wpbdp_regions_taxonomy();
//     $terms = get_terms($taxonomy, array('fields' => 'ids', 'get' => 'all'));
//     foreach ($terms as $term) {
//         wp_delete_term($term, $taxonomy);
//     }
// }
