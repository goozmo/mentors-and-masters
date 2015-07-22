<?php
/**
 * @since 3.6
 */
class WPBDP_Region_Search_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct( false,
                             _x( 'Business Directory - Region Search Widget', 'widgets', 'wpbdp-regions' ),
                             array( 'description' => _x( 'Allows visitors to perform region searches on your site.', 'widgets', 'wpbdp-regions' ) ) );
    }

    public function form( $instance ) {
        $regions_fields_api = wpbdp_regions_fields_api();

        printf( '<p><label for="%s">%s</label> <input class="widefat" id="%s" name="%s" type="text" value="%s" /></p>',
                $this->get_field_id( 'title' ),
                _x( 'Title:', 'widgets', 'wpbdp-regions' ),
                $this->get_field_id( 'title' ),
                $this->get_field_name( 'title' ),
                esc_attr( isset( $instance['title'] ) ? $instance['title'] : _x( 'Region Search', 'widgets', 'wpbdp-regions' ) ) );

        $current_fields = isset( $instance['fields'] ) ? $instance['fields'] : array();
        echo '<p><label>' . _x( 'Fields:', 'widgets', 'wpbdp-regions' ) . '</label><br />';
        foreach ( $regions_fields_api->get_fields() as $field_id ) {
            $field = wpbdp_get_form_field( $field_id );

            if ( ! $field )
                continue;

            printf( '<input type="checkbox" name="%s" id="%s" value="%d" %s /> <label for="%s">%s</label><br />',
                    $this->get_field_name( 'fields' ) . '[]',
                    $this->get_field_id( 'fields-' . $field_id ),
                    $field_id,
                    in_array( $field_id, $current_fields ) ? 'checked="checked"' : '',
                    $this->get_field_id( 'fields-' . $field_id ),
                    esc_html( $field->get_label() ) );
        }
        echo '</p>';

        printf( '<p><label for="%s">%s</label> <input class="widefat" id="%s" name="%s" type="text" value="%s" /></p>',
                $this->get_field_id( 'listings_limit' ),
                _x( 'Matching listings to return:', 'widgets', 'wpbdp-regions' ),
                $this->get_field_id( 'listings_limit' ),
                $this->get_field_name( 'listings_limit' ),
                isset( $instance['listings_limit'] ) ? $instance['listings_limit'] : 10 );

        printf( '<p><label for="%s">%s</label> <input clas="widefat" id="%s" name="%s" type="text" value="%s" /></p>',
                $this->get_field_id( 'submit_text' ),
                _x( 'Submit button text:', 'widgets', 'wpbdp-regions' ),
                $this->get_field_id( 'submit_text' ),
                $this->get_field_name( 'submit_text' ),
                esc_attr( isset( $instance['submit_text'] ) ? $instance['submit_text'] : _x( 'Search', 'widgets', 'wpbdp-regions' ) ) );
    }

    public function update( $new, $old ) {
        $instance = array();
        $instance['title'] = ! empty( $new['title'] ) ? strip_tags( $new['title'] ) : '';
        $instance['fields'] = is_array( $new['fields'] ) ? array_map( 'intval', $new['fields'] ) : array();
        $instance['listings_limit'] = ! empty( $new['listings_limit'] ) ? intval( $new['listings_limit'] ) : 0;
        $instance['submit_text'] = ! empty( $new['submit_text'] ) ? $new['submit_text'] : _x( 'Search', 'widgets', 'wpbdp-regions' );

        return $instance;
    }


    public function widget( $args, $instance ) {
        echo $args['before_widget'];

        if ( ! empty( $instance['title'] ) ) {
            echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ). $args['after_title'];
        }

        $regions_fields_api = wpbdp_regions_fields_api();

        $valid_fields = $regions_fields_api->get_fields();
        $fields = isset( $instance['fields'] ) ? $instance['fields'] : array();

        printf( '<form action="%s" method="post" class="wpbdp-regions-search-widget">', add_query_arg( array( 'bd-module' => 'regions', 'bd-action' => 'widget-search' ), wpbdp_get_page_link( 'main' ) ) );
        printf( '<input type="hidden" name="numberposts" value="%d">', $instance['listings_limit'] );

        foreach ( $fields as $field_id ) {
            if ( ! in_array( $field_id, $valid_fields ) )
                continue;

            $field = wpbdp_get_form_field( $field_id );

            if ( ! $field )
                continue;

            echo $field->render( null, 'widget' );
        }

        echo '<p>';
        printf( '<input type="submit" value="%s" class="button submit" />', $instance['submit_text'] );
        echo '</p>';

        echo '</form>';

    }

}
