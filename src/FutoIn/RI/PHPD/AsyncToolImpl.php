<?php

namespace FutoIn\RI\PHPD;

class AsyncToolImpl
    extends \FutoIn\RI\Details\AsyncToolImpl
{
    public function callLater( $cb, $delay_ms=0 )
    {
        $t = new \StdClass();
        $t->id = \PHPDaemon\Core\Timer::add( function() use ($cb, $t){
            $this->cancelCall( $t ):
            $cb();
        }, $delay_ms );
        return $t;
    }
    
    public function cancelCall( $ref )
    {
        \PHPDaemon\Core\Timer::remove( $ref->id );
    }
}
