<?php

require('vendor/autoload.php');

use \FutoIn\RI\AsyncSteps;
use \FutoIn\RI\ScopedSteps;
use \FutoIn\RI\AsyncTool;


function dummy_service_read( $success, $error ){
    // We expect it calles success when data is available
    // and error, if error occurs
    // Returns some request handle
    return null;
}

function dummy_service_cancel( $reqhandle ){
    // We assume it cancels previously scheduled reqhandle
}

$root_as = new ScopedSteps();

$root_as->add( function( $as ){
    AsyncTool::callLater( function() use ( $as ) {
        $as->success( 'async success()' );
    } );
    
    $as->setTimeout( 10 ); // ms
} )->add(
    function( $as, $arg ){
        echo $arg . PHP_EOL;
        
        $reqhandle = dummy_service_read(
            function( $data ) use ( $as ) {
                $as->success( $data );
            },
            function( $err ) use ( $as ) {
                if ( $err !== 'SomeSpecificCancelCode' )
                {
                    $as->error( $err );
                }
            }
        );
        
        $as->setCancel(function($as) use ( $reqhandle ) {
            dummy_service_cancel( $reqhandle );
        });
        
        // OPTIONAL. Set timeout of 1s
        $as->setTimeout( 1000 );
    },
    function( $as, $err )
    {
        echo $err . ": " . $as->error_info . PHP_EOL;
    }
);

// Note: we use ScopedSteps instead of AsyncSteps in this example
//$root_as->execute();
$root_as->run();

