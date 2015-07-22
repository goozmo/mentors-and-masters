<?php
@error_reporting( E_ALL ^ E_WARNING );
@set_time_limit(0);
@ini_set('memory_limit', '3G');

if ( php_sapi_name() != 'cli' )
	exit;

if ( count( $argv ) < 7 )
    help();
else
    process_command();

echo PHP_EOL;

function help() {
    echo 'Usage:';
    echo PHP_EOL;
    echo "\t";
    echo 'cli.php <action> -i <input file> [-f <format>] -o <output file>';
    echo PHP_EOL;
    echo "\t";   
    echo '* Valid actions: compile, merge';
    echo PHP_EOL;
    echo "\t";    
    echo '* Valid formats: uk us au ca mx (only needed for compile)';
    echo PHP_EOL;    
    echo "\t";
    echo '* Provide the US CSV file from http://www.unitedstateszipcodes.org/zip-code-database/';
    echo PHP_EOL;
    echo "\t";
    echo '* Provide the UK CSV file from http://www.doogal.co.uk/UKPostcodes.php';
    echo PHP_EOL;
    echo "\t";
    echo '* Provide the AU CSV file from http://blog.datalicious.com/free-download-all-australian-postcodes-geocod/';
    echo PHP_EOL;
    echo "\t";
    echo '* Provide the CA CSV file from http://geocoder.ca/?freedata=1';
    echo PHP_EOL;
    echo "\t";
    echo '* Provide the MX CSV file from GeoPostCode Places';    
}

function parse_cmdline() {
    global $argv;
    
    $cmdline = array(
        'action' => '',
        'input' => array(),
        'output' => '',
        'format' => ''
    );
    
    for ( $i = 1; $i < count( $argv ); $i++ ) {
        $a = trim( $argv[ $i ] );
        
        if ( !$a )
            continue;
        
        if ( $a[0] == '-' ) {
            if ( !isset( $a[1] ) )
                continue;
            
            $val = isset( $argv[ $i + 1 ] ) ? trim( $argv[ $i + 1 ] ) : '';            
            
            switch ( $a[1] ) {
                case 'i':
                    if ( $val )
                        $cmdline['input'][] = $val;
                    break;
                case 'o':
                    $cmdline['output'] = $val;
                    break;
                case 'f':
                    $cmdline['format'] = $val;
                    break;
                default:
                    break;
            }
            
            $i += 1;
        } else {
            if ( $cmdline['action'] )
                continue;
            
            $cmdline['action'] = $a;
        }
    }
    
    // TODO: validate command line here
    return $cmdline;
}

function process_command() {
    $cmd = parse_cmdline();
    extract( $cmd );
    
    switch ( $action ) {
        case 'compile':
            $input = $input[0];
            
            if ( !file_exists( $input ) || !is_readable( $input ) )
                printf( 'ERROR: Input file %s does not exist!', $input );
            
            $f = fopen( str_replace( '.gz', '', $output ), 'w' );
            $items = call_user_func_array( 'compile_' . $format, array( $input, &$f ) );
            fclose( $f );

            $header = 'date=' . date( 'Ymd' ) . '&database=' . $format . '&items=' . $items;  
            $lines = file( str_replace( '.gz', '', $output ) );
            array_unshift( $lines, $header );
            file_put_contents( str_replace( '.gz', '', $output ), $lines );
                
            if ( substr( $output, -3 ) == '.gz' ) {
                $gh = gzopen( $output, 'w9' );
                gzwrite( $gh, file_get_contents( str_replace( '.gz', '', $output ) ) );
                gzclose( $gh );
            }

            break;
        case 'merge':
            break;
        
        default:
            break;
    }
}

function _write_line( &$h, $data ) {
    $data = array_map( create_function( '$x', 'return str_replace( ":", "", $x );' ), $data );
    $data = array_map( 'trim', $data );        
    extract( $data );
    
    $zip = strtolower( trim( str_replace( ' ', '', $zip ) ) );
    
    fwrite( $h, "\n" . $country . ':' . $zip . ':' . $lat . ':' . $long . ':' . $city . ':' . $state );
}

/*function compile_us( $input_file, &$out ) {
    $fp = fopen( $input_file, 'r' );
    fgetcsv( $fp, 0, ',' ); // Advance first line.

    $items = 0;
    while ( ( $line = fgetcsv( $fp, 0, ',' ) ) !== FALSE ) {
        _write_line( $out, array( 'zip' => $line[0], 'city' => $line[2], 'state' => $line[5], 'lat' => $line[9], 'long' => $line[10], 'country' => 'us' ) );
        print '+';
        $items++;
    }

    fclose( $fp );
    
    return $items;
}*/

