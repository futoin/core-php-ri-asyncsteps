<?php
/**
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
    
    private $root;
    private $adapter_stack;
    public $_onerror;
    public $_oncancel;
    public $_queue = null;
    public $_limit_event = null;
    
    
    public function __construct( $root, &$adapter_stack )
    {
        $this->root = $root;
        $this->adapter_stack = &$adapter_stack;
    }
    
    public function _cleanup()
    {
        $this->root = null;
        unset( $this->adapter_stack );
        $this->_onerror = null;
        $this->_oncancel = null;
        $this->_queue = null;
    }
    
    private function _sanityCheck()
    {
        if ( !$this->root ||
             ( end($this->adapter_stack) !== $this ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    }
    
    public function add( callable $func, callable $onerror=null )
    {
        $this->_sanityCheck();
        
        $q = $this->_queue;
        
        if ( !$q )
        {
            $q = new \SplQueue();
            $this->_queue = $q;
        }

        $q->enqueue( [ $func, $onerror ] );
        
        return $this;
    }
    
    public function parallel( callable $onerror=null )
    {
        $p = new ParallelStep( $this->root, $this );
        $this->add( function($as) use ( $p ){
            $p->executeParallel($as);
        }, $onerror );
        return $p;
    }
    
    public function state()
    {
        return $this->root->state();
    }    

    public function success()
    {
        $this->_sanityCheck();
        
        if ( !$this->_queue )
        {
            $this->root->_handle_success( func_get_args() );
        }
        else
        {
            // Must be properly cancelled, no late completion
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    }
    
    public function successStep()
    {
        $this->_sanityCheck();
        
        if ( $this->_queue )
        {
            $this->_queue->enqueue( [ null, null ] );
        }
        else
        {
            $this->success();
        }
    }

    
    public function error( $name, $error_info=null )
    {
        $this->_sanityCheck();
        
        if ( !$this->_queue )
        {
            $this->root->error( $name, $error_info );
        }
        else
        {
            // Must be properly cancelled, no late completion
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    }

    public function setTimeout( $timeout_ms )
    {
        if ( $this->_limit_event )
        {
            \FutoIn\RI\AsyncTool::cancelCall( $this->_limit_event );
        }
        
        $this->_limit_event = \FutoIn\RI\AsyncTool::callLater(
            function(){
                \FutoIn\RI\AsyncTool::cancelCall( $this->_limit_event );
                $this->_limit_event = null;

                // Skip own sanity checks for top-of-stack
                $this->root->_handle_error( \FutoIn\Error::Timeout );
            },
            $timeout_ms
        );
    }
    
    public function __invoke()
    {
        $this->_sanityCheck();
        
        if ( !$this->_queue )
        {
            $this->root->_handle_success( func_get_args() );
        }
        else
        {
            // Must be properly cancelled, no late completion
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    }
    
    public function setCancel( callable $oncancel )
    {
        $this->_oncancel = $oncancel;
    }
    
    public function execute()
    {
        // not allowed
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function cancel()
    {
        // not allowed
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function copyFrom( \FutoIn\AsyncSteps $other )
    {
        if ( ! ( $other instanceof \FutoIn\RI\AsyncSteps ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
        
        $this->_sanityCheck();
        
        // Copy steps
        $oq = $other->getInnerQueue();
        $oq->rewind();
        
        if ( $oq->valid() )
        {
            $q = $this->_queue;
            
            if ( !$q )
            {
                $q = new \SplQueue();
                $this->_queue = $q;
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
    public function _getRoot()
    {
        return $this->root;
    }
}
