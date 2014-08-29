<?php

namespace FutoIn\RI\Details;

abstract class AsyncToolImpl
{
    protected function __construct(){}

    public static function init()
    {
        \FutoIn\RI\AsyncTool::init( new static() );
    }
    
    abstract public function callLater( $cb, $delay_ms=0 );
    abstract public function cancelCall( $ref );
}
