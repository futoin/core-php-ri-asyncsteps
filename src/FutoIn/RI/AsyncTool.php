<?php
/**
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */


namespace FutoIn\RI;

/**
 * Wrapper interface for singleton AsyncTools implementation
 *
 * @api
 */
class AsyncTool
{
    private static $impl;
    
    /**
     * \note Not assumed to be instantiated
     */
    private function __construct(){}
    
    /**
     * Install Async Tool implementation
     *
     * @param $impl AsyncTools implementation
     * @see \FutoIn\RI\Details\AsyncToolImpl::init
     */
    public static function init( Details\AsyncToolImpl $impl )
    {
        self::$impl = $impl;
    }
    
    /**
     * Schedule $cb for later execution after $delays_ms milliseconds
     *
     * @return Any value, which serves as reference to the scheduled item
     */
    public static function callLater( $cb, $delay_ms=0 )
    {
        return self::$impl->callLater( $cb, $delay_ms );
    }
    
    /**
     * Cancel previously scheduled $ref item
     */
    public static function cancelCall( $ref )
    {
        return self::$impl->cancelCall( $ref );
    }
}
