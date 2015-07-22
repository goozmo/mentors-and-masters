<?php
require_once( plugin_dir_path( __FILE__ ) . 'zipcodelib/dbfile.php' );


class WPBDP_ZipCodeSearchModule_Admin {
    
    private $module = null;
    
    public function __construct( &$module ) {
        $this->module = $module;
       
        add_action( 'admin_notices', array( &$this,'admin_notices' ) );        
        add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( &$this, 'enqueue_scripts' ) );        
        add_action( 'wp_ajax_wpbdp-zipcodesearch-import', array( &$this, 'import_ajax' ) );
        add_action( 'wp_ajax_wpbdp-zipcodesearch-rebuildcache', array( &$this, 'ajax_rebuild_cache' ) );
    }
    
    public function admin_menu( $menu_id ) {
        add_submenu_page( null,
                          __( 'Import ZIP code Database', 'wpbdp-zipcodesearch' ),
                          __( 'Import ZIP code Database', 'wpbdp-zipcodesearch' ),
                          'administrator',
                          'wpbdp-zipcodesearch-importdb',
                          array( &$this, 'import_page' ) );
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style( 'wpbdp-zipcodesearch-admin-css', plugins_url( '/resources/admin.min.css', __FILE__ ) );
        wp_enqueue_script( 'wpbdp-zipcodesearch-admin-js', plugins_url( '/resources/admin.min.js', __FILE__ ) );
        
        wp_localize_script( 'wpbdp-zipcodesearch-admin-js', 'wpbdpL10n', array(
            'start_import' => _x( 'Start Import', 'import', 'wpbdp-zipcodesearch' ),
            'pause_import' => _x( 'Pause Import', 'import', 'wpbdp-zipcodesearch' ),
            'resume_import' => _x( 'Resume Import', 'import', 'wpbdp-zipcodesearch' )
        ) );
    }
    
    public function admin_notices() {
        if ( ! current_user_can( 'administrator' ) )
            return;

        if ( isset( $_GET['page'] ) && $_GET['page'] == 'wpbdp-zipcodesearch-importdb'  )
            return;
        
        if ( !$this->module->check_db() ) {
            echo '<div class="error"><p>';
            if ( WPBDP_ZIPDBImport::get_current_import() )
                echo str_replace( '<a>',
                                  '<a href="' . admin_url( 'admin.php?page=wpbdp-zipcodesearch-importdb' ) . '">',
                                  __( 'Business Directory has detected an unfinished ZIP code database import. Please go to <a>Import ZIP code database</a> and either resume or cancel the import. If you are aware the import is in progress you can ignore this message.', 'wpbdp-zipcodesearch' ) );
            else
                echo sprintf( __( 'Business Directory - ZIP Code Search Module is active, but no valid ZIP code database available. You must first download and configure a ZIP code database for your region. Please <a href="%s">import a ZIP code database file</a>.', 'wpbdp-zipcodesearch' ),
                              admin_url('admin.php?page=wpbdp-zipcodesearch-importdb') );
            echo '</p></div>';
            return;
        }
        
        if ( !$this->module->is_cache_valid() ) {
            echo '<div class="error"><p>';
            echo str_replace( '<a>', '<a href="' . admin_url( 'admin.php?page=wpbdp_admin_settings&groupid=zipsearch' ) . '">',
                              __( 'Settings for Business Directory - ZIP Code Search Module have been recently changed and a cache rebuild is needed. Go to <a>ZIP Search settings</a> and click "Rebuild Cache". Not doing this results in slow searches.', 'wpbdp-zipcodesearch' ) );
            echo '</p></div>';
        }
    }
    
    /*
     * Settings.
     */
    public function manage_databases() {
        if ( isset( $_GET['deletedb'] ) ) {
            $this->module->delete_database( $_GET['deletedb'] );
        }
        
        $databases = $this->module->get_installed_databases();

        if ( !$databases ) {
            echo __( 'No valid ZIP code database found.', 'wpbdp-zipcodesearch' );
            echo '<br />';
            echo sprintf( __( 'Go to <a href="%s">Import ZIP code database</a> and follow installation instructions.', 'wpbdp-zipcodesearch' ),
                          admin_url( 'admin.php?page=wpbdp-zipcodesearch-importdb' ) );
            return;
        }

        foreach ( $this->module->get_supported_databases() as $dbid => $dbdata ) {
            echo '<div class="dbline">';
            printf( '<input type="checkbox" disabled="disabled" %s />', array_key_exists( $dbid, $databases ) ? 'checked="checked"' : '' );

            echo ' ' . $dbdata[0] . ' ';

            if ( isset( $databases[ $dbid ] ) ) {
                printf( '(ver %s)', $databases[ $dbid ] );
                printf( ' <a href="%s" class="delete-db">%s</a>',
                        add_query_arg( 'deletedb', $dbid ),
                        __( 'Delete database', 'wpbdp-zipcodesearch' ) );

                if (version_compare( $databases[ $dbid ], $dbdata[1], '<' ) ) {
                    printf( '<a href="%s" class="update-available" target="_blank">%s</a>',
                            'http://businessdirectoryplugin.com/zip-databases/',
                            __( 'Update available!', 'wpbdp-zipcodesearch' ) );
                }
            }
            echo '</div>';
        }

        echo '<br />';
        $msg = __( 'To install additional databases go to <a>Import ZIP code database</a>.', 'wpbdp-zipcodesearch' );
        $msg = str_replace( '<a>', '<a href="' . admin_url( 'admin.php?page=wpbdp-zipcodesearch-importdb' ) . '">', $msg );
        
        echo $msg;
    }
    
    public function cache_status() {
        echo '<div class="zipcodesearch-cache">';
                    
        if ( $this->module->is_cache_valid() ) {
            echo '<span class="status ok">' . __( 'OK', 'wpbdp-zipcodesearch' ) . '</span>';
        } else {
            echo '<span class="status notok"><span class="msg">' . __( 'Invalid cache. Please rebuild.', 'wpbdp-zipcodesearch' ) . '</span><span class="progress"></span></span>' . '<br />';
            printf( '<a href="%s" class="button rebuild-cache">%s</a>',
                    add_query_arg( 'action', 'wpbdp-zipcodesearch-rebuildcache', admin_url( 'admin-ajax.php' ) ),
                    __( 'Rebuild cache', 'wpbdp-zipcodesearch' ) );
        }
        
        echo '</div>';
    }
    
    public function ajax_rebuild_cache() {
        global $wpdb;
        
        $field_id = intval( wpbdp_get_option( 'zipcode-field', 0 ) );
        
        $response = array();
        $response['done'] = false;
        $response['statusText'] = '';
        
        // SELECT pm.post_id, pm.meta_value, zl.listing_id, zl.zip FROM wp_postmeta pm LEFT JOIN wp_wpbdp_zipcodes_listings zl ON zl.listing_id = pm.post_id INNER JOIN wp_posts p ON p.ID = pm.post_id WHERE pm.meta_key = '_wpbdp[fields][10]' AND zl.listing_id IS NULL AND p.post_status = 'publish' AND p.post_type = 'wpbdp_listing'
        // $pending = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT pm.post_id FROM {$wpdb->postmeta} pm LEFT JOIN {$wpdb->prefix}wpbdp_zipcodes_listings zl ON zl.listing_id = pm.post_id INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_status = %s AND p.post_type = %s AND zl.listing_id IS NULL ORDER BY pm.post_id ASC LIMIT 20", '_wpbdp[fields][' . $field_id . ']', 'publish', WPBDP_POST_TYPE ) );
        $pending = $wpdb->get_col( $wpdb->prepare( "SELECT p.ID from {$wpdb->posts} p WHERE p.post_type = %s AND p.ID NOT IN (SELECT zc.listing_id FROM {$wpdb->prefix}wpbdp_zipcodes_listings zc) LIMIT 50", WPBDP_POST_TYPE ) );
        
        if ( $pending ) {
            foreach ( $pending as $post_id )
                $this->module->cache_listing_zipcode( $post_id );
        } else {
            $response['done'] = true;
        }

        $remaining = max( 0, intval( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s", WPBDP_POST_TYPE ) ) ) - intval( $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}wpbdp_zipcodes_listings" ) ) );                
        $response['statusText'] = sprintf( _x( 'Please wait. Rebuilding cache... %d listings remaining.', 'cache', 'wpbdp-zipcodesearch' ), $remaining );
        
        echo json_encode( $response );
        
        die();
    }
    
    /*
     * DB Import process.
     */

    public function import_page() {
        $import = WPBDP_ZIPDBImport::get_current_import();
        
        if ( $import && isset( $_GET['cancel_import'] ) && $_GET['cancel_import'] == 1 ) {
            $import->cancel();
            $import = null;
            wpbdp_admin_message( _x( 'The import was canceled.', 'import', 'wpbdp-zipcodesearch' ) );
        }
        
        if ( isset( $_GET['nomigrate'] ) && $_GET['nomigrate'] == 1 )
            update_option( 'wpbdp-zipcodesearch-db-nomigrate', 1 );        
        
        $nomigrate = get_option( 'wpbdp-zipcodesearch-db-nomigrate', 0 );
        $old_style_db = plugin_dir_path( __FILE__ ) . 'db' . DIRECTORY_SEPARATOR . 'zipcodes.db';
        $upgrade_possible = ( !$nomigrate && !$import && file_exists( $old_style_db ) && is_readable( $old_style_db )  ) ? true : false;     
        
        if ( $upgrade_possible && isset( $_GET['migrate'] ) && $_GET['migrate'] == 1 ) {
            $import = WPBDP_ZIPDBImport::create( $old_style_db );
        }

        if ( !$import && isset( $_FILES['dbfile'] ) ) {
            $upload = wpbdp_media_upload( $_FILES['dbfile'], false, false, array(), $upload_error );
            
            if ( !$upload ) {
                wpbdp()->admin->messages[] = sprintf( _x( 'Could not upload database file: %s.', 'import', 'wpbdp-zipcodesearch' ), $upload_error );
            } else {
                $import = WPBDP_ZIPDBImport::create( $upload['file'] );
                wpbdp_admin_message( _x( 'Database file uploaded. Please proceed with the import.', 'import', 'wpbdp-zipcodesearch' ) );
            }
        } elseif ( !$import && isset( $_POST['uploaded_dbfile']  ) ) {
            if ( file_exists( $_POST['uploaded_dbfile'] ) && is_readable( $_POST['uploaded_dbfile'] ) ) {
                $import = WPBDP_ZIPDBImport::create( $_POST['uploaded_dbfile'] );
                // $import = WPBDP_ZIPDBImport::create( str_replace( '.gz', '', $_POST['uploaded_dbfile'] ) );                
                wpbdp_admin_message( sprintf( _x( 'Using database file "%s". Please proceed with the import.', 'import', 'wpbdp-zipcodesearch' ),
                                                      basename( $import->get_filepath() ) ) );
            }
        }
        
        // Check "db/" directory for FTP/manually uploaded databases.
        $dbfiles = $this->find_uploaded_databases();
        
        $_SERVER['REQUEST_URI'] = remove_query_arg( array( 'cancel_import', 'migrate', 'nomigrate' ), $_SERVER['REQUEST_URI'] );
        
        echo wpbdp_render_page( plugin_dir_path( __FILE__ ) . 'templates/admin-import.tpl.php',
                                array( 'import' => $import,
                                       'upgrade_possible' => $upgrade_possible,
                                       'dbfiles' => $dbfiles ) );
    }
    
    private function find_uploaded_databases() {
        $dbfiles = array();
        
        $files = wpbdp_scandir( plugin_dir_path( __FILE__ ) . 'db' . DIRECTORY_SEPARATOR );
        foreach ( $files as &$filename ) {
            // if ( strtolower( substr( $filename, -3 ) ) == '.gz' ) {
                try {
                    $dbfile = new ZIPCodeDB_File( plugin_dir_path( __FILE__ ) . 'db' . DIRECTORY_SEPARATOR . $filename  );
                    
                    $dbnames = array();
                    foreach ( $dbfile->get_databases() as $d ) {
                        $dbnames[] = $this->module->get_db_name( $d );
                    }

                    $dbfiles[] = array( 'filepath' => $dbfile->get_filepath(),
                                        'database' => implode( ', ', $dbnames ),
                                        'date' => $dbfile->get_date() );
                    $dbfile->close();
                } catch (Exception $e) {}
            // }
        }

        return $dbfiles;
    }
    
    public function import_ajax() {
        $response = array();
        $response['progress'] = 0;
        $response['finished'] = false;
        $response['statusText'] = '';
        $response['error'] = '';        
        
        if ( $import = WPBDP_ZIPDBImport::get_current_import() ) {
            $import->make_progress();
            $response['progress'] = $import->get_progress( '%' );
            $response['finished'] = $import->get_progress( 'r' ) == 0 ? true : false;
            $response['processed'] = number_format( $import->get_progress( 'n' ) );
            $response['statusText'] = sprintf( _x( 'Importing database... %d items remaining.', 'import', 'wpbdp-zipcodesearch' ), $import->get_progress( 'r' ) );
            $import->cleanup();
        } else {
            $response['error'] = __( 'There is no import in progress.', 'wpbdp-zipcodesearch' );
        }
        
        echo json_encode( $response );
        die();
    }
    
}