function compile_au( $input_file, &$out ) {
    $fp = fopen( $input_file, 'r' );
    fgetcsv( $fp, 0, ',' ); // Advance first line.
    
    $items = 0;
    while ( ( $line = fgetcsv( $fp, 0, ',' ) ) !== FALSE ) {
        _write_line( $out, array( 'zip' => $line[0], 'city' => $line[8], 'state' => $line[2], 'lat' => $line[10], 'long' => $line[11], 'country' => 'au' ) );
        $items++;
    }
    
    fclose( $fp );
    
    return $items;
}

function compile_uk( $input_file, &$out ) {
    $fp = fopen( $input_file, 'r' );
    fgetcsv( $fp, 0, ',' ); // Advance first line.
    
    $items = 0;
    while ( ( $line = fgetcsv( $fp, 0, ',' ) ) !== FALSE ) {
        _write_line( $out, array( 'zip' => $line[0], 'city' => $line[7], 'state' => $line[6], 'lat' => $line[1], 'long' => $line[2], 'country' => 'uk' ) );
        print '+';
        $items++;
    }
    
    fclose( $fp );
    
    return $items;
}

function compile_de( $input_file, &$out ) {
    // Read cities from 'cities.csv'
    $cities = array();

    $fp = fopen( dirname( $input_file ) . '/' . 'cities.csv', 'r' );
    while ( ( $line = fgetcsv( $fp, 0, ',' ) ) !== false ) {
        $cities[ trim( $line[0] ) ] = trim( $line[1] );
    }
    fclose( $fp );

    // Read coordinates file.
    $fp = fopen( $input_file, 'r' );
    $items = 0;

    while ( ( $line = fgetcsv( $fp, 0, ';' ) ) !== false ) {
        _write_line( $out, array( 'zip' => $line[0],
                                  'city' => isset( $cities[ $line[0] ] ) ? $cities[ $line[0] ] : '',
                                  'state' => '',
                                  'lat' => $line[1],
                                  'long' => $line[2],
                                  'country' => 'de' ) );
        print '+';
        $items++;
    }

    fclose( $fp );
    return $items;
}

function compile_mx( $input_file, &$out ) {
    $fp = fopen( $input_file, 'r' );
    fgetcsv( $fp, 0, ';' );

    $items = 0;
    while ( ( $line = fgetcsv( $fp, 0, ';' ) ) !== false ) {
        if ( $line[0] !== 'mx' && $line[0] !== 'MX' )
            continue;

        _write_line( $out, array( 'zip' => $line[9], 'city' => $line[5], 'state' => $line[4], 'lat' => $line[13], 'long' => $line[14], 'country' => 'mx' ) );

        print '+';
        $items++;
    }

    fclose( $fp );

    return $items;
}

function compile_us( $input_file, &$out ) {
    $fp = fopen( $input_file, 'r' );
    fgetcsv( $fp, 0, ';' );

    $items = 0;
    while ( ( $line = fgetcsv( $fp, 0, ';' ) ) !== false ) {
        if ( $line[0] !== 'us' && $line[0] !== 'US' )
            continue;

        _write_line( $out, array( 'zip' => $line[9], 'city' => $line[5], 'state' => $line[4], 'lat' => $line[13], 'long' => $line[14], 'country' => 'us' ) );

        print '+';
        $items++;
    }

    fclose( $fp );

    return $items;
}

function compile_ca( $input_file, &$out ) {
    $replacements = array(
        'Gros-MéCatina' => 'Gros-Mécatina',
        'Sainte-Agathe-De-LotbinièRe' => 'Sainte-Agathe-de-Lotbinière',
        'Saint-FéLicien' => 'Saint-Félicien',
        'QuéBec' => 'Quebec',
        'RivièRe-Du-Loup' => 'Rivière-du-Loup',
        'Levis' => 'Lévis',
        'LéVis' => 'Lévis',
        'St.-Jean-Chrysostome-De-Lévis' => 'Saint-Jean-Chrysostome',
        'HéBertville' => 'Hébertville',
        'Trois-RivièRes' => 'Trois-Rivières',
        'Trois-RiviÈRes' => 'Trois-Rivières',
        'Trois-RiviÃ¨res' => 'Trois-Rivières',
        'TROIS-RIVIÈRES' => 'Trois-Rivières',
        'MontréAl' => 'Montreal',
        'Saint-JéRôMe' => 'Saint-Jérôme',
        'ChâTeauguay' => 'Châteauguay'
    );

    $fp = fopen( $input_file, 'r' );
    
    $items = 0;
    while ( ( $line = fgetcsv( $fp, 0, ',' ) ) !== FALSE ) {
        _write_line( $out, array( 'zip' => $line[0],
                                  'city' => str_replace( array_keys( $replacements ), $replacements, $line[3] ),
                                  'state' => strtoupper( $line[4] ),
                                  'lat' => $line[1], 'long' => $line[2], 'country' => 'ca' ) );
        print '+';
        $items++;
    }
    
    fclose( $fp );
    
    return $items;
}
