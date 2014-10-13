<?php
/**
 * @package FutoIn\Core\PHP\RI\AsyncSteps
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Details;


/**
 * Internal class to organize Parallel step execution
 *
 * @internal Do not use directly, not standard API
 */
class ParallelStep
    implements \FutoIn\AsyncSteps
{
    use AsyncStepsStateAccessorTrait;
    
    private $root_;
    private $as_;
    private $parallel_steps_;
    private $complete_count = 0;
    private $error_ = null;
    
    public function __construct( $root, $async_iface )
    {
        $this->root_ = $root;
        $this->as_ = $async_iface;
        $this->parallel_steps_ = array();
    }

    public function add( callable $func, callable $onerror=null )
    {
        $this->parallel_steps_[] = array( $func, $onerror );
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
                $this->root_->handle_error( $this->error_ );
            }
            else
            {
                $this->as_->success();
            }
        }
    }
    
    public function successStep()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function error( $name, $error_info=null )
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
    
    /**
     * @ignore Do not use directly, not standard API
     * @internal
     */
    public function executeParallel($as)
    {
        $root = $as->getRoot();
        
        if ( $root !== $this->root_ )
        {
            $p = clone $this;
            $p->root_ = $root;
            $p->executeParallel( $as );
            unset( $p );
            return;
        }
        
        $this->as_ = $as;
        
        if ( empty( $this->parallel_steps_ ) )
        {
            $this->success();
            return;
        }
        
        $as->setCancel([ $this, 'cancel']);
        
        $as_cls = get_class( $this->root_ );
        
        $plist =  $this->parallel_steps_;
        $this->parallel_steps_ = array();
        
        $success_func = function($as){
            $as->success();
            $this->success();
        };
        
        $error_func = function( $as, $name ){
            $this->error( $name );
        };
        
        foreach ( $plist as $ps )
        {
            $s = new $as_cls( $this->as_->state() );
            
            $s->add(
                function( $as ) use ( $ps, $success_func ) {
                    $as->add(  $ps[0], $ps[1] );
                    $as->add( $success_func );
                },
                $error_func
            );
            
            $this->parallel_steps_[] = $s;

            $s->execute();
        }
    }
    
    /**
     * @ignore Do not use directly
     * @internal
     */
    public function cancel()
    {
        foreach ( $this->parallel_steps_ as $s )
        {
            $s->cancel();
        }
    }
    
    public function execute()
    {
        // not allowed
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function copyFrom( \FutoIn\AsyncSteps $other )
    {
        // not allowed
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function __clone()
    {}

}

