<?php

namespace FutoIn\RI;

class AsyncTool
{
    private static $impl;
    
    public static function init( Details\AsyncToolImpl $impl )
    {
        self::$impl = $impl;
    }
    
    public function callLater( $cb, $delay_ms=0 )
    {
        return self::$impl->callLater( $cb, $delay_ms );
    }
    
    public function cancelCall( $ref )
    {
        return self::$impl->cancelCall( $ref );
    }
}
