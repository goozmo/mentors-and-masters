<?php
/**
 * Represents a ZIP code database file (plain text / gz-compressed or SQLite).
 * @since 3.3
 */
class ZIPCodeDB_File implements SeekableIterator {

    public static $SUPPORTED_DATABASES = array( 'us', 'uk', 'au', 'ca', 'mx', 'de', 'at', 'be', 'ch', 'li' );


    private $dbformat = 'text';
    private $path = '';
    private $compressed = false;
    private $handle = null;

    /* Header information. */
    private $date;
    private $database = '';
    private $databases;
    private $items;
    
    private $pos = -1;
    private $line = '';
    

    public function __construct( $path ) {
        // Old style database.
        if ( strtolower( basename( $path ) ) == 'zipcodes.db' )
            $this->dbformat = 'sqlite';
        
        $this->path = $path;
        $this->compressed = $this->is_sqlite()  ? false : ( substr( $path, -3 ) == '.gz' ? true : false );
        $this->open();
        $this->read_header();
        $this->advance(); // Position ourselves at the first item.
    }
    
    private function open() {
        if ( $this->is_sqlite() )
            $this->handle = class_exists( 'SQLite3' ) ? new SQLite3( $this->path, SQLITE3_OPEN_READONLY ) : new PDO( 'sqlite:' . $this->path );
        else
            $this->handle = $this->compressed ? gzopen( $this->path, 'r' ) : fopen( $this->path, 'r' );
    }
    
    private function read_header() {
        if ( $this->is_sqlite() ) {
            $q = $this->handle->prepare( 'SELECT * FROM dboptions' );
            
            $info = array();
            if ( class_exists( 'SQLite3' ) ) {
                $rs = $q->execute();
                
                while ( $row = $rs->fetchArray( SQLITE3_ASSOC ) )
                    $info[ $row['key'] ] = $row['value'];
            } else {
                $q->execute();
                
                while ( $row = $q->fetch( PDO::FETCH_OBJ ) )
                    $info[ $row->key ] = $row->value;
            }
            
            if ( count( $info ) > 1 ) {
                $this->database = 'us+uk';
                $this->date = current( $info );
            } else {
                reset( $info );
                list( $this->database, $this->date ) = each( $info );
            }
            
            $this->database = str_replace( '_database', '', $this->database );
            $this->date = date( 'Ymd', $this->date );
            
            if ( class_exists( 'SQLite3' ) ) {
                $this->items = intval( $this->handle->querySingle( 'SELECT COUNT(*) FROM zipcodes' ) );
            } else {
                $q = $this->handle->prepare( 'SELECT COUNT(*) FROM zipcodes' );
                $q->execute();
                
                $this->items = intval( $q->fetchColumn() );
            }
        } else {
            $this->advance();

            parse_str( $this->line, $header );

            $date = isset( $header['date'] ) ? $header['date'] : '';
            $items = isset( $header['items'] ) ? intval( $header['items'] ) : 0;
            $databases = isset( $header['database'] ) ? $header['database'] : array();

            if ( ! is_array( $databases ) )
                $databases = array( $databases );

            if ( ! $date || $items <= 0 || ! $databases )
                throw new Exception( 'Invalid header information.' );

            foreach ( $databases as $d ) {
                if ( ! in_array( $d, self::$SUPPORTED_DATABASES, true ) )
                    throw new Exception( 'Unsupported database in header: ' . $d );
            }

            $this->date = $date;
            $this->items = $items;
            $this->databases = $databases;
        }
    }
    
    public function get_filepath() {
        return $this->path;
    }
    
    public function get_date() {
        return $this->date;
    }
    
    public function get_databases() {
        return $this->databases;
    }

    public function get_database_name() {
        return strtoupper( implode( ' ', $this->databases ) );
    }

    public function get_no_items() {
        return $this->items;
    }

    public function get_items( $start = 0, $end_ = -1 ) {
        $end = $end_ < 0 ? $this->items - 1 : min( $end_, $this->items - 1 );
        return new ZIPCodeDB_IntervalItemIterator( $this, $start, $end );
    }
    
    private function advance() {
        $this->line = trim( $this->compressed ? gzgets( $this->handle ) : fgets( $this->handle ) );
        $this->pos++;
    }
    
    /* -- Iterable interface. -- */
    
