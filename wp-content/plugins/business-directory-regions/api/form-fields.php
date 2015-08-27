<?php

function wpbdp_regions_fields_api() {
    return WPBDP_RegionFieldsAPI::instance();
}

class WPBDP_RegionFieldsAPI {

    private static $instance = null;

    public function __construct() {}

    public static function instance() {
        if (is_null(self::$instance))
            self::$instance = new WPBDP_RegionFieldsAPI();
        return self::$instance;
    }

    /* Business Directory Form Fields API integration */
    /**
     * Handler for the wpbdp_form_field_settings hook.
     */
    public function field_settings(&$field, $association) {
        if ( !$field || $association != 'region' )
            return;

        $settings = array();

        $label = _x( 'Display field in Region selector?', 'field settings', 'wpbdp-regions' );
        $html = '<input type="checkbox" name="field[x_display_in_region_selector]" ' . ($field->has_display_flag('region-selector') ? 'checked="checked"' : '') . ' value="1" />';
        $settings[] = array( $label, $html );

        // $label = _x( 'Display field in submit/edit listing form?', 'field settings', 'wpbdp-regions' );
        // $html = '<input type="checkbox" name="field[x_display_in_form]" ' . ($field->has_display_flag('regions-in-form') ? 'checked="checked"' : '') . ' value="1" />';
        // $settings[] = array( $label, $html );        

        echo WPBDP_FormFieldType::render_admin_settings($settings); 
    }

    /**
     * Handler for the wpbdp_form_field_settings_process hook.
     */
    public function field_settings_process( &$field ) {
        if ($field->get_association() != 'region')
            return;

        if (isset($_POST['field']['x_display_in_region_selector']) && $_POST['field']['x_display_in_region_selector'] == 1)
            $field->add_display_flag('region-selector');
        else
            $field->remove_display_flag('region-selector');

        if (isset($_POST['field']['x_display_in_form']) && $_POST['field']['x_display_in_form'] == 1)
            $field->add_display_flag('regions-in-form');
        else
            $field->remove_display_flag('regions-in-form');        
    }

    /**
     * Handler for the wpbdp_listing_field_value hook.
     *
     * @param  [type]  $value   [description]
     * @param  [type]  $listing_id [description]
     * @param  [type]  $field   [description]
     * @param  boolean $use_active_region  use active region if no value is found
     * @return int the ID of the selected region for this field.
     */
    public function field_value($value, $listing_id, $field, $use_active_region=false) {
        if ( $field->get_association() != 'region' )
            return $value;

        $value = (is_array($value) && isset($value[0])) ? $value[0] : $value;

        if (!empty($value)) return $value;

        $level = $this->get_field_level($field);

        if (is_null($level)) return $value;

        list($value, $parent) = $this->_field_value($field, $level, $listing_id);

        if ($use_active_region && absint($value) === 0) {
            $value = wpbdp_regions_api()->get_active_region_by_level($level);
        }

        // necessary so field_attributes() knows we find a parent region
        // for this field, otherwise, field_attributes() may hide the field.
        if (absint($parent) > 0) {
            wpbdp_regions()->set('parent-for-' . $field->get_id(), $parent);
        }

        if ( !$value )
            return array();

        return $value;
    }

