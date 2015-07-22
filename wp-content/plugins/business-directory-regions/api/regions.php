<?php

function wpbdp_regions_taxonomy() {
    return WPBDP_RegionsPlugin::TAXONOMY;
}

function wpbdp_regions_api() {
    return WPBDP_RegionsAPI::instance();
}

class WPBDP_RegionsAPI {

    private static $instance = null;
    private $max_level = null;
    private $active_region = null;

    const META_TYPE = 'region';

    private function __construct() {
        add_action( 'parse_query', array( &$this, 'determine_active_region' ) );
    }

    public static function instance() {
        if (is_null(self::$instance))
            self::$instance = new WPBDP_RegionsAPI();
        return self::$instance;
    }

    /* */

    // public function is_localization_enabled() {
    //     return (bool) get_option('wpbdp-regions-localization-enabled', false, true);
    // }

    // public function enable_localization() {
    //     update_option('wpbdp-regions-localization-enabled', true);
    //     $this->clean_localized_regions_cache();
    // }

    // public function disable_localization() {
    //     delete_option('wpbdp-regions-localization-enabled');
    //     $this->clean_localized_regions_cache();
    // }

    /* Metadata API */

    public function add_meta($region_id, $meta_key, $meta_value, $unique=false) {
        return add_metadata(self::META_TYPE, $region_id, $meta_key, $meta_value, $unique);
    }

    public function update_meta($region_id, $meta_key, $meta_value, $prev_value='') {
        return update_metadata(self::META_TYPE, $region_id, $meta_key, $meta_value, $prev_value);
    }

    public function delete_meta($region_id, $meta_key, $meta_value='', $delete_all=false) {
        return delete_metadata(self::META_TYPE, $region_id, $meta_key, $meta_value, $delete_all);
    }

    public function get_meta($region_id, $meta_key='', $single=false) {
        return get_metadata(self::META_TYPE, $region_id, $meta_key, $single);
    }

    /* Set/Get methods */

    public function set_enabled($region_id, $enabled, &$regions=array()) {
        $result = false;

        if (empty($regions)) {
            // disable child regions too
            if ($enabled === false) {
                $_regions = get_term_children($region_id, wpbdp_regions_taxonomy());
                $regions = array_merge((array) $regions, array($region_id), $_regions);
            }

            // enable parent regions too
            if ($enabled === true) {
                $this->get_region_level($region_id, $regions);
            }
        }

        foreach ($regions as $region) {
            $result = $this->update_meta($region, 'enabled', (int) $enabled) || $result;
        }

        // // disabling a region automatically removes it from the sidelist
        // if ($result || $enabled === false) {
        //     $this->set_sidelist_status($region_id, false, $regions);
        // }

        $this->clean_visible_regions_cache();

        return $result;
    }

    public function is_enabled($region_id) {
        $enabled = $this->get_meta($region_id, 'enabled', true);
        return $enabled === '' ? true : (bool) $enabled;
    }

    public function set_sidelist_status($region_id, $on_sidelist, &$regions=array()) {
        $result = false;

        if (empty($regions)) {
            if ($on_sidelist === false) {
                $_regions = get_term_children($region_id, wpbdp_regions_taxonomy());
                $regions = array_merge((array) $regions, array($region_id), $_regions);
            }

            if ($on_sidelist === true) {
                $this->get_region_level($region_id, $regions);
            }
        }


        foreach ($regions as $region) {
            $result = $this->update_meta($region, 'sidelist', (int) $on_sidelist) || $result;
        }

        // adding a region to the sidelist enables it automatically
        if ($result && $on_sidelist) {
            $this->set_enabled($region_id, true, $regions);
        }

        $this->clean_sidelisted_regions_cache();

        return $result;
    }

    public function on_sidelist($region_id) {
        $on_sidelist = $this->get_meta($region_id, 'sidelist', true);
        return $on_sidelist === '' ? true : (bool) $on_sidelist;
    }

    // public function set_localized_status($region_id, $localized) {
    //     $this->clean_localized_regions_cache();
    //     return $this->update_meta($region_id, 'localized', (int) $localized);
    // }

    // public function is_localized($region_id) {
    //     return (bool) $this->get_meta($region_id, 'localized', true);
    // }

    /* Queries */

    // private function filter($regions, $args=array()) {
    //     global $wpdb;

    //     $args = wp_parse_args($args, array(
    //         'localized' => false
    //     ));

    //     $where = array();

    //     if ($args['localized']) {
    //         $where[] = "meta_key = 'localized'";
    //         $where[] = 'meta_value = 1';
    //     }

