<?php

namespace FutoIn\RI;

class AsyncToolTest
    extends \FutoIn\RI\Details\AsyncToolImpl
{
    private static $queue = null;

    protected function __construct()
    {
        if ( !self::$queue )
        {
            self::$queue = new \SplQueue();
        }
    }
    
    public function callLater( $cb, $delay_ms=0 )
    {
        $t = new \StdClass();
        $t->cb = $cb;
        
        $q = self::$queue;
        
        $q->rewind();
        $t->firetime = ( microtime( true ) * 1e6 ) + $delay_ms;
        
        for ( ; $q->valid(); $q->next() )
        {
            $c = $q->current();
            
            if ( $c->firetime > $t->firetime )
            {
                $q->add( $q->key(), $t );
                return $t;
            }
        }
        
        $q->enqueue( $t );
        return $t;
    }
    
    public function cancelCall( $t )
    {
        $q = self::$queue;
        
        $q->rewind();
        
        for ( ; $q->valid(); $q->next() )
        {
            if ( $q->current() === $t )
            {
                $q->offsetUnset( $q->key() );
                break;
            }
        }
    }
    
    public static function nextEvent()
    {
        if ( self::hasEvents() )
        {
            $t = self::$queue->dequeue();
            
            $sleep = $t->firetime - ( microtime( true ) * 1e6 );
            
            if ( $sleep > 0 )
            {
                usleep( (int) $sleep );
            }
            
            call_user_func( $t->cb );
        }
    }
    
    public static function hasEvents()
    {
        return self::$queue->count() > 0;
    }
    
    public static function resetEvents()
    {
        self::$queue = new \SplQueue();
    }
    
    public static function getEvents()
    {
        return self::$queue;
    }
};
