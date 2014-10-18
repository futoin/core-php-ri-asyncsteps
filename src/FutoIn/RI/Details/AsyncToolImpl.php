<?php
/**
 * Definition of base for custom AsyncTool implementation
 *
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
     * @param callable $cb Callable to execute
     * @param int $delay_ms Required delay in milliseconds
     *
     * @return mixed Any value, which serves as reference to the scheduled item
     */
    abstract public function callLater( $cb, $delay_ms=0 );
    
    /**
     * Cancel previously scheduled $ref item
     * @param mixed $ref Any value returned from callLater()
     */
    abstract public function cancelCall( $ref );
}
