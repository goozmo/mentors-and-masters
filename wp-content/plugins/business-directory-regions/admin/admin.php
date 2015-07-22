<?php

class WPBDP_RegionsAdmin {

    private $screen;

    public function __construct() {
        $this->screen = sprintf('edit-%s', wpbdp_regions_taxonomy());

        $taxonomy = wpbdp_regions_taxonomy();
        $post_type = WPBDP_POST_TYPE;

        add_action('parent_file', array($this, 'parent_file'));
        add_action( 'admin_menu', array($this, 'menu'), 20 );

        add_action('admin_init', array($this, 'admin_init'));
        add_action('admin_init', array($this, 'setup'));

        add_action('admin_notices', array($this, 'admin_notices'));

        add_action('load-post.php', array($this, 'enqueue_scripts'));
        add_action('load-post-new.php', array($this, 'enqueue_scripts'));
        add_action('load-edit-tags.php', array($this, 'enqueue_scripts'));
        add_action('admin_footer', array($this, 'admin_footer'));

        add_filter("edit_{$taxonomy}_per_page", array($this, 'get_items_per_page'), 10, 2);
        add_filter('set-screen-option', array($this, 'set_screen_option'), 10, 3);

        add_filter("views_{$this->screen}", array($this, 'get_views'));

        add_action("add_meta_boxes_{$post_type}", array($this, 'add_meta_box'));
        add_action('save_post', array($this, 'save_meta_box'));

        add_action('wp_ajax_wpbdp-regions-show', array($this, 'ajax'));
        add_action('wp_ajax_wpbdp-regions-hide', array($this, 'ajax'));
        add_action('wp_ajax_wpbdp-regions-enable', array($this, 'ajax'));
        add_action('wp_ajax_wpbdp-regions-disable', array($this, 'ajax'));
        // add_action('wp_ajax_wpbdp-regions-localize', array($this, 'ajax'));
        // add_action('wp_ajax_wpbdp-regions-delocalize', array($this, 'ajax'));
    }

    /**
     * Overwrite $parent_file global variable so Manage Regions sub menu (which
     * is actually the menu for Edit Region Taxonomy screen) appears to be
     * a sub menu of the Directory Admin menu, instead of a sub menu of Directory.å
     */
    public function parent_file($parent_file) {
        global $submenu_file;

        $_submenu_file = "edit-tags.php?taxonomy=%s&amp;post_type=%s";
        $_submenu_file = sprintf($_submenu_file, wpbdp_regions_taxonomy(), WPBDP_POST_TYPE);

        if (strcmp($submenu_file, $_submenu_file) === 0)
            return 'wpbdp_admin';
        return $parent_file;
    }

    /**
     * Move Manage Regions sub menu to the Directory Admin menu.
     */
    public function menu() {
        global $submenu;

        $parent_file = sprintf('edit.php?post_type=%s', WPBDP_POST_TYPE);
        $submenu_file = "edit-tags.php?taxonomy=%s&amp;post_type=%s";
        $submenu_file = sprintf($submenu_file, wpbdp_regions_taxonomy(), WPBDP_POST_TYPE);

        $directory_regions = null;
        foreach (wpbdp_getv($submenu, $parent_file, array()) as $k => $item) {
            if (strcmp($item[2], $submenu_file) === 0) {
                $directory_regions = $k;
                break;
            }
        }

        if (is_null($directory_regions)) return;

        $manage_form_fields = null;
        foreach (wpbdp_getv($submenu, 'wpbdp_admin', array()) as $k => $item) {
            if (strcmp($item[2], 'wpbdp_admin_formfields') === 0) {
                $manage_form_fields = $k;
                break;
            }
        }

        if (is_null($manage_form_fields)) return;

        // remove Directory Regions submenu from standard position
        $item = $submenu[$parent_file][$directory_regions];
        unset($submenu[$parent_file][$directory_regions]);

        // add Directory Regions after Manage Form Fields
        array_splice($submenu['wpbdp_admin'], $manage_form_fields + 1, 0, array($item));
    }