    /**
     * Finds the ID of the selected region for the given field and the ID of
     * the selected region for the parent field, if any.
     *
     * If $listing is given, the ID of the selected region will be the ID
     * of one of the terms associated that listing. Otherwise, the function
     * will look into the posted data to see if a values was submitted for
     * the given field. The same applies for the ID selected for the parent
     * field.
     *
     * @param  [type] $field   [description]
     * @param  [type] $level   [description]
     * @param  [type] $listing [description]
     * @return array
     */
    private function _field_value($field, $level, $listing_id=0) {
        $contexts = array('search', 'submitlisting', 'editlisting');
        $value = $parent = null;

        if ($listing_id) {
            $regions = $this->get_listing_regions($listing_id);
        } elseif (in_array(wpbdp_getv($_REQUEST, 'action'), $contexts)) {
            $regions = $this->get_submitted_regions();
        } else {
            $regions = array();
        }

        if (!empty($regions)) {
            $total = count($regions);
            if (isset($regions[$total - ($level - 1)])) {
                $parent =  $regions[$total - ($level - 1)];
            }
            if (isset($regions[$total - $level])) {
                $value = $regions[$total - $level];
            }
        }

        if (absint($parent) === 0 ) {
            $parent = wpbdp_regions()->get('parent-for-' . $field->get_id());
        }

        if ($level > 1) {
            $ancestor = $this->get_field_by_level($level - 1);
            // we assume that if a field is not being shown, all fields associated
            // to higher levels in the regions hieararchy (the parent fields)
            // are also not being shown.
            $parent_visible = $ancestor ? $ancestor->has_display_flag( 'region-selector' ) : false;
        } else {
            $parent_visible = false;
        }

        // force first visible field to show all options available
        $parent = $parent_visible ? $parent : null;

        return array($value, $parent);
    }

    private function get_listing_regions($listing_id) {
        static $cache = array();

        if (isset($cache[$listing_id])) return $cache[$listing_id];


        $args = array('orderby' => 'id', 'order' => 'DESC', 'fields' => 'ids');
        $regions = wp_get_object_terms($listing_id, wpbdp_regions_taxonomy(), $args);
        $hierarchy = array();

        $api = wpbdp_regions_api();

        foreach ($regions as $id) {
            $api->get_region_level($id, $hierarchy);
            if (count(array_diff($regions, $hierarchy)) === 0) {
                break;
            }

            $hierarchy = array();
        }


        $cache[$listing_id] = $hierarchy;

        return $hierarchy;
    }

    /**
     * Return the hierarchy of the regions submitted by the user
     * while adding/editing a new listing or searching for listings.
     */
    private function get_submitted_regions() {
        static $cache = null;

        if (is_array($cache)) return $cache;


        $regions = wpbdp_regions_api();
        $formfields = wpbdp()->formfields;

        $data = wpbdp_getv($_REQUEST, 'listingfields', array());
        $fields = $this->get_fields();
        arsort($fields);

        $hierarchy = array();
        foreach ($fields as $level => $id) {
            $field = $formfields->get_field($id);
            
            if ( !$field ) continue;
            $region = (int) wpbdp_getv($data, $field->get_id());

            if ($region <= 0) continue;

            $hierarchy = array();
            $regions->get_region_level($region, $hierarchy);

            break;
        }


        $cache = $hierarchy;

        return $hierarchy;
    }

    public function field_attributes(&$field, $selected, $display_context) {
        $level =$this->get_field_level($field);

        // not a region field
        if (is_null($level)) return;

        $field->css_classes[] = 'wpbdp-region-field';
        $field->html_attributes['region-level'] = $level;

        list($_, $parent) = $this->_field_value($field, $level);

        $min = $this->get_min_visible_level();
        // do not render field options if there is no parent region selected.
        // this field will be hidden until a parent region is selected so
        // there is no need to spent time building the list of options
        $should_hide = ( $level > $min && absint( $selected ) === 0 && absint( $parent ) === 0 );
        // do not render field options if the settings say the field should be hidden
        $should_hide = $should_hide || ( 'widget' != $display_context && !$field->has_display_flag( 'region-selector' ) );
        // do not render field options if the field's level is below the
        // min visible level
        $should_hide = $should_hide || ( 'widget' != $display_context && $level < $min );

        if ( $should_hide ) {
            $field->css_classes[] = 'wpbdp-regions-hidden';
        } else if ( 'widget' == $display_context || $level >= $min ) {
            $field->set_data('options', $this->_field_options($field, $level, $selected, $parent, $display_context));
        }
    }

    public function field_option( $option, $field ) {
        $level =$this->get_field_level($field);

        if ( is_null( $level ) || 0 == $option['value'] )
            return $option;

        $regions = wpbdp_regions_api();
        $option['attributes']['data-url'] = $regions->region_link( $option['value'], true );
        return $option;
    }