    //     $query = 'SELECT region_id FROM ' . WPBDP_REGIONS_MODULE_META_TABLE . ' ';
    //     $query.= 'WHERE ' . join(' AND ', $where);

    //     $results = $wpdb->get_col($query);

    //     return array_intersect($regions, $results);
    // }

    public function exists($name, $parent=0) {
        if ($term_id = term_exists($name))
            if ($term_id = term_exists($name, wpbdp_regions_taxonomy(), $parent))
                return $term_id;
        return false;
    }

    public function find($args) {
        static $regions = array();

        $taxonomy = wpbdp_regions_taxonomy();
        $key = md5(serialize($args));
        $regions[$key] = isset($regions[$key]) ? $regions[$key] : get_terms($taxonomy, $args);

        return $regions[$key];
    }

    public function find_by_id($region_id) {
        $taxonomy = wpbdp_regions_taxonomy();
        return get_term($region_id, $taxonomy);
    }

    public function find_by_name($region, $level_hint=0) {
        $regions = get_terms( wpbdp_regions_taxonomy(), array( 'hide_empty' => 0, 'name__like' => $region ) );
        $regions = array_filter( $regions, create_function( '$x', 'return $x->name == \'' . $region . '\';' ) ); // remove inexact matches first

        if ( $regions ) {
            // use $level_hint if available to sort matches
            if ( $level_hint > 0 ) { 
                foreach ( $regions as &$r ) {
                    $r->region_level = $this->get_region_level( $r->term_id );
                }

                usort( $regions, create_function( '$a, $b', 'return abs( $a->region_level - ' . $level_hint .' ) - abs( $b->region_level - ' . $level_hint .' ); ' ) );
            }

            return $regions[0];
        }

        return false;
        // $taxonomy = wpbdp_regions_taxonomy();
        // return get_term_by('name', $region, $taxonomy);
    }

    public function find_by_slug($region) {
        $taxonomy = wpbdp_regions_taxonomy();
        return get_term_by('slug', $region, $taxonomy);
    }

    public function find_top_level_regions($args=array()) {
        $args = wp_parse_args($args, array(
            'parent' => 0,
            'hide_empty' => false,
            'get' => 'all',
            'orderby' => 'id',
            'fields' => 'ids',
            'wpbdp-regions-skip' => true
        ));
        return $this->find($args);
    }

    /**
     * Find Regions by level in the hierarchy.
     *
     * @param $hierarchy    array    holds the ID of all ancestors of the Regions returned
     */
    private function _find_regions_by_level($level=1, $terms=array(), $parents=null, &$hierarchy=array()) {
        if ($parents || is_array($parents)) {
            $parents = (array) $parents;

            if (empty($parents))
                return array();

            $k = $this->get_region_level($parents[0]);

            if ($k === $level)
                return $parents;
            if ($k > $level)
                return array();

            $regions = $parents;
        } else {
            $k = 1;
            $regions = $this->find_top_level_regions();
        }

        // if $level is false, we check as many levels as possible
        while ($level === false ? !empty($regions) : $k < $level) {
            $k = $k + 1;

            $_regions = array();
            foreach ($regions as $region) {
                if (isset($terms[$region]) && is_array($terms[$region]) && !empty($terms[$region])) {
                    $_regions = array_merge($_regions, $terms[$region]);
                    // store the ID of the ancestors of the Regions being returned
                    $hierarchy[] = $region;
                }
            }

            $regions = $_regions;
        }

        if ($level === 1) {
            // use array_values to reset key indexes
            $regions = array_values(array_intersect($regions, array_keys($terms)));
        }

        $this->max_level = $k;

        return $regions;
    }

    public function find_regions_by_level($level) {
        return $this->_find_regions_by_level($level, $this->get_regions_hierarchy());
    }

    public function find_visible_regions_by_level($level, $parent=null) {
        return $this->_find_regions_by_level($level, $this->get_visible_regions_hierarchy(), $parent);
    }

    public function find_sidelisted_regions_by_level($level) {
        return $this->_find_regions_by_level($level ? $level : 1, $this->get_sidelisted_regions_hierarchy());
    }

    // public function find_localized_regions_by_level($level, $ancestors=false) {
    //     if ($level === 1)
    //         return $this->find_top_level_regions();

    //     $hierarchy = array();
    //     $parents = $this->_find_regions_by_level($level - 1, $this->get_localized_regions_hierarchy(), null, $hierarchy);
    //     $regions = $this->_find_regions_by_level($level, $this->get_regions_hierarchy(), $parents);

    //     // include ancestor regions IDs
    //     if ($ancestors && !empty($regions))
    //         array_splice($regions, 0, 0, array_merge($parents, $hierarchy));

