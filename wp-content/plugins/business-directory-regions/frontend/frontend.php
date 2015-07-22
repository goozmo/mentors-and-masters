<?php

class WPBDP_RegionsFrontend {

    private $selector = true;

    public function __construct() {
        add_action( 'widgets_init', array( &$this, 'register_widgets' ) );
        add_filter( 'wpbdp_shortcodes', array( &$this, 'add_shortcodes' ) );

        add_filter('page_rewrite_rules', array($this, 'rewrite_rules'));
        add_filter('query_vars', array($this, 'query_vars'));
        add_action('template_redirect', array($this, 'template_redirect'));
        add_action( 'template_redirect', array( &$this, 'handle_widget_search' ) );

        add_filter('wpbdp_template_vars', array($this, 'render_region_sidelist'), 10, 2);
        add_filter('wpbdp_template_vars', array($this, 'render_region_selector'), 9, 2);

        add_action('wp_ajax_wpbdp-regions-get-regions', array($this, 'ajax'));
        add_action('wp_ajax_nopriv_wpbdp-regions-get-regions', array($this, 'ajax'));
        add_action( 'wpbdp_rewrite_rules', array( &$this, 'main_page_rewrite_rules' ) );
        add_action( 'wpbdp_category_link', array( &$this, 'category_link' ), 10, 2 );
    }

    public function register_widgets() {
        require_once( WPBDP_REGIONS_MODULE_DIR . '/frontend/widgets.php' );
        register_widget( 'WPBDP_Region_Search_Widget' );
    }

    public function add_shortcodes( $shortcodes ) {
        $shortcodes += array_fill_keys( array( 'wpbdp-region',
                                               'businessdirectory-regions-region',
                                               'businessdirectory-region',
                                               'business-directory-regions-region',
                                               'business-directory-region',
                                               'business-directory-regions' ),
                                        array( &$this, 'shortcode' ) );
        $shortcodes += array_fill_keys( array( 'wpbdp_regions_browser',
                                               'businessdirectory-regions-browser',
                                               'business-directory-regions-browser' ),
                                        array( &$this, 'regions_browser_shortcode' ) );
        return $shortcodes;
    }

    public function query_vars($vars) {
        array_push($vars, 'bd-module');
        array_push($vars, 'bd-action');
        array_push($vars, 'region-id');
        array_push( $vars, 'region' );

        return $vars;
    }