    /**
     * Handle bulk, settings actions.
     *
     * WP redirects if the action is not one of the standard
     * edit-tags actions. This function checks for posted data before the
     * redirection occurs.
     */
    public function admin_init() {
        $reset_type = '';

        // handle reset buttons
        if ( wpbdp_getv( $_REQUEST, 'wpbdp-regions-create-default-regions', 0 ) )
            $reset_type = 'wpbdp-regions-create-default-regions';
        elseif ( wpbdp_getv( $_REQUEST, 'wpbdp-regions-create-fields', 0 ) )
            $reset_type = 'wpbdp-regions-create-fields';
        elseif ( wpbdp_getv( $_REQUEST, 'wpbdp-regions-factory-reset', 0 ) )
            $reset_type = 'wpbdp-regions-factory-reset';
        elseif ( wpbdp_getv( $_REQUEST, 'wpbdp-regions-ignore-warning', 0 ) )
            $reset_type = 'wpbdp-regions-ignore-warning';

        if ( $reset_type ) {
            check_admin_referer( $reset_type );
            $plugin = wpbdp_regions();

            switch ( $reset_type ) {
                case 'wpbdp-regions-create-default-regions':
                    $plugin->create_default_regions();
                    break;
                case 'wpbdp-regions-create-fields':
                    $plugin->create_fields();
                    break;
                case 'wpbdp-regions-factory-reset':
                    $plugin->factory_reset();
                    break;
                case 'wpbdp-regions-ignore-warning':
                    update_option( 'wpbdp-regions-ignore-warning', true );
                    break;
                default:
                    break;
            }

            wp_redirect( wp_get_referer()  ? wp_get_referer() : admin_url() );
        }


        // further checks only apply if we are editing tags
        $script = $_SERVER['SCRIPT_FILENAME'];
        if (strcmp(substr($script, - strlen('edit-tags.php')), 'edit-tags.php') !== 0) return;

        // // need to include orderby to disable the hierachical layout and be able to show
        // // regions from second and deeper levels without retrieving their parents
        // if ($this->get_filter() != 'all' && !isset($_REQUEST['orderby'])) {
        //     $_REQUEST['orderby'] = 'name';
        //     $_REQUEST['order'] = 'asc';
        // }

        $nonce = isset($_POST['_wpnonce']) ? $_POST['_wpnonce'] : false;
        if (!$nonce) return;

        $api = wpbdp_regions_api();

        // handle bulk-actions
        if (wp_verify_nonce($nonce, 'bulk-tags')) {
            $action = isset($_POST['action']) ? $_POST['action'] : -1;
            if ($action == -1 && isset($_POST['action2']))
                $action = $_POST['action2'];

            switch ($action) {
                case 'bulk-show':
                    $fn = array('set_sidelist_status', true);
                    break;
                case 'bulk-hide':
                    $fn = array('set_sidelist_status', false);
                    break;
                case 'bulk-enable':
                    $fn = array('set_enabled', true);
                    break;
                case 'bulk-disable':
                    $fn = array('set_enabled', false);
                    break;
                case 'delete': // bulk-delete
                    // Force wp_get_referer() to return an URL instead of false. If false
                    // is returned edit-tags.php will drop all URL parameters like 'children'
                    // or 'filter' when redirecting.
                    //
                    // wp_get_referer() documentation says:
                    // Retrieve referer from '_wp_http_referer' or HTTP referer.
                    // If it's the same as the current request URL, will return false.
                    $_SERVER['REQUEST_URI'] = add_query_arg('timestamp', current_time('timestamp'), $_SERVER['REQUEST_URI']);
                default:
                    // one of the standard actions, skip
                    return;
            }

            $regions = isset($_POST['delete_tags']) ? $_POST['delete_tags'] : array();
            foreach ($regions as $region) {
                call_user_func_array(array($api, $fn[0]), array($region, $fn[1]));
            }

        // handle add multiple regions
        } else if (wp_verify_nonce($nonce, 'add-multiple-regions')) {
            $names = explode("\n", wpbdp_getv($_POST, 'tag-name'));
            $parent = wpbdp_getv($_POST, 'parent', 0);

            foreach ($names as $name) {
                $api->insert($name, $parent);
            }
        }
    }

