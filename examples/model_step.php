<?php

require('vendor/autoload.php');

use \FutoIn\RI\AsyncSteps;
use \FutoIn\RI\AsyncToolTest;

// Note, we have no default event engine in PHP
AsyncToolTest::init();

$model_as = new AsyncSteps();
$model_as->state()->variable = 'Vanilla';

$model_as->add( function($as){
    echo '-----' . PHP_EOL;
    echo 'Hi! I am from model_as' . PHP_EOL;
    echo 'State.var: ' . $as->variable . PHP_EOL;
    $as->variable = 'Dirty';
    $as->success();
});

for ( $i = 0; $i < 3; ++$i )
{
    $root_as = new AsyncSteps();
    $root_as->copyFrom( $model_as );
    $root_as->add( function( $as ) use ( $model_as ) {
        $as->add( function( $as ){
            echo '>> The first inner step' . PHP_EOL;
            $as->success();
        });
        $as->copyFrom( $model_as );
        $as->successStep();
    });
    $root_as->execute();
}

// Process all pending events
AsyncToolTest::run();