    public function valid() {
        return $this->pos > 0 && $this->pos <= $this->items;
    }

    public function key() {
        return $this->pos - 1;
    }
    
    public function current() {
        return explode( ':', $this->line );
    }
    
    public function next() {
        $this->advance();        
    }
        
    public function rewind() {
        rewind( $this->handle );
        $this->pos = -1;
        $this->advance(); // Skip header.
        $this->advance(); // Position ourselves at first item.
    }
    
    public function seek( $pos ) {
        $pos = intval( $pos );
        
        if ( $pos < 0 )
            return $this->seek( $this->items + $pos );
        
        if ( $pos > 0 && ( $pos >= $this->items ) )
            throw new OutOfBoundsException( "Invalid seek position ({$pos})." );

        $this->rewind();
        
        if ( $pos == 0 )
            return;
        
        for ( $i = 0; $i < $pos; $i++ )
            $this->advance();
    }

    

    // public function get_items( $start_ = 0, $end_ = -1 ) {
    //     $items = array();
    // 
    //     if ( $this->is_sqlite() ) {
    //         $start = $start_;
    //         $batchsize = $end_ - $start + 1;
    //         
    //         $rs = $this->handle->query( "SELECT * FROM zipcodes ORDER BY id LIMIT {$start},{$batchsize}" );
    //         
    //         if ( class_exists( 'SQLite3' ) ) {
    //             while ( $row = $rs->fetchArray( SQLITE3_ASSOC ) ) {
    //                 $this->line = sprintf( '%s:%s:%s:%s:%s:%s', $row['country'], $row['zipcode'], $row['latitude'], $row['longitude'], $row['city'], $row['state'] );
    //                 $items[] = $this->line;
    //             }
    //         } else {
    //             while ( $row = $rs->fetch( PDO::FETCH_OBJ ) ) {
    //                 $this->line = sprintf( '%s:%s:%s:%s:%s:%s', $row->country, $row->zipcode, $row->latitude, $row->longitude, $row->city, $row->state );
    //                 $items[] = $this->line;
    //             }
    //         }
    //     } else {
    //         $start = $start_;
    //         $end = $end_ < 0 ? $this->items - 1 : min( $end_, $this->items - 1 );
    //         //         
    //         // for ( $i = 0; $i <= $end; $i++ ) {
    //         //     $this->advance();
    //         // 
    //         //     if ( $i < $start )
    //         //         continue;
    //         // 
    //         //     $items[] = explode( ':', $this->line );
    //         // }
    //         $file = new SplFileObject( $this->path );
    //         $file->setFlags( SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE );
    //         $file->seek( $start );
    //         
    //         $batchsize = $end - $start + 1;
    //         
    //         for ( $i = 0; $i < $batchsize; $i++ ) {
    //             if ( $file->eof() )
    //                 break;
    // 
    //             $file->next();
    //             $this->pos++;             
    //             $this->line = $file->current();
    // 
    //             $linedata = explode( ':', $this->line );
    //             $items[] = $linedata;
    //         }
    //         
    //     }
    // 
    //     return $items;
    // }
    
    public function close() {
        if ( $this->is_sqlite() ) {
            if ( class_exists( 'SQLite3' ) ) {
                $this->handle->close();
            } else {
                $this->handle = null;
            }
        } else {
            if ( $this->compressed ) {
                gzclose( $this->handle );
            } else {
                fclose( $this->handle );
            }
        }
    }
    
    public function is_sqlite() {
        return $this->dbformat == 'sqlite' ? true : false;
    }

}

/**
 * Allows iteration over part of the items in a ZIPCodeDB.
 * @since 3.3
 */
class ZIPCodeDB_IntervalItemIterator implements Iterator {

    private $zipdb;
    private $start;
    private $end;
    
    public function __construct( &$zipdb, $start, $end) {
        $this->zipdb = $zipdb;
        $this->start = intval( $start );
        $this->end = intval( $end );
        $this->rewind();
    }
    
    public function rewind() {
        $this->zipdb->seek( $this->start );
        // $this->zipdb->current();
    }
    
    public function key() {
        return $this->zipdb->key();
    } 
    
    public function current() {
        return $this->zipdb->current();
    }

    public function next() {
        $this->zipdb->next();
    }
    
    public function valid() {
        return $this->zipdb->key() <= $this->end && $this->zipdb->valid();
    }
     
}
