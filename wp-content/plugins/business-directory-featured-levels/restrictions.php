<?php
/* Restrictions management. */

class WPBDP_FeaturedLevelsModule_Restrictions {

    private $restrictions = null;

	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, '_enqueue_scripts' ) );
        add_action( 'wpbdp_enqueue_scripts', array( $this, '_enqueue_scripts' ) );
        add_action( 'init', array( $this, '_init' ) );
	}

	public function _enqueue_scripts() {
        if ( is_admin() ) {
            wp_enqueue_style ( 'wpbdp-restrictions',
                               plugins_url( '/resources/admin.min.css', __FILE__ )
                             );
            wp_enqueue_script( 'wpbdp-restrictions-js',
                               plugins_url( '/resources/admin.min.js' , __FILE__ ),
                               array('jquery') );
        } else {
            wp_enqueue_style ( 'wpbdp-restrictions-frontend',
                               plugins_url( '/resources/frontend.min.css', __FILE__ )
                             );            
            wp_enqueue_script( 'wpbdp-restrictions-js-frontend',
                                plugins_url( '/resources/frontend.min.js', __FILE__ ),
                                array( 'jquery' ) );
        }
	}

    public function _init() {
        if ( ! defined( 'WPBDP_VERSION' ) || version_compare( WPBDP_VERSION, WPBDP_FeaturedLevelsModule::REQUIRED_BD_VERSION, '<' ) )
            return;

        if ( !get_option( 'wpbdp-restrictions-enabled' ) )
            return;

        add_filter( 'wpbdp_fee_selection_fee_description', array( $this, '_fee_description' ), 10, 2 );
        add_filter( 'wpbdp_listing_submit_fields', array( &$this, '_filter_submit_fields' ), 10, 2 );
        add_filter( 'wpbdp_render_listing_fields', array( $this, '_filter_display_fields' ), 10, 2 );
        add_filter( 'wpbdp_show_google_maps', array( $this, '_filter_google_maps' ), 10, 2 );
        add_filter( 'wpbdp_show_contact_form', array( &$this, '_filter_contact_form' ), 10, 2 );
        add_filter( 'wpbdp_listing_ratings_enabled', array( $this, '_filter_ratings' ), 10, 2 );
        add_filter( 'wpbdp_listing_form_attachments_config', array( $this, '_filter_attachments' ), 10, 2 );
        add_filter( 'wpbdp_form_field_data', array( $this, '_nofollow_on_links' ),  10, 3 );
        
        add_filter( 'wpbdp_render_field_html_attributes', array( $this, '_character_limit_data_attrs' ), 10, 5 );
        add_filter( 'wpbdp_render_field_inner', array( $this, '_character_limit_display' ), 10, 5 );
        add_filter( 'wpbdp_listing_submit_validate_field', array( $this, '_validate_length' ), 10, 5 );
    }

    public function _fee_description( $description, $fee ) {
        if ( !get_option( 'wpbdp-restrictions-enabled' ) )
            return $description;

        // character limit
        $char_limit = $this->get_char_limit( null, array( 'fee' => $fee ) );

        if ( $char_limit == 0 )
            $description .= '✓ ' . __( 'Unlimited content length for your listing.', 'wpbdp-featured-levels' );
        else
            $description .= '• ' . sprintf( __( 'Character limit of %d characters for your listing content.', 'wpbdp-featured-levels' ), $char_limit );

        // other capabilities
        if ( $this->has_capability( 'googlemaps', array( 'fee' => $fee ) ) ) {
            $description .= '<br />';
            $description .= '✓ ' . sprintf( __( 'Google Maps support.', 'wpbdp-featured-levels' ) );
        }

        if ( $this->has_capability( 'ratings', array( 'fee' => $fee ) ) ) {
            $description .= '<br />';
            $description .= '✓ ' . sprintf( __( 'Ratings support.', 'wpbdp-featured-levels' ) );
        }

        if ( $this->has_capability( 'attachments', array( 'fee' => $fee ) ) ) {
            $description .= '<br />';
            $description .= '✓ ' . sprintf( __( 'Ability to attach files to your listing.', 'wpbdp-featured-levels' ) );
        }

        return $description;
    }

    public function _filter_submit_fields( &$fields, &$state ) {
        $base_level = $this->get_listing_level( $state );
        $allowed_fields = $this->get_allowed_fields( array( 'level' => $base_level ) );

        foreach ( $state->categories as $fee_id ) {
            $allowed_fields = array_merge( $allowed_fields, $this->get_allowed_fields( array( 'fee' => $fee_id ) ) );
        }

        $newfields = array();
        foreach ( $fields as &$f ) {
            if ( in_array( $f->get_association(), array( 'title', 'content', 'category' ), true ) || in_array( $f->get_id(), $allowed_fields, true ) )
                $newfields[] = &$f;
        }

        return $newfields;
    }

    public function _filter_display_fields( &$fields, $listing_id ) {
        $allowed_fields = $this->get_allowed_fields( array( 'listing' => $listing_id ) );

        $newfields = array();
        foreach ( $fields as &$field ) {
            if ( in_array( $field->get_association(), array( 'title', 'content', 'category' ), true ) || in_array( $field->get_id(), $allowed_fields, true ) )
                $newfields[] = &$field;
        }

        return $newfields;
    }

    public function _filter_google_maps( $show, $listing_id ) {
        if ( !$show || !$listing_id )
            return $show;

        return $this->has_capability( 'googlemaps', array( 'listing' => $listing_id ) );
    }

    public function _filter_contact_form( $show, $listing_id ) {
        if ( ! $listing_id )
            return $show;

        return $this->has_capability( 'contact-form', array( 'listing' => $listing_id ) );
    }

    public function _filter_ratings( $enabled, $listing_id ) {
        if ( !$enabled || !$listing_id )
            return $enabled;

        return $this->has_capability( 'ratings', array( 'listing' => $listing_id ) );
    }

    public function get_char_limit_from_state( $field, &$state ) {
        $listing_level = $this->get_listing_level( $state );
        $limit = abs( $this->get_char_limit( $field, array( 'level' => $listing_level ) ) );

        foreach ( $state->categories as $fee_id ) {
            $flimit = $this->get_char_limit( $field, array( 'fee' => $fee_id ) );

            if ( $flimit == 0 ) {
                $limit = 0;
                break;
            }

            $limit = max( $limit, $flimit );
        }

        return $limit;
    }

    public function _validate_length( $validates, &$errors, &$field, $value, &$state ) {
        if ( ! in_array( $field->get_association(), array( 'title', 'excerpt', 'content' ), true ) || !$validates )
            return $validates;

        $limit = $this->get_char_limit_from_state( $field, $state );
        $content_length = strlen( str_replace( array( "\r", "\n" ), array( '', '' ), $value ) );

        if ( $limit > 0 && ( $content_length > $limit ) ) {
            $errors[] = sprintf( __( '%s exceeds the character limit of %d characters.', 'wpbdp-featured-levels' ), esc_attr( $field->get_label() ), $limit );
            $validates = false;
        }

        return $validates;
    }

    public function _nofollow_on_links( $value, $key, $field ) {
        if ( $field->get_field_type()->get_id() != 'url' || $key != 'use_nofollow' )
            return $value;

        global $post;
        if ( ! $post || $post->post_type != WPBDP_POST_TYPE )
            return;

        // this setting works backwards since > 1.0.1 (for consistency): true means remove nofollow / false add
        if ( wpbdp_listings_api()->get_sticky_status( $post->ID ) != 'sticky' )
            return true;

        if ( $this->get_option_value( 'nofollow_on_featured', false ) )
            return false;
        else
            return true;

        return $value;
    }

    public function _character_limit_data_attrs( $htmlattrs, &$field, $value, $render_context, &$extra ) {
        if ( $render_context != 'submit' || ! in_array( $field->get_association(), array( 'title', 'excerpt', 'content' ), true ) )
            return $htmlattrs;

        $htmlattrs = is_array( $htmlattrs ) ? $htmlattrs : array();

        if ( $limit = $this->get_char_limit_from_state( $field, $extra ) ) {
            $htmlattrs['data-limit-characters'] = 1;
            $htmlattrs['data-characters-limit'] = $limit;
        }
        
        return $htmlattrs;
    }

    public function _character_limit_display( $field_inner, &$field, $value, $render_context, &$extra ) {
        if ( $render_context != 'submit' || ! in_array( $field->get_association(), array( 'title', 'excerpt', 'content' ), true ) )
            return $field_inner;

        $limit = $this->get_char_limit_from_state( $field, $extra );
        if ( $limit == 0 )
            return $field_inner;

        $field_length = strlen( str_replace( array( "\r", "\n" ), array( '', '' ), $value ) );

        $html  = '';
        $html .= sprintf( '<span class="characters-left-display">' );

        $chars_left_text = __( 'characters left.', 'wpbdp-featured-levels' );
        $html .= sprintf( '<span class="characters-left-placeholder">%d</span> %s', max( 0, $limit - $field_length ), $chars_left_text );

        $html .= '</span><br />';

        $html .= $field_inner;

        return $html;
    }

    public function _dispatch() {
        $action = wpbdp_getv( $_REQUEST, 'action' );
        $_SERVER['REQUEST_URI'] = remove_query_arg( array( 'action' ), $_SERVER['REQUEST_URI'] );

        switch ( $action ) {
            case 'enable_restrictions':
            	if ( isset( $_POST['enable_restrictions'] ) && $_POST['enable_restrictions'] == 1 ) {
            		update_option( 'wpbdp-restrictions-enabled', true );
            	} else {
            		update_option( 'wpbdp-restrictions-enabled', false );
            	}

            	$this->settings_page();
            	break;
            case 'save-group':
                $this->settings_save_group();
                break;
            default:
                $this->settings_page();
                break;
        }
    }

    public function get_restrictions() {
        if ( !is_null($this->restrictions ) )
            return $this->restrictions;

        static $defaults = array(
            'levels' => array(),
            'fees' => array(),
            'options' => array()
        );

        $this->restrictions = get_option( 'wpbdp-restrictions-config', $defaults );
        return $this->restrictions;
    }

    public function save_restrictions( $new, $type='capabilities' ) {
        $old = $this->get_restrictions();

        foreach ( $old as $o_i => &$o ) {
            foreach ( $o as $i => &$v ) {
                if ( isset( $v[ $type ] ) )
                    unset( $v[ $type ] );
            }
        }

        foreach ( $new as $o_i => &$o ) {
            foreach( $o as $i => &$v ) {
                if ( !isset( $old[ $o_i ][ $i ] ) ) {
                    $old[ $o_i ][ $i ]= array();
                }
                
                $old[ $o_i ][ $i ][ $type ] = $v[ $type ];
            }
        }

        if ( $type == 'fields' ) {
            global $wpdb;

            if ( isset( $new['levels'] ) ) {
                foreach ( $new['levels'] as $level => $fields ) {
                    $wpdb->update( "{$wpdb->prefix}wpbdp_x_featured_levels", array( 'form_fields' => serialize( $fields['fields'] ) ), array( 'id' => $level ) );
                }
            }
        }

        $this->restrictions = $old;
        update_option( 'wpbdp-restrictions-config', $this->restrictions );
    }

    public function has_capability( $id, $args ) {
        $args = wp_parse_args(  $args, array( 'listing' => null, 'fee' => null, 'level' => null  ) );

        $listing_id = 0;
        $fee_id = -1;
        $level_id = '';

        if ( !is_null( $args['listing'] ) )
            $listing_id = is_object( $args['listing'] ) ? $args['listing']->ID : intval( $args['listing'] );

        if ( !is_null( $args['fee'] ) )
            $fee_id = is_object( $args['fee'] ) ? $args['fee']->id : intval( $args['fee'] );

        if ( !is_null( $args['level'] ) )
            $level_id = is_object( $args['level'] ) ? $args['level']->id : trim( $args['level'] );

        $restrictions = $this->get_restrictions();
        $capabilities = array();

        if ( $listing_id > 0 ) {
            // check listing for capability
            $listing_fees = wpbdp_listings_api()->get_listing_fees( $listing_id );
            $listing_level = wpbdp_listing_upgrades_api()->get_listing_level( $listing_id )->id;

            if ( $this->has_capability( $id, array( 'level' => $listing_level ) ) )
                return true;

            foreach ( $listing_fees as &$lfee ) {
                if ( $this->has_capability( $id, array( 'fee' => $lfee->fee_id ) ) )
                    return true;
            }

        } elseif ( $fee_id >= 0 ) {
            // check fee for capability
            $capabilities = isset( $restrictions['fees'][ $fee_id ] ) ? wpbdp_getv( $restrictions['fees'][ $fee_id ], 'capabilities', array() ) : array();
        } elseif ( $level_id ) {
            // check level for capability
            $capabilities = isset( $restrictions['levels'][ $level_id ] ) ? wpbdp_getv( $restrictions['levels'][ $level_id ], 'capabilities', array() ) : array();
        }

        return in_array( $id , $capabilities , true );
    }

    public function get_listing_level( &$state ) {
        if ( $state->listing_id > 0 ) {
            $upgrades_api = wpbdp_listing_upgrades_api();
            $level = $upgrades_api->get_listing_level( $state->listing_id );
            return $level->id;
        } else {
            return $state->upgrade_to_sticky ? 'sticky' : 'normal';
        }

        return 'normal';
    }

    public function get_allowed_fields( $args ) {
        $args = wp_parse_args( $args, array( 'listing' => null, 'fee' => null, 'level' => null ) );

        $listing_id = 0;
        $fee_id = -1;
        $level_id = '';

        if ( !is_null( $args['listing'] ) )
            $listing_id = is_object( $args['listing'] ) ? $args['listing']->ID : intval( $args['listing'] );

        if ( !is_null( $args['fee'] ) )
            $fee_id = is_object( $args['fee'] ) ? $args['fee']->id : intval( $args['fee'] );

        if ( !is_null( $args['level'] ) )
            $level_id = is_object( $args['level'] ) ? $args['level']->id : trim( $args['level'] );

        $restrictions = $this->get_restrictions();
        $fields = array();

        if ( $listing_id > 0 ) {
            $listing_fees = wpbdp_listings_api()->get_listing_fees( $listing_id );
            $listing_level = wpbdp_listing_upgrades_api()->get_listing_level( $listing_id )->id;

            $fields = array();
            $fields = array_merge( $fields, $this->get_allowed_fields( array( 'level' => $listing_level ) ) );

            foreach ( $listing_fees as &$lfee ) {
                $fields = array_merge( $fields, $this->get_allowed_fields( array( 'fee' => $lfee->fee_id ) ) );
            }

        } elseif ( $fee_id >= 0 ) {
            $fields = isset( $restrictions['fees'][ $fee_id ] ) ? wpbdp_getv( $restrictions['fees'][ $fee_id ], 'fields', array() ) : array();
        } elseif ( $level_id ) {
            global $wpdb;

            $fields = $wpdb->get_var( $wpdb->prepare( "SELECT form_fields FROM {$wpdb->prefix}wpbdp_x_featured_levels WHERE id = %s", $level_id ) );
            $fields = !$fields ? array() : unserialize( $fields );
        }

        return $fields;
    }

    public function get_char_limit( $field_ = null, $args ) {
        $field_ = is_null( $field_ ) ? wpbdp_get_form_fields( 'association=content&unique=1' ) : $field_;
        $field = is_object( $field_ ) ? $field_ : wpbdp_get_form_field( intval( $field_ ) );

        if ( ! $field )
            return 0;

        $args = wp_parse_args( $args, array( 'listing' => null, 'fee' => null, 'level' => null ) );

        $listing_id = 0;
        $fee_id = -1;
        $level_id = '';

        if ( !is_null( $args['listing'] ) )
            $listing_id = is_object( $args['listing'] ) ? $args['listing']->ID : intval( $args['listing'] );

        if ( !is_null( $args['fee'] ) )
            $fee_id = is_object( $args['fee'] ) ? $args['fee']->id : intval( $args['fee'] );

        if ( !is_null( $args['level'] ) )
            $level_id = is_object( $args['level'] ) ? $args['level']->id : trim( $args['level'] );

        $restrictions = $this->get_restrictions();
        $limit = 0;

        if ( !is_null( $args['listing'] ) && !$listing_id )
            $limit = max( abs( $this->get_char_limit( $field, array( 'fee' => 0 ) ) ), abs( $this->get_char_limit( $field, array( 'level' => 'normal' ) ) ) );

        if ( $listing_id > 0 ) {
            $listing_fees = wpbdp_listings_api()->get_listing_fees( $listing_id );
            $listing_level = wpbdp_listing_upgrades_api()->get_listing_level( $listing_id )->id;

            $llimit = $this->get_char_limit( $field, array( 'level' => $listing_level ) );

            if ( $llimit == 0 )
                return 0;

            $limit = max( $limit, $llimit );

            foreach ( $listing_fees as &$lfee ) {
                $fee = unserialize( $lfee->fee );
                $flimit = $this->get_char_limit( $field, array( 'fee' => $fee['id'] ) );

                if ( $flimit == 0 )
                    return 0;

                $limit = max( $limit, $flimit );
            }

        } elseif ( $fee_id >= 0 ) {
            // For backwards compat.
            if ( 'content' == $field->get_association() && ! isset( $restrictions['fees'][ $fee_id ]['char_limits'] ) )
                $limit = wpbdp_getv( wpbdp_getv( $restrictions['fees'], $fee_id, array() ), 'char_limit', 0 );
            else
                $limit = ( isset( $restrictions['fees'][ $fee_id ] ) && isset( $restrictions['fees'][ $fee_id ]['char_limits'] ) ) ?  wpbdp_getv( $restrictions['fees'][ $fee_id ]['char_limits'], $field->get_id(), 0 ) : 0;
        } elseif ( $level_id ) {
            // For backwards compat.
            if ( 'content' == $field->get_association() && ! isset( $restrictions['levels'][ $level_id ]['char_limits'] ) )
                $limit = wpbdp_getv( wpbdp_getv( $restrictions['levels'], $level_id, array() ), 'char_limit', 0 );
            else
                $limit = ( isset( $restrictions['levels'][ $level_id ] ) && isset( $restrictions['levels'][ $level_id ]['char_limits'] ) ) ? wpbdp_getv( $restrictions['levels'][ $level_id ]['char_limits'], $field->get_id(), 0 ) : 0;
        }

        return max( 0, intval( $limit ) );
    }

    public function get_option_value( $key, $default=null ) {
        $restrictions = $this->get_restrictions();

        $options = wpbdp_getv( $restrictions, 'options', array() );

        if ( array_key_exists( $key, $options ) )
            return $options[ $key ];

        return $default;
    }

    public function set_option_value( $key, $value ) {
        if ( !$key )
            return;

        $restrictions = $this->get_restrictions();

        $options = isset( $restrictions['options'] ) ? $restrictions['options'] : array();
        $options[ $key ] = $value;

        $restrictions['options'] = $options;
        $this->restrictions = $restrictions;

        update_option( 'wpbdp-restrictions-config', $this->restrictions );
    }

    private function settings_page() {
        $current_group = wpbdp_getv( $_GET, 'group', 'premium' );

        $form  = '';

        switch ( $current_group ) {
            case 'premium':
                if ( wpbdp_has_module( 'googlemaps' ) )
                    $form .= $this->settings_form_capability( 'googlemaps', __( 'Google Maps', 'wpbdp-featured-levels' ) );

                if ( wpbdp_has_module( 'ratings' ) )
                    $form .= $this->settings_form_capability( 'ratings', __( 'Ratings', 'wpbdp-featured-levels' ) );

                if ( wpbdp_has_module( 'attachments' ) )
                    $form .= $this->settings_form_capability( 'attachments',
                                                              __( 'File Attachments', 'wpbdp-featured-levels' ),
                                                              array( $this, '_attachments_advanced_controls' ) );

                if ( wpbdp_get_option( 'show-contact-form' ) )
                    $form .= $this->settings_form_capability( 'contact-form',
                                                              __( 'Listing contact form', 'wpbpd-featured-levels' ) );

                break;
            case 'custom-fields':
                $form = $this->settings_form_custom_fields();
                break;
            case 'misc':
                $form = $this->settings_form_misc();
                break;
        }

        // character limits for description fields

        // Display the settings page
        $groups = array(
            (object) array( 'slug' => 'premium',
                   'name' => __( 'Premium Modules', 'wpbdp-featured-levels' ),
                   'enabled' => true
                 ),            
            (object) array( 'slug' => 'custom-fields',
                   'name' => __( 'Field Display Access', 'wpbdp-featured-levels' ),
                   'enabled' => true
                 ),
            (object) array( 'slug' => 'misc',
                   'name' => __( 'Character Count Limits', 'wpbdp-featured-levels' ),
                   'enabled' => true
                 )
        );

        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/restrictions.tpl.php',
                           		array(
                                    'groups' => $groups,
                                    'current_group' => $current_group,
                                    'settings_form' => $form
                                ) );
    }

    private function settings_save_group() {
        $group = wpbdp_getv( $_POST, 'group' );

        switch ( $group ) {
            case 'premium':
                $config = array( 'levels' => array(), 'fees' => array() );

                foreach ( array( 'levels', 'fees' ) as $o ) {
                    if ( !isset( $_POST[ $o ] ) )
                        continue;

                    foreach ( $_POST[ $o ] as $item => $caps ) {
                        foreach ( $caps as $c => $v ) {
                            if ( $v == 1 )
                                $config[ $o ][ $item ]['capabilities'][] = $c;
                        }
                    }
                }

                $this->save_restrictions( $config, 'capabilities' );

                if ( isset( $_POST['settings'] ) && $_POST['settings'] ) {
                    foreach ( $_POST['settings'] as $k => $s ) {
                        $this->set_option_value( $k, $s );
                    }
                }

                return $this->settings_page();

                break;
            case 'custom-fields':
                $config = array( 'levels' => array(), 'fees' => array() );

                foreach ( array( 'levels', 'fees' ) as $o ) {
                    if ( !isset( $_POST[ $o ] ) )
                        continue;

                    foreach ( $_POST[ $o ] as $item => $fields ) {
                        $config[ $o ][ $item ]['fields'] = array_map( 'intval', $fields );
                    }
                }

                $this->save_restrictions( $config, 'fields' );
                return $this->settings_page();

                break;
            case 'misc':
                $config = array( 'levels' => array(), 'fees' => array() );

                foreach ( $_POST['char_limit'] as $field_id => $data ) {
                    foreach ( array( 'levels', 'fees' ) as $o ) {
                        if ( ! isset( $data[ $o ] ) )
                            continue;

                        foreach ( $data[ $o ] as $item => $char_limit ) {
                            if ( ! isset( $config[ $o ][ $item ]['char_limits'] ) )
                                $config[ $o ][ $item ]['char_limits'] = array();

                            $config[ $o ][ $item ]['char_limits'][ $field_id ] = abs( intval( $char_limit ) );
                        }
                    }
                }

                $this->save_restrictions( $config, 'char_limits' );

                if ( isset( $_POST['nofollow_on_featured'] ) && $_POST['nofollow_on_featured'] == 1 ) {
                    $this->set_option_value( 'nofollow_on_featured', true );
                } else {
                    $this->set_option_value( 'nofollow_on_featured', false );
                }

                return $this->settings_page();

                break;
            default:
                break;
        }
    }

    private function settings_form_capability( $id, $name, $advanced_controls_callback=null ) {
        $capability = (object) array(
            'id' => $id,
            'name' => $name
        );

        // featured levels
        $featured_levels = wpbdp_listing_upgrades_api()->get_levels();
        $level_config = array();
        foreach ( $featured_levels as &$level ) {
            $level_config[] = (object) array( 'level' => &$level,
                                     'enabled' => $this->has_capability( $id, array( 'level' => $level->id ) )
                                   );
        }

        // fee plans
        $fee_plans = array_merge( array( wpbdp_fees_api()->get_free_fee() ), wpbdp_fees_api()->get_fees() );
        $fees_config = array();
        foreach ( $fee_plans as &$fee ) {
            $fees_config[] = (object) array( 'fee' => $fee,
                                             'enabled' => $this->has_capability( $id, array( 'fee' => $fee->id ) )
                                  );
        }

        return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/capability.tpl.php',
                                  array(
                                    'capability' => $capability,
                                    'fees_config' => $fees_config,
                                    'level_config' => $level_config,
                                    'advanced_controls' => $advanced_controls_callback
                                  )
                                );
    }

    private function settings_form_custom_fields() {
        // featured levels
        $featured_levels = wpbdp_listing_upgrades_api()->get_levels();
        $level_config = array();
        foreach ( $featured_levels as &$level ) {
            $level_config[] = (object) array( 'level' => &$level,
                                              'fields' => $this->get_allowed_fields( array( 'level' => $level->id ) )
                                            );
        }

        // fee plans
        $fee_plans = array_merge( array( wpbdp_fees_api()->get_free_fee() ), wpbdp_fees_api()->get_fees() );
        $fees_config = array();
        foreach ( $fee_plans as &$fee ) {
            $fees_config[] = (object) array( 'fee' => $fee,
                                             'fields' => $this->get_allowed_fields( array( 'fee' => $fee->id ) )
                                  );
        }

        return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/custom-fields.tpl.php',
                                  array(
                                    'level_config' => $level_config,
                                    'fees_config' => $fees_config
                                  )
                                 );
    }

    private function settings_form_misc() {
        $fields = array();

        foreach ( array( 'title', 'excerpt', 'content' ) as $field_assoc ) {
            $field = wpbdp_get_form_fields( array( 'association' => $field_assoc, 'unique' => 1 ) );

            if ( $field )
                $fields[] = $field;
        }

        $featured_levels = wpbdp_listing_upgrades_api()->get_levels();
        $fee_plans = array_merge( array( wpbdp_fees_api()->get_free_fee() ), wpbdp_fees_api()->get_fees() );

        $char_limits = array();

        foreach( $fields as $field ) {
            $item = new StdClass();
            $item->level_config = array();
            $item->fees_config = array();

            foreach ( $featured_levels as $level ) {
                $item->level_config[] = (object) array( 'level' => $level,
                                                        'char_limit' => $this->get_char_limit( $field, array( 'level' => $level->id ) ) );
            }

            foreach ( $fee_plans as $fee ) {
                $item->fees_config[] = (object) array( 'fee' => $fee,
                                                       'char_limit' => $this->get_char_limit( $field, array( 'fee' => $fee->id ) ) );
            }

            $item->field = $field;

            $char_limits[] = $item;
        }

        return wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/misc.tpl.php',
                                  array( 'char_limits' => $char_limits,
                                         'nofollow_on_featured' => $this->get_option_value( 'nofollow_on_featured', false ) ) );
    }

    /*
     * Attachments.
     */

    public function _filter_attachments( $config, $state ) {
        $newconfig = array_merge( $config, array() );
        $listing_level = $this->get_listing_level( $state );
        $attachments_config = $this->get_option_value( 'attachments' );

        if ( !$attachments_config )
            $attachments_config = array( 'fees' => array(), 'levels' => array() );

        $level_config = isset( $attachments_config['levels'][ $listing_level ] ) ? $attachments_config['levels'][ $listing_level ] : null;
        $newconfig['enabled'] = $this->has_capability( 'attachments', array( 'level' => $listing_level ) );
        $newconfig['limit'] = $level_config && isset( $level_config['restrictions-kind'] ) && $level_config['restrictions-kind'] == 'custom'
                                          ? intval( $level_config['attachments-count'] )
                                          : intval( $config['limit'] );
        $newconfig['filesize'] = $level_config && isset( $level_config['restrictions-kind'] ) && $level_config['restrictions-kind'] == 'custom'
                                 ? intval( $level_config['attachments-maxsize'] ) * 1024
                                 : intval( $config['filesize'] );

        foreach ( $state->categories as $fee_id ) {
            $fee_config = isset( $attachments_config['fees'][ $fee_id ] ) ? $attachments_config['fees'][ $fee_id ] : array();
            $newconfig['enabled'] = $newconfig['enabled'] || $this->has_capability( 'attachments', array( 'fee' => $fee_id ) );

            if ( isset( $fee_config['restrictions-kind'] ) && $fee_config['restrictions-kind'] == 'custom' ) {
                $newconfig['limit'] = max( $newconfig['limit'], intval( $fee_config['attachments-count'] ) );
                $newconfig['filesize'] = max( $newconfig['filesize'], intval( $fee_config['attachments-maxsize'] ) * 1024 );
            }
        }

        return $newconfig;
    }

    public function _attachments_advanced_controls( $kind = 'levels', &$capability, &$l ) {
        $attachments_config = $this->get_option_value( 'attachments' );

        if ( !$attachments_config || !isset( $attachments_config[ $kind ] ) ||
             !isset( $attachments_config[ $kind ][ $kind == 'fees' ? $l->fee->id : $l->level->id ] ) )
            $config = array( 'restrictions-kind' => 'system',
                             'attachments-count' => intval( wpbdp_get_option( 'attachments-count' ) ),
                             'attachments-maxsize' => intval( wpbdp_get_option( 'attachments-maxsize' ) ) );
        else
            $config = $attachments_config[ $kind ][ $kind == 'fees' ? $l->fee->id : $l->level->id ];

        printf( '<label><input type="radio" name="settings[%s][%s][%s][restrictions-kind]" value="system" class="toggle-custom" %s/> %s</label>',
                $capability->id,
                $kind,
                $kind == 'fees' ? $l->fee->id : $l->level->id,
                $config['restrictions-kind'] == 'system' ? 'checked="checked"' : '',
                __( 'Use attachment defaults', 'wpbdp-featured-levels' ) );
        echo ' ';
        printf( '<label><input type="radio" name="settings[%s][%s][%s][restrictions-kind]" value="custom" class="toggle-custom" %s /> %s</label>',
                $capability->id,
                $kind,
                $kind == 'fees' ? $l->fee->id : $l->level->id,
                $config['restrictions-kind'] == 'custom' ? 'checked="checked"' : '',
                __( 'Use custom options', 'wpbdp-featured-levels' ) );
        printf( '<div class="custom-settings" style="%s">',
                $config['restrictions-kind'] == 'custom' ? '' : 'display: none;' );
        printf( '<label>%s: <input type="text" name="settings[%s][%s][%s][attachments-count]" value="%s" size="2" /></label>',
                __( 'Maximum number of attachments per listing', 'wpbdp-featured-levels' ),
                $capability->id,
                $kind,
                $kind == 'fees' ? $l->fee->id : $l->level->id,
                $config['attachments-count'] );
        echo '<br />';
        printf( '<label>%s: <input type="text" name="settings[%s][%s][%s][attachments-maxsize]" value="%s" size="5" /></label>',
                __( 'Maximum attachment size (in KB)', 'wpbdp-featured-levels' ),
                $capability->id,
                $kind,
                $kind == 'fees' ? $l->fee->id : $l->level->id,
                $config['attachments-maxsize'] );
        echo '</div>';
    }

}
