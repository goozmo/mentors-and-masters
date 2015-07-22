<?php
if ( ! class_exists( 'WP_List_Table' ) )
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );


class WPBDP_Listing_Claims_Table extends WP_List_Table {

    function __construct() {
        parent::__construct(array(
            'singular' => __('listing claim', 'wpbdp-claim-listings'),
            'plural' => __('listing claims', 'wpbdp-claim-listings'),
            'ajax' => false
        ));
    }

    function get_views() {
        global $wpdb;

        $views = array();

        foreach ( array( 'all' => _x( 'All', 'admin', 'wpbdp-claim-listings' ),
                         'pending' => _x( 'Pending', 'admin', 'wpbdp-claim-listings' ),
                         'approved' => _x( 'Approved', 'admin', 'wpbdp-claim-listings' ),
                         'completed' => _x( 'Completed', 'admin', 'wpbdp-claim-listings' ),
                         'rejected' => _x( 'Rejected', 'admin', 'wpbdp-claim-listings' ) ) as $status => $label ) {

            if ( 'all' == $status )
                $count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_listing_claims" );
            else
                $count = $wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_listing_claims WHERE status = %s",
                        $status
                ) );

            $views[ $status ] = sprintf( '<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
                                         add_query_arg( 'status', $status ),
                                         $status == wpbdp_getv( $_GET, 'status', 'pending' ) ? 'current' : '',
                                         $label,
                                         number_format_i18n( $count ) );
        }

        return $views;
    }

    function get_bulk_actions() {
        $actions = array();
        $actions['bulk-approve'] = _x( 'Approve claims', 'admin', 'wpbdp-claim-listings' );
        $actions['bulk-reject'] = _x( 'Reject claims', 'admin', 'wpbdp-claim-listings' );
        $actions['bulk-delete'] = _x( 'Delete claims', 'admin', 'wpbdp-claim-listings' );

        return $actions;
    }

    public function prepare_items() {
        global $wpbdp_claim;

        $this->_column_headers = array($this->get_columns(), array(), $this->get_sortable_columns());
        $this->items = $wpbdp_claim->get_claims( wpbdp_getv( $_REQUEST, 'status', 'pending' ),
                                                 isset( $_GET['listing'] ) ? $_GET['listing'] : '' );
    }

    public function get_columns() {
        return array(
            'cb' => '<input type="checkbox" />',
            'listing' => __( 'Listing Title', 'wpbdp-claim-listings' ),
            'details' => __( 'User Details', 'wpbdp-claim-listings' ),
            'created_on' => __( 'Date', 'wpbdp-claim-listings' ),
            'status' => __( 'Status', 'wpbdp-claim-listings' )
        );
    }

    function column_cb( $item ) {
        return '<input type="checkbox" name="claims[]" value="' . $item->id . '" />';
    }

    function column_created_on( $item ) {
        $html  = '';
        $html .= sprintf( '<a href="%s">%s</a>',
                           esc_url( add_query_arg( array( 'action' => 'view', 'id' => $item->id ) ) ),
                           date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ),
                                      strtotime( $item->created_on ) ) );
        return $html;
    }

    function column_listing( $item ) {
        if ( ! get_post_status( $item->listing_id ) )
            return '--';

        $html  = '';
        $html .= sprintf( '<a href="%s">%s</a>',
                          esc_url( add_query_arg( array( 'action' => 'view', 'id' => $item->id ) ) ),
                          get_the_title( $item->listing_id ) );
        $html .= '<br />';

        $actions = array();

        if ( 'pending' == $item->status ) {
            $actions['approve-claim'] = sprintf( '<a href="%s">%s</a>',
                                                 esc_url( add_query_arg( array( 'action' => 'approve-claim',
                                                                                'id' => $item->id ) ) ),
                                                 __( 'Approve', 'wpbdp-claim-listings' ) );

            $actions['delete'] = sprintf( '<a href="%s">%s</a>',
                                          esc_url( add_query_arg( array( 'action' => 'reject-claim',
                                                                         'id' => $item->id ) ) ),
                                          __( 'Reject', 'wpbdp-claim-listings' ) );
        } elseif ( 'rejected' == $item->status || 'completed' == $item->status ) {
            $actions['delete'] = sprintf( '<a href="%s">%s</a>',
                                          esc_url( add_query_arg( array( 'action' => 'delete-claim',
                                                                         'id' => $item->id,
                                                                         '_wpnonce' => wp_create_nonce( 'delete claim ' . $item->id ) ) ) ),
                                          __( 'Delete', 'wpbdp-claim-listings' ) );
        }

        $actions['details'] = sprintf( '<a href="%s">%s</a>',
                                       esc_url( add_query_arg( array( 'action' => 'view', 'id' => $item->id ) ) ),
                                       __( 'View Details', 'wpbdp-claim-listings' ) );
        $actions['show-listing'] = sprintf( '<a href="%s" target="_blank" class="show-listing-link">%s</a>',
                                            get_permalink( $item->listing_id ),
                                            _x( 'Show Listing', 'admin', 'wpbdp-claim-listings' ) );


        $html .= $this->row_actions( $actions );

        return $html;

    }

    function column_details( $item ) {
        $user = get_user_by( 'id', $item->user_id );
        $html  = '';
        $html .= esc_html( $user->display_name );
        $html .= ' (' . esc_attr( $user->user_email ) . ')';
        return $html;
    }

    function column_status( $item ) {
        $html = '<span class="tag ' . ( $item->status ) . '">' . $item->status . '</span>';
        return $html;
    }

}
