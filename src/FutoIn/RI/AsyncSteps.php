<?php

namespace FutoIn\RI;

/// TODO: Too dirty... To be redesigned.

trait AsyncStepsStateAccessorTrait
{
    public function __set( $name, $value )
    {
        $this->state()->$name = $value;
    }

    public function &__get( $name )
    {
        return $this->state()->$name;
    }

    public function __isset( $name )
    {
        return isset( $this->state()->$name );
    }

    public function __unset($name)
    {
        unset( $this->state()->$name );
    }
}


class AsyncSteps
    implements \FutoIn\AsyncSteps
{
    use AsyncStepsStateAccessorTrait;

    private $queue_;
    private $adapter_stack_;
    private $state_;
    private $next_args_;
    private $execute_event_ = null;
    private $succeeded_;
    private $limit_event_ = null;
    
    public function __construct( $state=null )
    {
        $this->queue_ = new \SplQueue();
        $this->adapter_stack_ = array();
        $this->state_ = ( $state !== null ) ? $state : new \StdClass();
        $this->next_args_ = array();
    }

    public function add( callable $func, callable $onerror=null )
    {
        $o = new \StdClass();
        $o->func = $func;
        $o->onerror = $onerror;

        $this->queue_->enqueue( $o );
        
        return $this;
    }
    
    public function parallel( callable $onerror=null )
    {
        $p = new ParallelStep( $this );
        $this->add( function($as) use ( $p ){
            $p->execute($as);
        }, $onerror );
        return $p;
    }
    
    public function state()
    {
        return $this->state_;
    }
    
    public function success()
    {
        $this->next_args_ = func_get_args();
        
        for(;;)
        {
            $asp = end( $this->adapter_stack_ );
            
            if ( $asp->limit_event_ )
            {
                AsyncTool::cancelCall( $asp->limit_event_ );
                $asp->limit_event_ = null;
            }
            
            //--
            array_pop( $this->adapter_stack_ );
            
            if ( !$this->adapter_stack_ ||
                 !end( $this->adapter_stack_ )->queue_->isEmpty() )
            {
                break;
            }
        }

        if ( $this->adapter_stack_ ||
             !$this->queue_->isEmpty() )
        {
            $this->execute_event_ = AsyncTool::callLater( [$this, 'execute'] );
        }
    }
    
    public function error( $name )
    {
        $this->next_args_ = array();

        while ( count( $this->adapter_stack_ ) )
        {
            $asp = end( $this->adapter_stack_ );
            
            if ( $asp->limit_event_ )
            {
                AsyncTool::cancelCall( $asp->limit_event_ );
                $asp->limit_event_ = null;
            }
            
            if ( $asp->oncancel_ )
            {
                call_user_func( $asp->oncancel_, $asp );
            }
            
            if ( $asp->onerror_ )
            {
                $oc = count( $this->adapter_stack_ );

                // Supress non-empty sub-steps error
                $asp->queue_ = null;

                call_user_func( $asp->onerror_, $asp, $name );
                
                // onError stack may decreases on sucess/error()
                if ( $oc !== count( $this->adapter_stack_ ) )
                {
                    break;
                }
            }

            array_pop( $this->adapter_stack_ );
        }
    }
    
    /// \brief Delay further execution until success() or error() is called
    public function setTimeout( $timeout_ms )
    {
        if ( $this->limit_event_ )
        {
            AsyncTool::cancelCall( $this->limit_event_ );
        }
        
        $this->limit_event_ = AsyncTool::callLater(
            function(){
                AsyncTool::cancelCall( $this->limit_event_ );
                $this->limit_event_ = null;

                $this->error( \FutoIn\Error::Timeout );
            },
            $timeout_ms
        );
    }
    
    public function __invoke()
    {
        call_user_func_array( $this->success, func_get_args() );
    }
    
    public function setCancel( callable $oncancel )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    //! \note Do not use directly!
    public function execute()
    {
        if ( $this->adapter_stack_ )
        {
            $q = end( $this->adapter_stack_ )->queue_;
        }
        else
        {
            $q = $this->queue_;
        }
    
        if ( $q->isEmpty() )
        {
            return;
        }
        
        if ( $this->execute_event_ !== null )
        {
            AsyncTool::cancelCall( $this->execute_event_ );
            $this->execute_event_ = null;
        }
        
        $current = $q->dequeue();
        $q->rewind(); // Make sure inner add() are insert in front
        
        $asp = new AsyncStepsProtection( $this, $this->adapter_stack_ );
        
        $next_args = &$this->next_args_;
        unset( $this->next_args_ ); //yep
        $this->next_args_ = array();
        array_unshift( $next_args, $asp );
        
        try
        {
            $asp->onerror_ = $current->onerror ;
            $asp->oncancel_ = null;
            array_push( $this->adapter_stack_, $asp );
            
            $oc = count( $this->adapter_stack_ );
            call_user_func_array( $current->func, $next_args );
            
            if ( $oc === count( $this->adapter_stack_ ) )
            {
                // If inner add(), continue to that one
                if ( $asp->queue_ )
                {
                    $this->execute_event_ = AsyncTool::callLater( [$this, 'execute'] );
                }
                // If no setTimeout() and no setCancel() called
                elseif ( !$asp->limit_event_ &&
                         !$asp->oncancel_ )
                {
                    // function must either:
                    // a) call inner add() to continue execution of sub-steps
                    // b) call success() or error() to complete execution of current steps
                    // c) call setTimeout() to delay further execution until result is received
                    $this->error( \FutoIn\Error::InternalError );
                }
            }
        }
        catch ( \FutoIn\Error $e )
        {
            $this->error( $e->getMessage() );
        }
    }
    
    //! \note Do not use directly!
    public function cancel()
    {
        if ( $this->limit_event_ )
        {
            AsyncTool::cancelCall( $this->limit_event_ );
            $this->limit_event_ = null;
        }

        while ( count( $this->adapter_stack_ ) )
        {
            $asp = end( $this->adapter_stack_ );
            
            if ( $asp->limit_event_ )
            {
                AsyncTool::cancelCall( $asp->limit_event_ );
                $asp->limit_event_ = null;
            }
            
            if ( $asp->oncancel_ )
            {
                call_user_func( $asp->oncancel_, $this );
            }
            
            array_pop( $this->adapter_stack_ );
        }
    }
}

