<?php

namespace FutoIn\RI\Details;

/**
 * \brief Internal class to organize AsyncSteps levels during execution
 * \warning: DO NOT use directly
 */
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
            \FutoIn\RI\AsyncTool::cancelCall( $this->limit_event_ );
        }
        
        $this->limit_event_ = \FutoIn\RI\AsyncTool::callLater(
            function(){
                \FutoIn\RI\AsyncTool::cancelCall( $this->limit_event_ );
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