    //     return $regions;
    // }

    private function _get_hierarchy($option, $args) {
        $children = get_option($option);
        if (is_array($children)) return $children;

        $terms = $this->find(wp_parse_args($args, array(
            'get' => 'all',
            'orderby' => 'id',
            'fields' => 'id=>parent',
            'wpbdp-regions-skip' => true
        )));

        $children = array();
        foreach ($terms as $term_id => $parent) {
            if ($parent > 0) {
                $children[$parent][] = $term_id;
            } else if (!isset($children[$term_id])) {
                // also save top-level regions with no children
                $children[$term_id] = array();
            }
        }

        update_option($option, $children);

        return $children;
    }

    public function get_regions_hierarchy() {
        return _get_term_hierarchy(wpbdp_regions_taxonomy());
    }

    // public function get_localized_regions_hierarchy() {
    //     return $this->_get_hierarchy('wpbdp-localized-regions-children', array(
    //         'localized' => true
    //     ));
    // }

    public function get_sidelisted_regions_hierarchy() {
        return $this->_get_hierarchy('wpbdp-sidelisted-regions-children', array(
            // 'localized' => $this->is_localization_enabled(),
            'enabled' => true,
            'sidelist' => true,
        ));
    }

    public function get_visible_regions_hierarchy() {
        return $this->_get_hierarchy('wpbdp-visible-regions-children', array(
            // 'localized' => $this->is_localization_enabled(),
            'enabled' => true
        ));
    }