    public function field_html_value($value, $listing_id, $field) {
        if ( $field->get_association() != 'region' )
            return $value;

        if ( !$value )
            return '';

        $value = is_array( $value ) ? $value[0] : $value;
        if (!absint($value)) return $value;

        $level =$this->get_field_level($field);
        // $level = wpbdp_getv($field->field_data, 'region_level', null);

        if (is_null($level)) return $value;

        $region = wpbdp_regions_api()->find_by_id($value);

        if (is_null($region) || is_wp_error($region)) return $value;

        return $region->name;
    }

    public function field_plain_value($value, $listing_id, $field) {
        if ( $field->get_association() != 'region' )
            return $value;

        return $this->field_html_value( $value, $listing_id, $field );
    }

    private function _field_options($field, $level, $selected, $parent, $display_context = '') {
        $api = wpbdp_regions_api();

        // get visible regions for this level, filtering by parent selected region, if any
        $results = $api->find_visible_regions_by_level($level, $parent);

        // build options array
        if (!empty($results)) {
            $results = $api->find(array('include' => $results, 'hide_empty' => 0));
            $regions = array(0 => _x('Select a State', 'region-selector', 'wpbdp-regions'));
            $show_counts = wpbdp_get_option( 'regions-show-counts' );

            foreach ($results as $item) {
                // $c = isset($count[$item->term_id]) ? $count[$item->term_id]->count : 0;
                $regions[$item->term_id] = $show_counts ? sprintf("%s (%s)", $item->name, $item->count) : $item->name;
            }
        } else {
            $regions = array(0 => _x('No Regions available', 'region-selector', 'wpbdp-regions'));
        }

        return $regions;
    }

    /* API */

    public function get_fields($sort=false) {
        $fields = get_option('wpbdp-regions-form-fields', array());

        if ($sort === 'asc')
            ksort($fields);
        if ($sort === 'desc')
            krsort($fields);
        return $fields;
    }

    public function update_fields($fields=array()) {
        update_option('wpbdp-regions-form-fields', $fields);
    }

    public function get_field_level($field) {
        foreach ($this->get_fields() as $level => $id) {
            if ($id == $field->get_id())
                return $level;
        }
        return null;
    }

    public function get_field_by_level($level=1) {
        $id = wpbdp_getv($this->get_fields(), $level, null);
        if (!is_null($id))
            return wpbdp()->formfields->get_field($id);
        return null;
    }

    public function get_visible_fields() {
        $regionfields = wpbdp_regions_fields_api();
        $max = wpbdp_regions_api()->get_max_level();

        $fields = array();
        for ($level = 1; $level <= $max; $level++) {
            $field = $regionfields->get_field_by_level($level);

            if (is_null($field)) continue;

            if ( !$field->has_display_flag( 'region-selector' ) )
                continue;

            $fields[] = $field;
        }

        return $fields;
    }

    public function get_min_visible_level() {
        $fields = $this->get_visible_fields();
        if (empty($fields))
            return null;
        return $this->get_field_level($fields[0]);
    }

    public function delete_fields() {
        $fields = wpbdp_get_form_fields('association=region');

        foreach ($fields as &$f) {
            $f->delete();
        }

        delete_option('wpbdp-regions-form-fields');
    }

    public function show_fields() {
        $options = get_option('wpbdp-regions-form-fields-options', array());
        foreach ($options as $id => $display_options) {
            $field = wpbdp_get_form_field($id);

            if (!$field) continue;

            $field->set_display_flags( $display_options );
            $field->save();
        }

        delete_option('wpbdp-regions-form-fields-options');
    }

    public function hide_fields() {
        // if we already have options stored, return to avoid overwriting stored data
        $options = get_option('wpbdp-regions-form-fields-options', array());
        if (!empty($options)) return;

        if ( !function_exists( 'wpbdp' ) )
            return;

        foreach ($this->get_fields() as $level => $id) {
            $field = wpbdp_get_form_field($id);

            if (!$field) continue;

            $options[$id] = $field->get_display_flags();
            $field->remove_display_flag( array( 'excerpt', 'listing', 'search', 'regions-in-form' ) );
            $field->save();
        }

        update_option('wpbdp-regions-form-fields-options', $options);
    }

}
