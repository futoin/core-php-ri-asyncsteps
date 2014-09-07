<?php

namespace FutoIn\RI\Details;

/**
 * \brief Abstract base for AsyncTool implementation
 */
abstract class AsyncToolImpl
{
    /**
     * \brief Any derived class should call this c-tor for future-compatibility
     */
    protected function __construct(){}

    /**
     * \brief Install Async Tool implementation, call using derived class
     */
    public static function init()
    {
        \FutoIn\RI\AsyncTool::init( new static() );
    }
    
    /**
     * \brief Schedule \p $cb for later execution after \p $delays_ms milliseconds
     * \return Any value, which serves as reference to the scheduled item
     */
    abstract public function callLater( $cb, $delay_ms=0 );
    
    /**
     * \brief Cancel previously scheduled \p $ref item
     */
    abstract public function cancelCall( $ref );
}
