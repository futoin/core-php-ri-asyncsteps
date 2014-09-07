<?php

namespace FutoIn\RI\Details;


/**
 * \brief Internal class to organize Parallel step execution
 * \warning: DO NOT use directly
 */
class ParallelStep
    implements \FutoIn\AsyncSteps
{
    use AsyncStepsStateAccessorTrait;
    
    private $as_;
    private $parallel_steps_;
    private $complete_count = 0;
    private $error_ = null;
    
    public function __construct( $async_iface )
    {
        $this->as_ = $async_iface;
        $this->parallel_steps_ = array();
    }

    public function add( callable $func, callable $onerror=null )
    {
        $as_cls = get_class( $this->as_ );
        $s = new $as_cls( $this->as_->state() );
        $s->add(
            function( $as ) use ( $func, $onerror ) {
                $as->add( $func, $onerror );
                $as->add(function($as){
                    $as->success();
                    $this->success();
                });
            },
            function( $as, $name ){
                $this->error( $name );
            }
        );
        
        array_push( $this->parallel_steps_, $s );
        return $this;
    }
    
    public function parallel( callable $onerror=null )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function state()
    {
        return $this->as_->state();
    }
    
    public function success()
    {
        $this->complete_count += 1;
        
        if ( $this->complete_count === count( $this->parallel_steps_ ) )
        {
            if ( $this->error_ )
            {
                $this->as_->error( $this->error_ );
            }
            else
            {
                $this->as_->success();
            }
        }
    }
    
    public function error( $name )
    {
        $this->error_ = $name;
        $this->success();
    }
    
    public function __invoke()
    {
        $this->success();
    }
    
    public function setTimeout( $timeout_ms )
    {
        /// Should never be called
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function setCancel( callable $oncancel )
    {
        /// Should never be called
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    //! \note DO NOT use directly
    public function execute($as)
    {
        $this->as_ = $as;
        $as->setCancel([ $this, 'cancel']);
        
        if ( empty( $this->parallel_steps_ ) )
        {
            $this->success();
            return;
        }
        
        foreach ( $this->parallel_steps_ as $s )
        {
            $s->execute();
        }
    }
    
    //! \note DO NOT use directly
    public function cancel()
    {
        foreach ( $this->parallel_steps_ as $s )
        {
            $s->cancel();
        }
    }
}

