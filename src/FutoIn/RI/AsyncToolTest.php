<?php
/**
 * @package FutoIn\Core\PHP\RI\AsyncSteps
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */


namespace FutoIn\RI;

/**
 * Async Tool implementation for testing purposes
 *
 * Install like \FutoIn\RI\AsyncToolTest::init().
 *
 * The primary feature is predictive event firing for debugging
 * and Unit Testing through nextEvent()
 *
 * @api
 */
class AsyncToolTest
    extends \FutoIn\RI\Details\AsyncToolImpl
{
    private static $queue = null;

    protected function __construct()
    {
        if ( !self::$queue )
        {
            self::$queue = [];
        }
    }
    
    /**
     * @see \FutoIn\RI\Details\AsyncToolImpl::callLater
     */
    public function callLater( $cb, $delay_ms=0 )
    {
        $t = new \StdClass();
        $t->cb = $cb;
        
        $q = &self::$queue;
        
        $t->firetime = ( microtime( true ) * 1e6 ) + $delay_ms;
      
        for ( $i = 0; $i < count( $q ); ++$i )
        {
            $c = &$q[$i];
            
            if ( $c->firetime > $t->firetime )
            {
                array_splice( $q, $i, 0, [$t] );
                return $t;
            }
        }
        
        $q[] = $t;
        return $t;
    }
    
    /**
     * @see \FutoIn\RI\Details\AsyncToolImpl::cancelCall
     */
    public function cancelCall( $t )
    {
        $q = &self::$queue;
        
        for ( $i = 0; $i < count( $q ); ++$i )
        {
            if ( $q[$i] === $t )
            {
                array_splice( $q, $i, 1 );
                break;
            }
        }
    }
    
    /**
     * Wait and execute the next item in queue, if any
     */
    public static function nextEvent()
    {
        if ( self::hasEvents() )
        {
            $t = array_shift( self::$queue );
            
            $sleep = $t->firetime - ( microtime( true ) * 1e6 );
            
            if ( $sleep > 0 )
            {
                usleep( (int) $sleep );
            }
            
            call_user_func( $t->cb );
        }
    }
    
    /**
     * Check if any item is scheduled (for unit testing)
     */
    public static function hasEvents()
    {
        return count( self::$queue ) > 0;
    }

    /**
     * Reset event queue (for unit testing)
     */
    public static function resetEvents()
    {
        self::$queue = [];
    }

    /**
     * Get internal item queue (for unit testing)
     */
    public static function &getEvents()
    {
        return self::$queue;
    }
    
    /**
     * Run event loop until last event pending
     */
    public static function run()
    {
        while ( self::hasEvents() )
        {
            self::nextEvent();
        }
    }
};