/**
 * Represents a ZIP code database import in progress.
 * @since 3.3
 */
class WPBDP_ZIPDBImport {
    
    const BATCH_SIZE = 5000;
    const INSERT_BATCH = 50;

    /* Database info. */
    private $filepath = '';
    private $file = null;
    
    /* Progress. */
    private $processed = 0;
    private $started = null;
    private $updated = null;

    public static function &get_current_import() {
        $data = get_option( 'wpbdp-zipcodesearch-db-import', null );
        
        try {
            if ( $data && is_array( $data ) ) {
                $import = new self( $data['filepath'] );
                return $import;
            }
        } catch ( Exception $e ) {
            wpbdp_admin_message( _x( 'A previous database import was corrupted. All import information was deleted.', 'import', 'wpbdp-zipcodesearch' ), 'error' );
            delete_option( 'wpbdp-zipcodesearch-db-import' );
            $import = null;
        }

        return $import;
    }
    
    public static function &create( $dbfile ) {
        if ( self::get_current_import() ) {
            $import = null;
            return $import;
        }
        
        $import = new self( $dbfile );
        return $import;
    }
    
    private function __construct( $filepath ) {
        $this->filepath = $filepath;
        $this->file = new ZIPCodeDB_File( $filepath );
        
        if ( $data = get_option( 'wpbdp-zipcodesearch-db-import', null ) ) {
            if ( $data['filepath'] != $this->filepath )
                throw new Exception( 'Can not import two different DB files at the same time.' );
            
            $this->started = $data['started'];
            $this->updated = $data['updated'];
            $this->processed = $data['processed'];
        } else {
            $this->started = time();
            $this->updated = time();
            $this->processed = 0;
        }
        
        $this->persist();
    }
    
