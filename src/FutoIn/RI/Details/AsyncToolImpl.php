<?php
/**
 * @package FutoIn\Core\PHP\RI\AsyncSteps
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Details;

/**
 * Abstract base for AsyncTool implementation
 *
 * @api
 */
abstract class AsyncToolImpl
{
    /**
     * Any derived class should call this c-tor for future-compatibility
     */
    protected function __construct(){}

    /**
     * Install Async Tool implementation, call using derived class
     */
    public static function init()
    {
        \FutoIn\RI\AsyncTool::init( new static() );
    }
    
    /**
     * Schedule $cb for later execution after $delays_ms milliseconds
     *
     * @return Any value, which serves as reference to the scheduled item
     */
    abstract public function callLater( $cb, $delay_ms=0 );
    
    /**
     * Cancel previously scheduled $ref item
     */
    abstract public function cancelCall( $ref );
}