/// \NOTE: DO NOT use directly
class AsyncStepsProtection
    implements \FutoIn\AsyncSteps
{
    use AsyncStepsStateAccessorTrait;
    
    private $parent_;
    private $adapter_stack_;
    public $onerror_;
    public $oncancel_;
    public $queue_ = null;
    public $limit_event_ = null;
    
    
    public function __construct( $parent, &$adapter_stack )
    {
        $this->parent_ = $parent;
        $this->adapter_stack_ = &$adapter_stack;
    }
    
    public function add( callable $func, callable $onerror=null )
    {
        if ( end($this->adapter_stack_) === $this )
        {
            $o = new \StdClass();
            $o->func = $func;
            $o->onerror = $onerror;
            
            if ( !$this->queue_ )
            {
                $q = new \SplQueue();
                $this->queue_ = $q;
            }
            else
            {
                $q = $this->queue_;
            }

            $q->enqueue( $o );
            
            return $this;
        }
        
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function parallel( callable $onerror=null )
    {
        return $this->parent_->parallel( $onerror );
    }
    
    public function state()
    {
        return $this->parent_->state();
    }    

    public function success()
    {
        if ( ( end($this->adapter_stack_) === $this ) &&
             !$this->queue_ )
        {
            call_user_func_array( [ $this->parent_, 'success' ], func_get_args() );
        }
        else
        {
            // Must be properly cancelled, no late completion
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    }
    
    public function error( $name )
    {
        if ( ( end($this->adapter_stack_) === $this ) &&
             !$this->queue_ )
        {
            $this->parent_->error( $name );
        }
        else
        {
            // Must be properly cancelled, no late completion
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    }

    public function setTimeout( $timeout_ms )
    {
        if ( $this->limit_event_ )
        {
            AsyncTool::cancelCall( $this->limit_event_ );
        }
        
        $this->limit_event_ = AsyncTool::callLater(
            function(){
                AsyncTool::cancelCall( $this->limit_event_ );
                $this->limit_event_ = null;

                // Skip own sanity checks for top-of-stack
                $this->parent_->error( \FutoIn\Error::Timeout );
            },
            $timeout_ms
        );
    }
    
    public function __invoke()
    {
        if ( end($this->adapter_stack_) === $this )
        {
            call_user_func_array( [ $this->parent_, 'success' ], func_get_args() );
        }
        else
        {
            // Must be properly cancelled, no late completion
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    }
    
    public function setCancel( callable $oncancel )
    {
        $this->oncancel_ = $oncancel;
    }
}

/// \NOTE: DO NOT use directly
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
        $s = new AsyncSteps( $this->as_->state() );
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