    private function config_notices($in_manage_page=false) {
        global $wpdb;

        $messages = array();
        $errors = array();

        $count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . WPBDP_REGIONS_MODULE_META_TABLE . " rm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = rm.region_id WHERE meta_key=%s AND meta_value=%d",
                                               'enabled', 1)));
        if ($count == 0) {
            if ($in_manage_page) {
                $messages[] = _x('You must enable a region for it to show on the lists in Business Directory (the filter selector, submit listing form, etc.).', 'regions-module', 'wpbdp-regions');
            } else {
                $errors[] = sprintf(_x('Business Directory Regions module has been turned on but no regions are enabled. Go to <a href="%s">Manage Regions</a> to fix this.',
                                       'regions-module',
                                       'wpbdp-regions'),
                                    admin_url('edit-tags.php?taxonomy=' . wpbdp_regions_taxonomy())
                                    );
            }
        }

        if (wpbdp_get_option('regions-show-sidelist')) {
            $count = intval($wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . WPBDP_REGIONS_MODULE_META_TABLE . " rm INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = rm.region_id WHERE meta_key=%s AND meta_value=%d",
                                                   'sidelist', 1)));

            if ($count == 0) {
                if ($in_manage_page) {
                    $messages[] = sprintf(_x('You are showing the Regions sidelist but have no region marked with the "Show on Sidelist" flag. Please review your <a href="%s">Regions settings</a>.', 'regions-module', 'wpbdp-regions'),
                                         '');
                }
            }
        }

        if (($in_manage_page || (isset($_GET['page']) && $_GET['page'] == 'wpbdp_admin_formfields')) && !get_option('wpbdp-regions-ignore-warning', false)) {
            $levels_missing = array();

            $rf_api = wpbdp_regions_fields_api();
            $fields = $rf_api->get_fields();

            foreach ( $fields as $l => $field_id ) {
                if ( !$field_id || !wpbdp_get_form_field( $field_id ) )
                    $levels_missing[] = $l;
            }

            if ( $levels_missing ) {
                $errors[] = sprintf( _x( 'Your Business Directory - Regions hierarchy contains %d levels, but you are missing Region fields for some of them (level %s).', 'regions-module', 'wpbdp-regions' ) . '<br />' .
                                     '<a href="%s"><b>%s</b></a> | <a href="%s">%s</a>',
                                     count( $fields ),
                                     implode(',', $levels_missing),
                                     wp_nonce_url( add_query_arg( 'wpbdp-regions-create-fields', 1 ), 'wpbdp-regions-create-fields' ),
                                     _x( 'Restore Region Form Fields', 'regions-module', 'wpbdp-regions' ),
                                     wp_nonce_url( add_query_arg( 'wpbdp-regions-ignore-warning', 1 ), 'wpbdp-regions-ignore-warning' ),
                                     _x( 'Dismiss Warning', 'regions-module', 'wpbdp-regions' )
                                   );
            }

        }

        foreach ($errors as &$e) {
            echo sprintf('<div class="error"><p>%s</p></div>', $e);
        }

        foreach ($messages as &$m) {
            echo sprintf('<div class="updated"><p>%s</p></div>', $m);
        }

    }

    public function admin_notices() {
        global $pagenow;

        if ($pagenow != 'edit-tags.php' || wpbdp_getv($_REQUEST, 'taxonomy') != wpbdp_regions_taxonomy()) {
            $this->config_notices(false);
            return;
        }

        $this->config_notices(true);

        $this->messages = isset($this->messages) ? $this->messages : array();

        if ($parent = $this->get_parent()) {
            $region = wpbdp_regions_api()->find_by_id($parent);

            if (!is_null($region)) {
                $url = add_query_arg('children', 0);
                $message = _x('You are currently seeing sub-regions of <strong>%s</strong> only. <a href="%s">Click here to see all regions</a>.', 'regions-module', 'wpbdp-regions');
                $this->messages[] = sprintf($message, $region->name, $url);
            }
        }

        foreach ($this->messages as $message) {
            echo sprintf('<div class="updated"><p>%s</p></div>', $message);
        }
    }

    public function setup() {
        $taxonomy = wpbdp_regions_taxonomy();
        add_filter("manage_edit-{$taxonomy}_columns", array($this, 'manage_columns'));
        add_filter("manage_edit-{$taxonomy}_sortable_columns", array($this, 'manage_sortable_columns'));
        add_filter("manage_{$taxonomy}_custom_column", array($this, 'manage_custom_column'), 10, 3);
        add_filter("{$taxonomy}_row_actions", array($this, 'row_actions'), 10, 2);

        add_filter('get_terms_args', array($this, 'get_terms_args'), 10, 2);
    }

    public function enqueue_scripts() {
        global $typenow;

        if (get_current_screen()->id === $this->screen) {
            wp_enqueue_style('wpbdp-regions-style');
            wp_enqueue_script('wpbdp-regions-admin');
        }

        if ($typenow === WPBDP_POST_TYPE) {
            wp_enqueue_style('wpbdp-regions-style');
        }
    }

    public function admin_footer() {
        // there are not enough hooks to add the features we need. I'm using
        // jQuery to create the required UI in Directory Regions screen
        wp_localize_script('wpbdp-regions-admin', "ignore = 'me'; jQuery.RegionsData", array(
            'templates' => $this->templates(),
        ));
    }

    // /* Localization functions */

    // private function is_localizing() {
    //     $user = wp_get_current_user();
    //     return (bool) get_user_meta($user->ID, 'localize-regions', true);
    // }

    /* WP List Table integration */

    private function get_filter() {
        $filter = get_user_option('wpbdp-regions-admin-filter');
        return wpbdp_getv($_REQUEST, 'filter', empty($filter) ? 'all' : $filter);
    }

    private function get_parent() {
        $parent = get_user_option('wpbdp-regions-admin-parent');
        return (int) wpbdp_getv($_REQUEST, 'children', $parent);
    }

    public function get_views($views) {
        $filter = $this->get_filter();
        $templates = array('<a href="%1$s">%2$s</a>', '<strong>%2$s</strong>');

        $_views = array(
            'enabled' => _x('Enabled', 'regions-module', 'wpbdp-regions'),
            'disabled' => _x('Disabled', 'regions-module', 'wpbdp-regions'),
            'on-sidelist' => _x('On Sidelist', 'regions-module', 'wpbdp-regions'),
            'not-on-sidelist' => _x('Not on Sidelist', 'regions-module', 'wpbdp-regions'),
            'all' => _x('All', 'regions-module', 'wpbdp-regions')
        );

        foreach ($_views as $id => $label) {
            $views[$id] = sprintf($templates[$filter == $id], add_query_arg('filter', $id), $label);
        }

        return $views;
    }

    private function get_edit_link($region_id) {
        return get_edit_term_link($region_id, wpbdp_regions_taxonomy(), WPBDP_POST_TYPE);
    }

    public function row_actions($_actions, $region) {
        $actions = array();

        // no view, for now
        unset($_actions['view']);

        // // no normal Edit screen
        // unset($_actions['edit']);
        // // Quick Edit is the desired way of editing a Region
        // $text = _x('Edit', 'regions-mdule', 'wpbdp-regions');
        // $_actions['inline hide-if-no-js'] = '<a href="#" class="editinline">' . $text . '</a>';

        $url = $this->get_edit_link($region->term_id);
        $url = add_query_arg('children', $region->term_id, $url);
        $url = remove_query_arg(array('action', 'tag_ID'), $url);
        $link = sprintf('<a href="%s">%s</a>', $url, __('Show Sub-Regions', 'wpbdp-regions'));
        $actions['children'] = $link;

        $actions = array_merge($actions, $_actions);

        $text = _x('Add Child', 'regions-mdule', 'wpbdp-regions');
        $actions['add-child'] = '<a href="#">' . $text . '</a>';

        return $actions;
    }

    public function manage_columns($columns) {
        unset($columns['description']);

        $columns['posts'] = _x('Listings in Region', 'regions-module', 'wpbdp-regions');
        $columns['sidelist'] = _x('Show on Sidelist', 'regions-module', 'wpbdp-regions');
        $columns['enabled'] = _x('Enabled', 'regions-module', 'wpbdp-regions');
        $columns['frontend-links'] = _x( 'Frontend Links', 'regions-module', 'wpbdp-regions' );
        // $enabled = wpbdp_regions_api()->is_localization_enabled();
        // if ($this->is_localizing() || $enabled)
        //     $columns['localized'] = _x('Localized', 'regions-module', 'wpbdp-regions');

        return $columns;
    }

    public function manage_sortable_columns($columns) {
        unset($columns['description']);
        // $columns['sidelist'] = array('sidelist', false);
        // $columns['enabled'] = array('enabled', false);
        // $columns['localized'] = array('localized', false);
        return $columns;
    }

    public function manage_custom_column($value, $column, $region_id) {
        global $wp_list_table;

        $yes = _x('Yes', 'regions-module', 'wpbdp-regions');
        $no = _x('No', 'regions-module', 'wpbdp-regions');

        $actions = array();
        $regions = wpbdp_regions_api();

        switch ($column) {
            case 'sidelist':
                if ($regions->on_sidelist($region_id)) {
                    $actions['hide'] = _x('Hide', 'regions-module', 'wpbdp-regions');
                    $value = $yes;
                } else {
                    $actions['show'] = _x('Show', 'regions-module', 'wpbdp-regions');
                    $value = $no;
                }
                break;
            case 'enabled':
                if ($regions->is_enabled($region_id)) {
                    $actions['disable'] = _x('Disable', 'regions-module', 'wpbdp-regions');
                    $value = $yes;
                } else {
                    $actions['enable'] = _x('Enable', 'regions-module', 'wpbdp-regions');
                    $value = $no;
                }
                break;
            // case 'localized':
            //     if ($regions->is_localized($region_id)) {
            //         $actions['delocalize'] = _x('Delocalize', 'regions-module', 'wpbdp-regions');
            //         $value = $yes;
            //     } else {
            //         $actions['localize'] = _x('Localize', 'regions-module', 'wpbdp-regions');
            //         $value = $no;
            //     }
            case 'frontend-links':
                if ( ! $regions->is_enabled( $region_id ) )
                    break;

                $output  = '<a href="' . esc_url( $regions->region_link( $region_id ) ) . '" class="display-link">' .
                            _x( 'Region home page', 'regions-module', 'wpbdp-regions' ) .
                            '</a>' . '<br />';
                $output .= '<a href="' . esc_url( $regions->region_listings_link( $region_id ) ) . '" class="display-link">' .
                            _x( 'Region listings', 'regions-module', 'wpbdp-regions' ) . 
                            '</a>';

                return $output;
                break;

       }

        foreach ($actions as $action => $label) {
            $url = add_query_arg('action', $action, $this->get_edit_link($region_id));
            $actions[$action] = '<a href="' . esc_url($url) . '" >' . $label . '</a>';
        }

        $output = "<span>$value</span> <br />";
        $output.= $wp_list_table->row_actions($actions);

        return $output;
    }

    /* Queries and other stuff */

    /**
     * See get_items_per_page at class-wp-list-table.php
     */
    public function get_items_per_page($option, $default=20) {
        $taxonomy = wpbdp_regions_taxonomy();

        if ($per_page = get_user_option("edit_{$taxonomy}_per_page")) {
            $per_page = (int) $per_page;
        } else {
            $option = sprintf("edit_%s_per_page", str_replace('-', '_', $taxonomy));
            $per_page = (int) get_user_option($option);
        }

        if (empty($per_page) || $per_page < 1)
            $per_page = $default;

        return $per_page;
    }

    /**
     * See set_screen_option at wp-admin/includes/misc.php
     */
    public function set_screen_option($sanitized, $option, $value) {
        $taxonomy = wpbdp_regions_taxonomy();
        $taxonomy = str_replace('-', '_', $taxonomy);

        switch ($option) {
            case "edit_{$taxonomy}_per_page":
                $value = (int) $value;
                if ($value < 1 || $value > 999)
                    return $sanitized;
                return $value;
            default:
                return $sanitized;
        }
    }

    public function get_terms_args($args, $taxonomies) {
        static $_args = null;

        // internal affairs
        if (isset($args['wpbdp-regions-skip'])) return $args;

        $screen = get_current_screen();
        if (is_null($screen) || $screen->id != $this->screen)
            return $args;

        // out of jurisdiction
        $taxonomy = wpbdp_regions_taxonomy();
        if (!in_array($taxonomy, $taxonomies)) return $args;

        // most likely called from wp_dropdown_categories(), skip
        if (isset($args['class']) && $args['class'] === 'postform')
            return $args;

        // most likely called from _get_term_hierarchy, skip or
        // enjoy the infinte recursion!
        $children = get_option("{$taxonomy}_children");
        if (!is_array($children)) return $args;

        // there is no need to calculate $_args more than once, because they
        // depend on the request data only
        if (is_array($_args)) return array_merge($args, $_args);

        $regions = wpbdp_regions_api();
        $user = wp_get_current_user();
        $_args = array();

        if ($filter = $this->get_filter()) {
            switch ($filter) {
                case 'enabled':
                    $_args['enabled'] = true;
                    break;
                case 'disabled':
                    $_args['enabled'] = false;
                    break;
                case 'on-sidelist':
                    $_args['sidelist'] = true;
                    break;
                case 'not-on-sidelist':
                    $_args['sidelist'] = false;
                    break;
                default:
                    $filter = 'all';
            }

            if ($filter) {
                update_user_meta($user->ID, 'wpbdp-regions-admin-filter', $filter);
            }
        }

        if ($parent = $this->get_parent()) {
            $hierarchy = array();
            $level = $regions->get_region_level($parent, $hierarchy);

            $params = array('fields' => 'ids', 'hide_empty' => false, 'wpbdp-regions-skip' => true, 'child_of' => $parent);
            $children = get_terms($taxonomy, $params);

            array_splice($children, 0, 0, $hierarchy);

            $_args['include'] = $children;

            if (!empty($children)) {
                update_user_meta($user->ID, 'wpbdp-regions-admin-parent', $parent);
            }
        } else {
            delete_user_meta($user->ID, 'wpbdp-regions-admin-parent');
        }

        return array_merge($args, $_args);
    }

    /* Additional UI */

    private function get_bulk_actions() {
        $actions = array();
        $actions['bulk-show'] = _x('Show on Sidelist', 'regions-module', 'wpbdp-regions');
        $actions['bulk-hide'] = _x('Hide on Sidelist', 'regions-module', 'wpbdp-regions');
        $actions['bulk-enable'] = _x('Enable', 'regions-module', 'wpbdp-regions');
        $actions['bulk-disable'] = _x('Disable', 'regions-module', 'wpbdp-regions');

        // if ($this->is_localizing()) {
        //     $actions['bulk-localize'] = _x('Localize', 'regions-module', 'wpbdp-regions');
        //     $actions['bulk-delocalize'] = _x('Delocalize', 'regions-module', 'wpbdp-regions');
        // }

        return $actions;
    }

    private function bulk_actions() {

        $options = array();
        foreach ($this->get_bulk_actions() as $name => $title) {
            $options[] = "\t<option value='$name'>$title</option>\n";
        }

        return join('', $options);
    }

    // private function localize_form() {
    //     $buttons = array();

    //     $enabled = wpbdp_regions_api()->is_localization_enabled();
    //     if (!$this->is_localizing()) {
    //         $buttons['localize-regions'] = _x('Localize', 'regions-module', 'wpbdp-regions');
    //     }

    //     if ($enabled && $this->is_localizing()) {
    //         $buttons['localize-next-level'] = _x('Localize Next Level', 'regions-module', 'wpbdp-regions');
    //         $buttons['localize-finish'] = _x('Finish Localization', 'regions-module', 'wpbdp-regions');
    //     }

    //     if ($enabled || $this->is_localizing()) {
    //         $buttons['localize-disable'] = _x('Disable', 'regions-module', 'wpbdp-regions');
    //     }

    //     $taxonomy = wpbdp_regions_taxonomy();

    //     $form = '<form class="localize-regions" method="post">';
    //     $form.= '<input type="hidden" value="' . $taxonomy. '" name="taxonomy">';
    //     $form.= '<input type="hidden" value="' . wpbdp_post_type() . '" name="post_type">';
    //     $form.= wp_nonce_field('localize-regions', '_wpnonce', true, false);
    //     $form.= '<label><strong>' . _x('Localization', 'regions-module', 'wpbdp-regions') . '</strong>:&nbsp;</label>';

    //     foreach ($buttons as $name => $value) {
    //         $button = '<input type="submit" class="button-secondary action" name="%s" value="%s" />';
    //         $form .= sprintf($button, esc_attr($name), esc_attr($value));
    //     }

    //     $form.= '</form>';

    //     return $form;
    // }

    private function regions_form() {
        ob_start();
            include(WPBDP_REGIONS_MODULE_DIR . '/templates/form-add-regions.tpl.php');
            $html = ob_get_contents();
        ob_end_clean();

        return $html;
    }

    private function views() {
        global $wp_list_table;

        if (!$wp_list_table) return;

        ob_start();
            $wp_list_table->views();
            $views = ob_get_contents();
        ob_end_clean();

        $html = '<div class="wpbdp-regions-views"><span>%s</span>%s</div>';

        return sprintf($html, _x('Show Regions:', 'regions-module', 'wpbdp-regions'), $views);
    }

    public function templates() {
        return array(
            'bulk-actions' => $this->bulk_actions(),
            // 'localize-form' => $this->localize_form(),
            'add-regions-form' => $this->regions_form(),
            'views' => $this->views()
        );
    }

    /* Ajax functions */

    public function ajax() {
        global $wp_list_table;

        $region_id = isset($_REQUEST['region']) ? $_REQUEST['region'] : 0;
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        $action = str_replace('wpbdp-regions-', '', $action);

        $regions = wpbdp_regions_api();
        $updated = array();

        switch ($action) {
            case 'show':
                $columns = array('sidelist', 'enabled');
                $result = $regions->set_sidelist_status($region_id, true, $updated);
                break;

            case 'hide':
                $columns = array('sidelist');
                $result = $regions->set_sidelist_status($region_id, false, $updated);
                break;

            case 'enable':
                $columns = array('enabled');
                $result = $regions->set_enabled($region_id, true, $updated);
                break;

            case 'disable':
                $columns = array('enabled');
                $result = $regions->set_enabled($region_id, false, $updated);
                break;
        }

        if (!is_object($wp_list_table)) {
            set_current_screen($this->screen);
            $wp_list_table = _get_list_table('WP_Terms_List_Table', array('screen' => $this->screen));
        }

        if ($result) {
            foreach ($columns as $column) {
                $html[$column] = $this->manage_custom_column('', $column, $region_id);
            }
            $response = array('success' => true, 'html' => $html, 'updated' => $updated);
        } else {
            $response = array();
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    /* Meta Box */

    public function add_meta_box() {
        $taxonomy = wpbdp_regions_taxonomy();
        $post_type = WPBDP_POST_TYPE;

        // remove standard meta box for Regions taxonomy
        remove_meta_box($taxonomy . 'div', $post_type, 'side');

        add_meta_box($taxonomy . '-meta-box',
            _x('Listing Region', 'regions meta box', 'wpbdp-regions'),
            array($this, 'meta_box'),
            $post_type,
            'side',
            'core'
        );
    }

    public function meta_box() {
        global $post;

        wp_enqueue_script('wpbdp-regions-frontend');

        $wpbdp_regions = wpbdp_regions();
        $regionfields = wpbdp_regions_fields_api();

        $value = null;
        foreach ($regionfields->get_visible_fields() as $field) {
            $value = $regionfields->field_value(null, $post->ID, $field, false);
            echo $field->render( $value );
        }
    }

    public function save_meta_box($post_id) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;

        $post_type = WPBDP_POST_TYPE;

        if ($post_type != wpbdp_getv($_POST, 'post_type')) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (!isset($_POST['listingfields']))
            return;

        wpbdp_regions()->update_listing($post_id, $_POST['listingfields']);
    }
}
