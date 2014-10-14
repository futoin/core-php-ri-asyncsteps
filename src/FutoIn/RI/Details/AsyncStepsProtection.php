<?php
/**
 * @package FutoIn\Core\PHP\RI\AsyncSteps
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI\Details;

/**
 * Internal class to organize AsyncSteps levels during execution
 *
 * @api
 * @internal Do not use directly, not standard API
 */
class AsyncStepsProtection
    implements \FutoIn\AsyncSteps
{
    use AsyncStepsStateAccessorTrait;
    
    private $root_;
    private $adapter_stack_;
    public $onerror_;
    public $oncancel_;
    public $queue_ = null;
    public $limit_event_ = null;
    
    
    public function __construct( $root, &$adapter_stack )
    {
        $this->root_ = $root;
        $this->adapter_stack_ = &$adapter_stack;
    }
    
    public function add( callable $func, callable $onerror=null )
    {
        if ( end($this->adapter_stack_) === $this )
        {
            $o = new \StdClass();
            $o->func = $func;
            $o->onerror = $onerror;
            
            $q = $this->queue_;
            
            if ( !$q )
            {
                $q = new \SplQueue();
                $this->queue_ = $q;
            }

            $q->enqueue( $o );
            
            return $this;
        }
        
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function parallel( callable $onerror=null )
    {
        $p = new ParallelStep( $this->root_, $this );
        $this->add( function($as) use ( $p ){
            $p->executeParallel($as);
        }, $onerror );
        return $p;
    }
    
    public function state()
    {
        return $this->root_->state();
    }    

    public function success()
    {
        if ( ( end($this->adapter_stack_) === $this ) &&
             !$this->queue_ )
        {
            $this->root_->handle_success( func_get_args() );
        }
        else
        {
            // Must be properly cancelled, no late completion
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    }
    
    public function successStep()
    {
        if ( $this->queue_ )
        {
            $this->add( [$this, 'success'] );
            $this->queue_->top()->func = null;
        }
        else
        {
            $this->success();
        }
    }

    
    public function error( $name, $error_info=null )
    {
        if ( ( end($this->adapter_stack_) === $this ) &&
             !$this->queue_ )
        {
            $this->root_->error( $name, $error_info );
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
                $this->root_->handle_error( \FutoIn\Error::Timeout );
            },
            $timeout_ms
        );
    }
    
    public function __invoke()
    {
        if ( ( end($this->adapter_stack_) === $this ) &&
             !$this->queue_ )
        {
            $this->root_->handle_success( func_get_args() );
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
    
    public function execute()
    {
        // not allowed
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function copyFrom( \FutoIn\AsyncSteps $other )
    {
        assert( $other instanceof \FutoIn\RI\AsyncSteps );
        
        if ( end($this->adapter_stack_) !== $this )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
        
        // Copy steps
        $oq = $other->getInnerQueue();
        $oq->rewind();
        
        if ( $oq->valid() )
        {
            $q = $this->queue_;
            
            if ( !$q )
            {
                $q = new \SplQueue();
                $this->queue_ = $q;
            }

            for ( ; $oq->valid(); $oq->next() )
            {
                $q->enqueue( $oq->current() );
            }
        }
        
        // Copy state
        $s = $this->state();
        
        foreach ( $other->state() as $k => $v )
        {
            if ( !isset( $s->{$k} ) )
            {
                $s->{$k} = $v;
            }
        }
    }
    
    public function __clone()
    {
        // not allowed
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    
    /**
     * @ignore
     * @internal Do not use directly, not standard API for INTERNAL USE
     */
    public function getRoot()
    {
        return $this->root_;
    }
}