    public function rewrite_rules($rules) {
        global $wpdb;
        global $wp_rewrite;

        $shortcode_pages = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE %s AND post_status = %s AND post_type = %s",
                                                           '%[wpbdp_regions_browser%',
                                                           'publish',
                                                           'page' ) );
        add_rewrite_tag( '%region_path%', '(.*)' );

        foreach ( $shortcode_pages as $page_id ) {
            $rewrite_base = str_replace( 'index.php/', '',
                                         rtrim( str_replace( home_url() . '/',
                                                             '',
                                                             untrailingslashit( get_permalink( $page_id ) ) . '/(.*)' ),
                                         '/' ) );
            $rules[$rewrite_base] = 'index.php?page_id=' . $page_id . '&region_path=$matches[1]';
        }

        add_rewrite_rule('wpbdp/api/regions/set-location', 'index.php?bd-module=wpbdp-regions&bd-action=set-location', 'top');
        return $rules;
    }

    public function template_redirect() {
        $module = get_query_var('bd-module');
        $action = get_query_var('bd-action');

        if ($module != 'wpbdp-regions') return;
        if ($action != 'set-location') return;

        $regions = wpbdp_regions_api();
        $redirect = isset($_REQUEST['redirect']) ? trim($_REQUEST['redirect']) : wp_get_referer();

        if (isset($_POST['set-location'])) {
            $origin_data = array();
            parse_str( urldecode( base64_decode( isset( $_POST['origin'] ) ? $_POST['origin'] : '' ) ), $origin_data );

            $regionfields = wpbdp_regions_fields_api();
            $data = wpbdp_getv($_POST, 'listingfields', array());
            $region = false;

            foreach ($regionfields->get_fields('desc') as $level => $id) {
                if (isset($data[$id]) && $data[$id] > 0) {
                    $region = $data[$id];
                    break;
                }
            }

            if ( $region && $origin_data )
                $redirect = $regions->region_link( $region, true, $origin_data );
        } else if (isset($_POST['clear-location'])) {
            $origin_data = array();
            parse_str( urldecode( base64_decode( isset( $_POST['origin'] ) ? $_POST['origin'] : '' ) ), $origin_data );
            $redirect = $regions->remove_url_filter( $origin_data['referer'] );
            $regions->clear_active_region();

        }/* else if (isset($_REQUEST['region-id'])) {
            $regions->set_active_region((int) $_REQUEST['region-id']);
        }*/

        wp_redirect( $redirect );
        exit();
    }

    /**
     * @since 3.6
     */
    public function handle_widget_search() {
        $module = get_query_var('bd-module');
        $action = get_query_var('bd-action');

        if ( 'regions' != $module )
            return;

        if ( 'widget-search' != $action )
            return;

        $limit = isset( $_POST['numberposts'] ) ? intval( $_POST['numberposts'] ) : 0;

        $regions_api = wpbdp_regions_api();
        $regions_fields_api = wpbdp_regions_fields_api();
        $region_id = 0;
        $region = null;

        foreach ( $regions_fields_api->get_fields( 'desc' ) as $level => $field_id  ) {
            if ( isset( $_POST['listingfields'][ $field_id ] ) && $_POST['listingfields'][ $field_id ] > 0  ) {
                $region_id = $_POST['listingfields'][ $field_id ];
                break;
            }
        }

        $region = $regions_api->find_by_id( $region_id );

        if ( ! $region )
            die(); // TODO: maybe 404?

        $redirect = $regions_api->region_listings_link( $region );
        $redirect = add_query_arg( 'limit', $limit, $redirect );

        wp_redirect( $redirect );
        die();
    }

    public function search_where($where, $args) {
        global $wpdb;

        $regionfields = wpbdp_regions_fields_api();
        $fields = $regionfields->get_visible_fields();

        $terms = array();
        // fields are sorted from top to bottom in Region hierarchy,
        // consider the Region selected in the greatest (deeper) level only
        foreach ($fields as $field) {
            foreach ($args['fields'] as $query) {
                if ($query['field_id'] == $field->get_id() && !empty($query['q']))
                    $terms = array($query['q']);
            }
        }

        if (empty($terms)) return $where;

        $query = "SELECT rp.ID FROM {$wpdb->posts} AS rp ";
        $query.= "JOIN {$wpdb->term_relationships} AS rtr ON (rp.ID = rtr.object_id) ";
        $query.= "JOIN {$wpdb->term_taxonomy} AS rtt ";
        $query.= sprintf("ON (rtr.term_taxonomy_id = rtt.term_taxonomy_id AND rtt.term_id IN (%s))", join(',', $terms));

        return sprintf("%s AND {$wpdb->posts}.ID IN (%s)", $where, $query);
    }

    public function url() {
        if (get_option('permalink_structure'))
            return home_url('/wpbdp/api/regions/set-location');
        return home_url('index.php?bd-module=wpbdp-regions&bd-action=set-location');
    }

    public function origin_hash() {
        global $wpbdp;
        $data = array( 'action' => $wpbdp->controller->get_current_action(),
                       'referer' => $_SERVER['REQUEST_URI'] );
        return base64_encode( http_build_query( $data ) );
    }

    private function get_current_location() {
        $regions = wpbdp_regions_api();
        $active = $regions->get_active_region();
        $hierarchy = array();

        $level = $regions->get_region_level($active, $hierarchy);
        $min = wpbdp_regions_fields_api()->get_min_visible_level();

        $text = _x('Displaying listings from %s.', 'region-selector', 'wpbdp-regions');

        if (is_null($active) || $level < $min) {
            return sprintf($text, _x('all locations', 'region-selector', 'wpbdp-regions'));
        }

        $names = array();
        for ($i = $min; $i <= $level; $i++) {
            $names[] = $regions->find_by_id($hierarchy[$level - $i])->name;
        }

        return sprintf($text, sprintf('<strong>%s</strong>', join('&nbsp;&#8594;&nbsp;', $names)));
    }

    private function _render_region_sidelist($regions, $children, $args = array()) {
        $open_by_default = isset( $args['open_by_default'] ) ? $args['open_by_default'] : array();

        $api = wpbdp_regions_api();
        $show_counts = wpbdp_get_option('regions-sidelist-counts');
        $hide_empty = wpbdp_get_option('regions-sidelist-hide-empty');

        if ($show_counts)
            $item = '<a href="#" data-url="%s" data-region-id="%d">%s</a> (%d)';
        else
            $item = '<a href="#" data-url="%s" data-region-id="%d">%s</a>';

        $baseurl = $this->url();

        if (!empty($regions)) {
            $regions = $api->find(array('include' => $regions, 'hide_empty' => 0));
        }

        $html = '';
        foreach ($regions as $region) {
            if ($hide_empty && $region->count == 0)
                continue;

            $url = add_query_arg('region-id', $region->term_id, $baseurl);
            if ( is_paged() )
                $url = add_query_arg( 'redirect', get_pagenum_link(1, true), $url );

            $url = $api->region_link( $region, true );

            $html .= '<li>';
            $html .= $show_counts ? sprintf($item, $url, $region->term_id, $region->name, intval($region->count)) : sprintf($item, $url, $region->term_id, $region->name);

            if (isset($children[$region->term_id]) && is_array($children[$region->term_id])) {
                $html .= '<a class="js-handler" href="#"><span></span></a>';
                $html .= sprintf( '<ul data-collapsible="true" data-collapsible-default-mode="%s">%s</ul>',
                                  wpbdp_get_option( 'regions-sidelist-autoexpand' ) || in_array( $region->term_id, $open_by_default, true ) ? 'open' : '',
                                  $this->_render_region_sidelist($children[$region->term_id], $children, $args) );
            }

            $html .= '</li>';
        }

        return $html;
    }

    public function render_region_sidelist($vars, $template) {
        static $search = false;
        static $processed = false;

        if (!wpbdp_get_option('regions-show-sidelist')) return $vars;

        // only one region sidelist per request
        if ($processed) return $vars;

        // businessdirectory-listings is rendered from the search template,
        // however, we don't want to show the sidelist in that case
        $match = array_intersect((array) $template, array('search'));
        if (!empty($match)) $search = true;
        if ($search) return $vars;

        // only show sidelist on main page or listings page
        $pages = array('businessdirectory-main-page', 'businessdirectory-listings');
        $match = array_intersect((array) $template, $pages);
        if (empty($match)) return $vars;

        $children = wpbdp_regions_api()->get_sidelisted_regions_hierarchy();
        $level = wpbdp_regions_fields_api()->get_min_visible_level();
        $regions = wpbdp_regions_api()->find_sidelisted_regions_by_level($level);

        $args = array();
        if ( wpbdp_get_option( 'regions-sidelist-expand-current' ) && function_exists( 'get_ancestors' ) ) {
            $api = wpbdp_regions_api();
            if ( $current = $api->get_active_region() ) {
                $args['open_by_default'] = array_merge( array( $current ), get_ancestors( $current, wpbdp_regions_taxonomy() ) );
            }
        }

        $html  = '';
        $html .= '<div class="wpbdp-region-sidelist-wrapper">';
        $html .= '<input type="button" class="sidelist-menu-toggle" value="' . _x( 'Regions Menu', 'sidelist', 'wpbdp-regions' ) . '" />';
        $html .= '<ul class="wpbdp-region-sidelist">%s</ul>';
        $html .= '</div>';
        $html = sprintf($html, $this->_render_region_sidelist($regions, $children, $args));

        $vars['__page__']['class'] = array_merge($vars['__page__']['class'], array('with-region-sidelist'));
        $vars['__page__']['before_content'] = $vars['__page__']['before_content'] . $html;

        $processed = true;

        return $vars;
    }

    public function render_region_selector($vars, $template) {
        static $processed = false;
        static $search = false;

        if ( !$this->selector || wpbdp_get_option( 'regions-hide-selector' ) ||
             wpbdp_starts_with( $template, 'submit-listing', false ) )
            return $vars;

        // only one region sidelist per request
        if ($processed) return $vars;

        // // businessdirectory-listings is rendered from the search template,
        // // however, we don't want to show the sidelist in that case
        // $match = array_intersect((array) $template, array('search'));
        // if (!empty($match)) $search = true;
        // if ($search) return $vars;

        $formfields = wpbdp()->formfields;
        $region_fields = wpbdp_regions_fields_api();

        $fields = array();
        $value = null;

        foreach (wpbdp_regions_fields_api()->get_visible_fields() as $field) {
            if (!is_null($value)) {
                wpbdp_regions()->set('parent-for-' . $field->get_id(), $value);
            }

             // get active region for this field
            $value = $region_fields->field_value(null, null, $field, true);
            $fields[] = $field->render( $value );
        }

        ob_start();
            include(WPBDP_REGIONS_MODULE_DIR . '/templates/region-selector.tpl.php');
            $region_selector = ob_get_contents();
        ob_end_clean();

        $vars['__page__']['before_content'] = $vars['__page__']['before_content'] . $region_selector;

        $processed = true;

        return $vars;
    }

    public function shortcode($attrs) {
        extract(shortcode_atts(array(
            'region' => false,
            'children' => true
        ), $attrs));

        if ( is_numeric( $region ) ) {
            $region = wpbdp_regions_api()->find_by_id($region);
        } else {
            $region = wpbdp_regions_api()->find_by_name( $region );

            if ( ! $region )
                $region = wpbdp_regions_api()->find_by_slug( $region );
        }

        if ( ! $region || is_null( $region ) )
            return _x("The specified Region doesn't exist.", "region shortcode", 'wpbdp-regions');

        $paged = 1;
        if (get_query_var('page'))
            $paged = get_query_var('page');
        else if (get_query_var('paged'))
            $paged = get_query_var('paged');

        query_posts(array(
            'post_type' => WPBDP_POST_TYPE,
            'posts_per_page' => wpbdp_get_option( 'listings-per-page' ) > 0 ? wpbdp_get_option( 'listings-per-page' ) : -1,
            'post_status' => 'publish',
            'paged' => intval($paged),
            'orderby' => wpbdp_get_option('listings-order-by', 'date'),
            'order' => wpbdp_get_option('listings-sort', 'ASC'),
            'tax_query' => array(
                array(
                    'taxonomy' => wpbdp_regions_taxonomy(),
                    'field' => 'id',
                    'terms' => array($region->term_id),
                    'include_children' => $children,
                    'operator' => 'IN'
                )
            )
        ));

        // disable region selector
        $this->selector = false;

        $params = array('excludebuttons' => false);
        $html = wpbdp_render('businessdirectory-listings', $params, true);

        wp_reset_query();

        return $html;
    }

    /*
     * Regions browser shortcode.
     */

    public function regions_browser_shortcode( $args ) {
        $args = wp_parse_args( $args, array(
            'base_region' => null,
            /*'max_level' => null,*/
            'breadcrumbs' => 1
        ) );

        $api = wpbdp_regions_api();
        $forms_api = wpbdp_regions_fields_api();

        extract( $args );

        if ( $base_region ) {
            $base_region = get_term_by( is_numeric ( $base_region ) ? 'id' : 'name', $base_region, wpbdp_regions_taxonomy() );
            $base_level = $api->get_region_level( $base_region->term_id );

            if ( !$base_region )
                return '';
        }

        $base_uri = get_permalink();

        $region_path = isset( $_REQUEST['region_path'] ) ? $_REQUEST['region_path'] : get_query_var( 'region_path' );
        if ( ! $region_path )
            $region_path = $base_region->slug;

        $region_path = untrailingslashit( ltrim( $region_path, '/' ) );
        $region_path_ = explode( '/', $region_path );

        $current_region = get_term_by( 'slug', $region_path_[ count( $region_path_ ) - 1 ], wpbdp_regions_taxonomy() );
        $current_region->link = $this->regions_browser_link( $current_region, $base_uri, $region_path, 'current' );

        if ( !$current_region )
            return '';

        $level = $api->get_region_level( $current_region->term_id );
        $api_max_level = $api->get_max_level();
        $next_level_field = $level >= $api_max_level ? null : $forms_api->get_field_by_level( $level + 1 );

        $ids = $api->find_top_level_regions( array( 'parent' => $current_region->term_id ) );
        $regions = $api->find( array( 'include' => $ids ? $ids : array( -1 ), 'orderby' => 'name', 'hide_empty' => false ) );

        foreach ( $regions as &$r ) {
            $r->children = count( get_term_children( $r->term_id, wpbdp_regions_taxonomy() ) );
            $r->link = $this->regions_browser_link( $r, $base_uri, $region_path );
        }

        if ( $level > $base_level )
            $regions = $this->regions_browser_classify( $regions );

        $breadcrumbs_text = $breadcrumbs ? $this->regions_browser_breadcrumb( $region_path_, $base_uri ) : '';
 
        return wpbdp_render_page( WPBDP_REGIONS_MODULE_DIR . '/templates/regions-browser.tpl.php',
                                  array( 'breadcrumbs' => $breadcrumbs_text,
                                         'current_region' => $current_region,
                                         'regions' => $regions,
                                         'field' => $next_level_field,
                                         'alphabetically' => $level > $base_level ? true : false ) );
    }

    private function regions_browser_classify( $regions = array() ) {
        $c = array();

        foreach ( $regions as &$r ) {
            $first_char = $r->name[0];

            if ( !isset( $c[ $first_char ] ) )
                $c[ $first_char ] = array();

            $c[ $first_char ][] = $r;
        }

        return $c;
    }

    private function regions_browser_breadcrumb( $region_id, $base_level = 0 ) {
/*        $parts = array();
        $api = wpbdp_regions_api();

        while ( $region_id ) {
            $term = $api->find_by_id( $region_id );

            if ( $api->get_region_level( $region_id ) >= $base_level )
                $parts[] = sprintf( '<a href="%s">%s</a>', add_query_arg( 'r', $term->term_id ), $term->name );

            $region_id = $term->parent;
        }*/

        $api = wpbdp_regions_api();
        $parts = array();

        $path = '';
        foreach ( $parts_ as $region_slug ) {
            $term = $api->find_by_slug( $region_slug );
            $path .= $term->slug . '/';

            if ( wpbdp_rewrite_on() ) {
                $link = untrailingslashit( $base_uri ) . '/' . ltrim( $path, '/' );
            } else {
                $link = add_query_arg( 'region_path', $path, $base_uri );
            }

           $parts[] = sprintf( '<a href="%s">%s</a>', $link, $term->name );
        }

        return implode( ' &raquo; ', $parts );
    }

    private function regions_browser_link( &$region, $base_uri, $region_path, $custom = '' ) {
        $api = wpbdp_regions_api();
        $region_path = trailingslashit( $region_path );

        if ( 'current' != $custom && $region->children > 0 ) {
            $region_path = $region_path . $region->slug . '/';

            if ( wpbdp_rewrite_on() )
                return trailingslashit( $base_uri ) . $region_path;
            else
                return add_query_arg( 'region_path', $region_path, $base_uri );
        } else {
            return $api->region_home( $region );
        }
    }

    public function ajax() {
        $parent = wpbdp_getv($_REQUEST, 'parent', false);
        $level = wpbdp_getv($_REQUEST, 'level', false);
        $field = wpbdp_getv($_REQUEST, 'field', false);

        // no support for searching by multiple parents
        $parent = is_array($parent) ? array_shift($parent) : $parent;

        $formfields = wpbdp()->formfields;
        $field = $formfields->get_field($field);

        wpbdp_regions()->set('parent-for-' . $field->get_id(), $parent);

        $html = $field->render();

        $response = array('status' => 'ok', 'html' => $html);

        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }

    public function main_page_rewrite_rules( $rules0 ) {
        global $wp_rewrite;

        $page_id = wpbdp_get_page_id( 'main' );
        $rewrite_base = str_replace( 'index.php/', '', rtrim( str_replace( home_url() . '/', '', wpbdp_get_page_link( 'main' ) ), '/' ) );
        $regions_slug = wpbdp_get_option( 'regions-slug' );
        $directory_slug = wpbdp_get_option( 'permalinks-directory-slug' );
        $category_slug = wpbdp_get_option( 'permalinks-category-slug' );
        $tags_slug = wpbdp_get_option( 'permalinks-tags-slug' );
        $pagination = $wp_rewrite->pagination_base;

        $rules = array();

        // All listings in region.
        $rules["($rewrite_base)/$directory_slug/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/?\$"] = 'index.php?page_id=' . $page_id . '&action=region-listings&region=$matches[2]&paged=$matches[3]';
        $rules["($rewrite_base)/$directory_slug/$regions_slug/(.+?)/?\$"] = 'index.php?page_id=' . $page_id . '&action=region-listings&region=$matches[2]';

        // Region + category.
        $rules["($rewrite_base)/$regions_slug/(.+?)/$category_slug/(.+?)/$pagination/?([0-9]{1,})/?\$"] = 'index.php?page_id=' . $page_id . '&category=$matches[3]&region=$matches[2]&paged=$matches[4]';
        $rules["($rewrite_base)/$regions_slug/(.+?)/$category_slug/(.+?)/?\$"] = 'index.php?page_id=' . $page_id . '&category=$matches[3]&region=$matches[2]';

        // Category + region.
        $rules["($rewrite_base)/$category_slug/(.+?)/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/?\$"] = 'index.php?page_id=' . $page_id . '&category=$matches[2]&region=$matches[3]&paged=$matches[4]';
        $rules["($rewrite_base)/$category_slug/(.+?)/$regions_slug/(.+?)/?\$"] = 'index.php?page_id=' . $page_id . '&category=$matches[2]&region=$matches[3]';

        // Region home-page.
        $rules["($rewrite_base)/$regions_slug/(.+?)/$pagination/?([0-9]{1,})/?\$"] = 'index.php?page_id=' . $page_id . '&region=$matches[2]&paged=$matches[3]';
        $rules["($rewrite_base)/$regions_slug/(.+?)/?\$"] = 'index.php?page_id=' . $page_id . '&region=$matches[2]';

        return $rules + $rules0;
    }

    public function category_link( $link, $category ) {
        global $post;

        // Do not append 'region' information to links inside listing views.
        if ( $post && isset( $post->post_type ) && WPBDP_POST_TYPE == $post->post_type )
            return $link;

        $api = wpbdp_regions_api();
        $region_id = $api->get_active_region();

        if ( ! $region_id )
            return $link;

        $region = get_term( $region_id, wpbdp_regions_taxonomy() );

        if ( ! $region )
            return $link;

        if ( wpbdp_rewrite_on() ) {
            $query_string = '';

            if ( false !== preg_match( "/\\?(?<querystring>.*)/ui", $_SERVER['REQUEST_URI'], $matches ) ) {
                if ( ! empty( $matches['querystring'] ) )
                    $query_string = $matches['querystring'];
            }

            $link_x = untrailingslashit( str_replace( wpbdp_get_page_link( 'main' ), '', $link ) );

            $link  = untrailingslashit( wpbdp_get_page_link( 'main' ) );
            $link .= '/' . ltrim( $link_x, '/' );
            $link .= '/' . wpbdp_get_option( 'regions-slug' ) . '/' . $region->slug;
            $link .= '/';
            $link .= $query_string ? '?' . $query_string : '';
        } else {
            $link = add_query_arg( 'region', $region->slug, $link );
        }

        return $link;
    }

}

function wpbdp_regions_region_page_title() {
    $term = null;

    if ( get_query_var('taxonomy') == wpbdp_regions_taxonomy() ) {
        if ($id = get_query_var('term_id'))
            $term = wpbdp_regions_api()->find_by_id($id);
        else if ($slug = get_query_var('term'))
            $term = wpbdp_regions_api()->find_by_slug($slug);
    }

    return is_null($term) ? '' : esc_attr($term->name);
}