    private function persist() {
        update_option( 'wpbdp-zipcodesearch-db-import', array(
            'filepath' => $this->filepath,
            'started' => $this->started,
            'updated' => $this->updated,
            'processed' => intval( $this->processed )
        ) );        
    }
    
    public function get_databases() {
        return $this->file->get_databases();
    }

    public function get_database_name() {
        return $this->file->get_database_name();
    }
    
    public function get_database_date() {
        return $this->file->get_date();
    }
    
    public function get_filepath() {
        return $this->filepath;
    }
    
    public function get_imported() {
        return $this->processed;
    }
    
    public function get_total_items() {
        return $this->file->get_no_items();
    }
    
    public function cleanup() {
        $this->file->close();
        
        // Remove original file when done.
        if ( $this->get_progress( 'r' ) == 0 )
            @unlink(  $this->filepath );
    }
    
    public function cancel() {
        // Cancels an import.
        $module = WPBDP_ZIPCodeSearchModule::instance();

        foreach ( $this->file->get_databases() as $d ) {
            $module->delete_database( $d );
        }

        delete_option( 'wpbdp-zipcodesearch-db-import' );
        
        $this->cleanup();
        
        if ( !$this->file->is_sqlite() )
            @unlink(  $this->filepath );
    }
    
    public function get_progress( $format = '%' ) {
        $processed = $this->get_imported();
        $items = $this->get_total_items();        
        
        switch ( $format ) {
            case '%': // As a percentage.
                return round( 100 * $this->get_progress( 'f' ) );
                break;
            case 'f': // As a fraction.
                return round( $processed / $items, 3 );
                break;
            case 'n': // As # of items imported.
                return $processed;
                break;
            case 'r': // As # of items remaining.
                return max( 0, $items - $processed );
                break;
        }
        
        return 0;
    }
    
