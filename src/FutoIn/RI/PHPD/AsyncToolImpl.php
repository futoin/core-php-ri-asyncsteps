<?php

namespace FutoIn\RI\PHPD;

/**
 * Integration with PHP daemon (http://daemon.io/)
 *
 * \warning NOT TESTED
 *
 * Install like \FutoIn\RI\PHPD\AsyncToolImpl::init()
 * @ignore
 */
class AsyncToolImpl
    extends \FutoIn\RI\Details\AsyncToolImpl
{
    /**
     * @see \FutoIn\RI\Details\AsyncToolImpl::callLater
     */
    public function callLater( $cb, $delay_ms=0 )
    {
        $t = new \StdClass();
        $t->id = \PHPDaemon\Core\Timer::add( function() use ($cb, $t){
            $this->cancelCall( $t );
            $cb();
        }, $delay_ms );
        return $t;
    }
    
    /**
     * @see \FutoIn\RI\Details\AsyncToolImpl::cancelCall
     */
    public function cancelCall( $ref )
    {
        \PHPDaemon\Core\Timer::remove( $ref->id );
    }
}
