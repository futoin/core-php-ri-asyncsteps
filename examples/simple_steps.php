<?php

require('vendor/autoload.php');

use \FutoIn\RI\AsyncSteps;
use \FutoIn\RI\ScopedSteps;

$root_as = new ScopedSteps();

$root_as->add(
    function( $as ){
        $as->success( "MyValue" );
    }
)->add(
    function( $as, $arg ){
        if ( $arg === 'MyValue' )
        {
            $as->add( function( $as ){
                $as->error( 'MyError', 'Something bad has happened' );
            });
        }

        $as->successStep();
    },
    function( $as, $err )
    {
        if ( $err === 'MyError' )
        {
            $as->success( 'NotSoBad' );
        }
    }
);

$root_as->add(
    function( $as, $arg )
    {
        if ( $arg === 'NotSoBad' )
        {
            echo 'MyError was ignored: ' . $as->state()->error_info . PHP_EOL;
        }
        
        $as->state()->p1arg = 'abc';
        $as->state()->p2arg = 'xyz';
        
        $p = $as->parallel();
        
        $p->add( function( $as ){
            echo 'Parallel Step 1' . PHP_EOL;
            
            $as->add( function( $as ){
                echo 'Parallel Step 1->1' . PHP_EOL;
                $as->p1 = $as->p1arg . '1';
                $as->success();
            } );
        } )
        ->add( function( $as ){
            echo 'Parallel Step 2' . PHP_EOL;
            
            $as->add( function( $as ){
                echo 'Parallel Step 2->1' . PHP_EOL;
                $as->p2 = $as->p2arg . '2';
                $as->success();
            } );
        } );
    }
)->add( function( $as ){
    echo 'Parallel 1 result: ' . $as->state()->p1 . PHP_EOL;
    echo 'Parallel 2 result: ' . $as->p2 . PHP_EOL;
} );

// Note: we use ScopedSteps instead of AsyncSteps in this example
//$root_as->execute();
$root_as->run();