    public function make_progress() {
        global $wpdb;
        
        if ( $this->processed == 0 ) {
            $module = WPBDP_ZIPCodeSearchModule::instance();

            foreach ( $this->file->get_databases() as $d ) {
                $module->delete_database( $d );
            }
        }

        $sql_items = '';
        foreach ( $this->file->get_items( $this->processed, $this->processed + self::BATCH_SIZE - 1 ) as $k => $item ) {
            $sql_items .= $wpdb->prepare( '(%s, %s, %s, %s, %s, %s)', $item[0], $item[1], $item[2], $item[3], $item[4], $item[5] ) . ',';
            $this->processed++;
            
            if ( ( ($k + 1) % self::INSERT_BATCH == 0 ) || ( $k == $this->file->get_no_items() - 1 ) ) {                
                $sql_items = rtrim( $sql_items, ',' );
                $sql = "INSERT INTO {$wpdb->prefix}wpbdp_zipcodes(country, zip, latitude, longitude, city, state) VALUES {$sql_items};";
                $wpdb->query( $sql );
                $sql_items = $sql = '';
            }

        }
        
        $this->updated = time();
        $this->persist();
        
        if ( $this->get_progress( 'r' ) == 0 ) {
            // Add database to list.
            $databases = get_option( 'wpbdp-zipcodesearch-db-list', array() );

            foreach ( $this->get_databases() as $d )
                $databases[ $d ] = $this->get_database_date();
            update_option( 'wpbdp-zipcodesearch-db-list', $databases );
            
            // Remove original file.
            @unlink(  $this->filepath );
            update_option( 'wpbdp-zipcodesearch-db-nomigrate', 1 );
            
            // Invalidate cache items with NULL zip since this database might bring the required information.
            $module = WPBDP_ZIPCodeSearchModule::instance();            
            $module->delete_listing_cache( 'NULL' );
            
            // Delete import info.
            delete_option( 'wpbdp-zipcodesearch-db-import' );
        }
    }

}
