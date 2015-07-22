<?php
@error_reporting( E_ALL ^ E_WARNING );
@set_time_limit(0);
@ini_set('memory_limit', '3G');

if ( php_sapi_name() != 'cli' )
	exit;

if ( count( $argv ) < 3 || !file_exists( $argv[2] ) || !is_readable( $argv[2] ) ) {
    echo 'Usage: cli-check.php <strangechars|uncommon> <dbfile>';
    echo PHP_EOL;
    exit;
}

$compressed = substr( $argv[2], 0, -3 ) == '.gz' ? true : false;
$file = $compressed ? gzopen( $argv[2], 'r' ) : fopen( $argv[2], 'r' );

if ( $compressed )
    gzrewind( $file );
else
    rewind( $file );

call_user_func( 'check_' . $argv[1] );

if ( $compressed )
    gzclose( $argv[2] );
else
    fclose( $argv[2] );

function read_line() {
    global $compressed, $file;

    return trim( $compressed ? gzgets( $file ) : fgets( $file ) );
}

function at_eof() {
    global $compressed, $file;
    
    return $compressed ? gzeof( $file ) : feof( $file );
}

function check_strangechars() {
    $chars = array( 'à', 'è', 'ì', 'ò', 'ù', 'á', 'é', 'í', 'ó', 'ú', 'â', 'ê', 'î', 'ô', 'û' );
    $l = 0;
    
    while ( !at_eof() ) {
        ++$l;
        $line = read_line();
        
        if ( $l == 1 )
            continue;
        
        foreach ( $chars as $c ) {
            if ( stripos( $line, $c ) !== false ) {
                echo '[' . $l . '] ' . $line;
                break;
            }
        }
    }
}

function check_uncommon() {
    global $argv;
    $threshold = isset( $argv[3] ) ? intval( $argv[3] ) : 1;
        
    $cities = array();
    $states = array();
    $l = 0;
    
    while ( !at_eof() ) {
        ++$l;
        $line = explode( ':', read_line() );
        
        if ( $l == 1 )
            continue;        
        
        $city = $line[4];
        $state = $line[5];
        
        if ( isset( $cities[ $city ] ) )
            $cities[ $city ]++;
        else
            $cities[ $city ] = 1;
        
        if ( isset( $states[ $state ] ) )
            $states[ $state ]++;
        else
            $states[ $state ] = 1;
    }
    
    echo '=== Cities ===' . PHP_EOL;
    foreach ( $cities as $city => $count ) {
        if ( $count <= $threshold )
            echo $city . ' (' . $count . ')' . PHP_EOL;
    }
    
    echo PHP_EOL . '=== States ===' . PHP_EOL;
    foreach ( $states as $state => $count ) {
        if ( $count <= $threshold )
            echo $state . ' (' . $count . ')' . PHP_EOL;
    }
}