<?php
/**
 * Main interface for asynchronous event scheduling
 *
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
    /** @internal */
    private static $impl;
    
    /**
     * \note Not assumed to be instantiated
     * @ignore
     */
    private function __construct(){}
    
    /**
     * Install Async Tool implementation
     *
     * @param \FutoIn\RI\Details\AsyncToolImpl $impl AsyncTools implementation
     * @see \FutoIn\RI\Details\AsyncToolImpl::init()
     */
    public static function init( Details\AsyncToolImpl $impl )
    {
        self::$impl = $impl;
    }
    
    /**
     * Schedule $cb for later execution after $delays_ms milliseconds
     *
     * @param callable $cb Callable to execute
     * @param int $delay_ms Required delay in milliseconds
     *
     * @return mixed which serves as reference to the scheduled item
     */
    public static function callLater( callable $cb, $delay_ms=0 )
    {
        return self::$impl->callLater( $cb, $delay_ms );
    }
    
    /**
     * Cancel previously scheduled $ref item
     * @param mixed $ref Any value returned from callLater()
     * @return bool
     */
    public static function cancelCall( $ref )
    {
        return self::$impl->cancelCall( $ref );
    }
}
