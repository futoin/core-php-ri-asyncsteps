<?php

namespace FutoIn\RI;

/**
 * \brief Wrapper interface for singleton AsyncTools implementation
 */
class AsyncTool
{
    private static $impl;
    
    /**
     * \note Not assumed to be instantiated
     */
    private function __construct(){}
    
    /**
     * \brief Install Async Tool implementation
     * \param $impl AsyncTools implementation
     * \see \FutoIn\RI\Details\AsyncToolImpl::init
     */
    public static function init( Details\AsyncToolImpl $impl )
    {
        self::$impl = $impl;
    }
    
    /**
     * \brief Schedule \p $cb for later execution after \p $delays_ms milliseconds
     * \return Any value, which serves as reference to the scheduled item
     */
    public static function callLater( $cb, $delay_ms=0 )
    {
        return self::$impl->callLater( $cb, $delay_ms );
    }
    
    /**
     * \brief Cancel previously scheduled \p $ref item
     */
    public static function cancelCall( $ref )
    {
        return self::$impl->cancelCall( $ref );
    }
}