    private function set_regions_cookie($value='', $expiration=1209600) {
        if ($user = wp_get_current_user())
            $cookies[] = sprintf("wpbdp-regions-active-regions-%d", $user->ID);
        $cookies[] = sprintf("wpbdp-regions-active-regions", $user->ID);

        $expire = time() + $expiration;
        $secure = is_ssl();

        foreach ($cookies as $cookie) {
            setcookie($cookie, $value, $expire, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
            if (COOKIEPATH != SITECOOKIEPATH)
                setcookie($cookie, $value, $expire, SITECOOKIEPATH, COOKIE_DOMAIN, $secure, true);
        }
    }

/*    private function get_region_cookie() {
        if ($user = wp_get_current_user())
            $cookies[] = sprintf("wpbdp-regions-active-regions-%d", $user->ID);
        $cookies[] = sprintf("wpbdp-regions-active-regions", $user->ID);

        foreach ($cookies as $cookie) {
            if (isset($_COOKIE[$cookie])) {
                $value = unserialize(base64_decode($_COOKIE[$cookie]));
                if (is_array($value))
                    return $value;
            }
        }

        return array();
    }*/

    public function set_active_region($region_id) {
        $hierarchy = array();
        $level = $this->get_region_level($region_id, $hierarchy);

        $active = array();
        for ($i = 1; $i <= $level; $i++) {
            $active[$i] = $hierarchy[$level - $i];
        }

        $this->set_regions_cookie(base64_encode(serialize($active)));
    }

    public function clear_active_region() {
        $this->active_region = null;
        $this->set_regions_cookie('', -3600);
    }

    public function get_active_region() {
        if ( $this->active_region )
            return $this->active_region->term_id;

        return null;
    }

    public function get_active_region_by_level($level) {
        $current = $this->get_active_region();

        if ( ! $current )
            return null;

        $hierarchy = array();
        $active = array();
        $current_level = $this->get_region_level( $current, $hierarchy );

        for ( $i = 1; $i <= $current_level; $i++ )
            $active[ $i ] = $hierarchy[ $current_level - $i ];

        return wpbdp_getv( $active, $level, null );
    }

    public function get_region_level($region_id, &$hierarchy=array()) {
        $taxonomy = wpbdp_regions_taxonomy();
        $level = 0;

        do {
            $term = get_term($region_id, $taxonomy);
            if (is_null($term) || is_wp_error($term))
                break;

            array_push($hierarchy, $region_id);

            $region_id = $term->parent;
            $level++;
        } while ($region_id > 0);

        return $level;
    }

    public function get_max_level() {
        $max = get_option('wpbdp-regions-max-level', null);

        if (is_null($max)) {
            $this->find_regions_by_level(false);
            $max = $this->max_level - 1;
            update_option('wpbdp-regions-max-level', $max);
        }

        return $max;
    }

    /* Cache */

    public function clean_regions_cache() {
        delete_option('wpbdp-regions-max-level');
        $this->clean_regions_count_cache();
        $this->clean_visible_regions_cache();

        // trigger fields detection
        update_option('wpbdp-regions-create-fields', true);
    }

    public function clean_regions_count_cache() {
        delete_option('wpbdp-category-regions-count');
    }

    public function clean_visible_regions_cache() {
        delete_option('wpbdp-visible-regions-children');
        $this->clean_sidelisted_regions_cache();
    }

    public function clean_sidelisted_regions_cache() {
        delete_option('wpbdp-sidelisted-regions-children');
    }

    /* Taxonomy API integration */

    public function insert($name, $parent=0) {
        return wp_insert_term($name, wpbdp_regions_taxonomy(), array('parent' => $parent));
    }

    public function determine_active_region( $wp_query ) {
        if ( $this->active_region )
            return;

        $region = null;

        if ( isset( $wp_query->query_vars['region'] ) ) {
            $region_ = $wp_query->query_vars['region'];

            if ( is_numeric( $region_ ) )
                $region = $this->find_by_id( $region_ );
            else
                $region = $this->find_by_slug( $region_ );
        }

        $this->active_region = $region;
    }

    // {{{ Link generation

    public function remove_url_filter( $url ) {
        $slug = wpbdp_get_option( 'regions-slug' );

        if ( wpbdp_rewrite_on() ) {
            $newurl = preg_replace( "/(" . $slug . "\\/[^\\/]*)/ui", '', $url );

            if ( false === strpos( $newurl, '?' ) )
                $newurl = trailingslashit( $newurl );
        }

        $newurl = remove_query_arg( 'region', $newurl );

        return $newurl;
    }

    public function region_link( $region, $smart = false, $origin = array() ) {
        $region = is_object( $region ) ? $region : get_term( $region, wpbdp_regions_taxonomy() );

        if ( ! $region )
            return '';

        $base_page = wpbdp_get_page_link( 'main' );

        if ( $smart && wpbdp_rewrite_on() ) {
            $base_page = $origin ? $origin['referer'] : $_SERVER['REQUEST_URI'];

            // Remove pagination from base page.
            global $wp_rewrite;
            $pagination_slug = $wp_rewrite->pagination_base;
            $base_page = preg_replace( "/(\/{$pagination_slug}\/[0-9]+?)/uis", '', $base_page );
            $base_page = str_replace( '//', '/', $base_page );

            $slug = wpbdp_get_option( 'permalinks-category-slug' );
            $region_slug = wpbdp_get_option( 'regions-slug' );

            global $wpbdp;
            $action = $origin ? $origin['action'] : $wpbdp->controller->get_current_action();

            switch ( $action ) {
                case 'browsecategory':
                    $pattern = "/(?<category>" . $slug . "\\/[^\\/]*)/ui";
    
                    if ( false == preg_match( $pattern, $base_page, $matches ) )
                        return $base_page;

                    // Remove current region from URI.
                    $base_page = preg_replace( "/(" . $region_slug . "\\/[^\\/]*)/ui", '', $base_page );

                    return preg_replace( $pattern,
                                         $matches['category'] . '/' . $region_slug . '/' . $region->slug,
                                         untrailingslashit( $base_page ) );
                    break;
                case 'main':
                    return untrailingslashit( wpbdp_get_page_link( 'main' ) ) . '/' . $region_slug . '/' . $region->slug . '/';
                    break;
                default:
                    break;
            }
        }

        $url = add_query_arg( 'region', $region->slug, $base_page );
        return $url;
    }

    public function region_term_link( $region, $term ) {

    }

    public function region_home( $region ) {
        $region = is_object( $region ) ? $region : get_term( $region, wpbdp_regions_taxonomy() );

        if ( ! $region )
            return '';

        $main_page = wpbdp_get_page_link( 'main' );
        $url = '';

        if ( wpbdp_rewrite_on() ) {
            $url = untrailingslashit( $main_page ) . '/' . wpbdp_get_option( 'regions-slug' ) . '/' . $region->slug . '/';
        } else {
            $url = add_query_arg( 'region', $region->slug, $main_page );
        }

        return $url;
    }

    public function region_listings_link( $region ) {
        $region = is_object( $region ) ? $region : get_term( $region, wpbdp_regions_taxonomy() );

        if ( ! $region )
            return '';

        $main_page = wpbdp_get_page_link( 'main' );

        if ( wpbdp_rewrite_on() ) {
            $url = untrailingslashit( $main_page ) . '/' . wpbdp_get_option( 'permalinks-directory-slug' ) . '/' . wpbdp_get_option( 'regions-slug' ) . '/' . $region->slug . '/';
        } else {
            $url = add_query_arg( array( 'region' => $region->slug,
                                         'action' => 'region-listings',
                                       ), $main_page );
        }

        return $url;
    }

    // }}}

}
