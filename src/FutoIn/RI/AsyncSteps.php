<?php
/**
 * @package FutoIn\Core\PHP\RI\AsyncSteps
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI;

/**
 * AsyncSteps reference implementation as per "FTN12: FutoIn Async API"
 *
 * @see http://specs.futoin.org/final/preview/ftn12_async_api.html
 *
 * \note AsyncTool must be initialized prior use
 * @api
 */
class AsyncSteps
    implements \FutoIn\AsyncSteps
{
    use Details\AsyncStepsStateAccessorTrait;
    
    /**
     * Implementation-defined state variable name to setup AsyncStepsProtection implementation
     */
    const STATE_ASP_CLASS = '_aspcls';

    private $queue_;
    private $adapter_stack_;
    private $state_;
    private $next_args_;
    private $execute_event_ = null;
    
    /**
     * C-tor
     * @param $state for INTERNAL use only
     */
    public function __construct( $state=null )
    {
        $this->queue_ = new \SplQueue();
        $this->adapter_stack_ = array();

        if ( $state === null )
        {
            $state = new \StdClass();
            $state->error_info = '';
        }
        
        if ( !isset( $state->{self::STATE_ASP_CLASS} ) )
        {
            $state->{self::STATE_ASP_CLASS} = __NAMESPACE__.'\\Details\\AsyncStepsProtection';
        }
        
        $this->state_ = $state;
        $this->next_args_ = array();
    }

    /**
     * Add \$func step executor to end of current AsyncSteps level queue
     * @param $func void execute_callback( AsyncSteps as[, previous_success_args] )
     * @param $onerror OPTIONAL: void error_callback( AsyncSteps as, error )
     * @return reference to $this
     */
    public function add( callable $func, callable $onerror=null )
    {
        if ( !empty( $this->adapter_stack_ ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    
        $o = new \StdClass();
        $o->func = $func;
        $o->onerror = $onerror;

        $this->queue_->enqueue( $o );
        
        return $this;
    }
    
    /**
     * Copy steps from other AsyncSteps, useful for sub-step cloning
     * \note Please see the specification for more information
     * @return reference to $this
     */
    public function copyFrom( \FutoIn\AsyncSteps $other ){
        assert( $other instanceof AsyncSteps );
        
        // Copy steps
        $oq = $other->getInnerQueue();
        $oq->rewind();
        
        $q = $this->queue_;
        
        for ( ; $oq->valid(); $oq->next() )
        {
            $q->enqueue( $oq->current() );
        }
        
        // Copy state
        $s = $this->state_;
        foreach ( $other->state_ as $k => $v )
        {
            $s->{$k} = $v;
        }
    }

    /**
     * PHP-specific: properly copy internal structures after cloning of AsyncSteps
     */
    public function __clone()
    {
        assert( empty( $this->adapter_stack_ ) );
        
        $this->queue_ = clone $this->queue_;
        $this->state_ = clone $this->state_;
    }

    /**
     * Create special object to queue steps for execution in parallel
     *
     * @param $onerror OPTIONAL: void error_callback( AsyncSteps as, error )
     * @return Special parallel interface, identical to AsyncSteps
     * \note Please see the specification for more information
     *
     * All steps added through returned parallel object are seen as a single step
     */
    public function parallel( callable $onerror=null )
    {
        $p = new Details\ParallelStep( $this, $this );
        $this->add( function($as) use ( $p ){
            $p->executeParallel($as);
        }, $onerror );
        return $p;
    }

    /**
     * Access AsyncSteps state object
     *
     * @return State object
     */
    public function state()
    {
        return $this->state_;
    }

    /**
     * Set "success" state of current step execution
     *
     * \note Please see the specification for constraints
     * @param ... Any passed argument is used as input for the next step
     */
    public function success()
    {
        // Not inside AsyncSteps execution
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    /**
     * @ignore Do not use directly, not standard API
     * @internal
     */
    public function handle_success( $args )
    {
        $this->next_args_ = $args;
        
        $aspstack = &$this->adapter_stack_;
        
        if ( !count( $aspstack ) )
        {
            // Not inside AsyncSteps execution
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
        
        do
        {
            $asp = array_pop( $aspstack );
            
            if ( $asp->limit_event_ )
            {
                AsyncTool::cancelCall( $asp->limit_event_ );
                $asp->limit_event_ = null;
            }
        }
        while ( $aspstack &&
                end( $aspstack )->queue_->isEmpty() );

        if ( $aspstack ||
             !$this->queue_->isEmpty() )
        {
            $this->execute_event_ = AsyncTool::callLater( [$this, 'execute'] );
        }
    }
    
    /**
     * Call success() or add sub-step with success() depending on presence of other sub-steps
     */
    public function successStep()
    {
        // Not inside AsyncSteps execution
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }

    
    /**
     * Set "error" state of current step execution
     *
     * @param $name Type of error
     * @param $error_info Error description to be put into "error_info" state field
     * \note Please see the specification for constraints
     * @see \FutoIn\Error
     */
    public function error( $name, $error_info=null )
    {
        if ( $error_info !== null )
        {
            $this->state()->error_info = $error_info;
        }
        
        throw new \FutoIn\Error( $name );
    }
    
    /**
     * Set "error" state of current step execution
     * @ignore Do not use directly, not standard API
     * @internal
     */
    public function handle_error( $name )
    {
        $this->next_args_ = array();
        
        $aspstack = &$this->adapter_stack_;

        while ( count( $aspstack ) )
        {
            $asp = end( $aspstack );
            
            if ( $asp->limit_event_ )
            {
                AsyncTool::cancelCall( $asp->limit_event_ );
                $asp->limit_event_ = null;
            }
            
            if ( $asp->oncancel_ )
            {
                call_user_func( $asp->oncancel_, $asp );
                $asp->oncancel_ = null;
            }
            
            if ( $asp->onerror_ )
            {
                $oc = count( $aspstack );

                // Supress non-empty sub-steps error
                $asp->queue_ = null;

                try
                {
                    call_user_func( $asp->onerror_, $asp, $name );
                }
                catch ( \FutoIn\Error $e )
                {
                    $name = $e->getMessage();
                }
                
                // onError stack may decreases on success()
                if ( $oc !== count( $aspstack ) )
                {
                    return;
                }
            }

            array_pop( $aspstack );
        }
        
        // Reach the bottom of error handler stack. End execution. Cleanup.
        $this->queue_ = new \SplQueue(); // No clear() method so far PHP #60759
        
        if ( $this->execute_event_ !== null )
        {
            AsyncTool::cancelCall( $this->execute_event_ );
            $this->execute_event_ = null;
        }
    }
    
    /**
     * Delay further execution until success() or error() is called
     *
     * @param $timeout_ms Timeout in milliseconds
     * \note Please see the specification
     */
    public function setTimeout( $timeout_ms )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    /**
     * PHP-specific alias for success()
     */
    public function __invoke()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    /**
     * Set cancellation callback
     * @param $oncancel void cancel_callback( AsyncSteps as )
     * \note Please see the specification
     */
    public function setCancel( callable $oncancel )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    /**
     * Start execution of AsyncSteps
     */
    public function execute()
    {
        if ( $this->execute_event_ !== null )
        {
            AsyncTool::cancelCall( $this->execute_event_ );
            $this->execute_event_ = null;
        }
        
        $aspstack = &$this->adapter_stack_;
        
        if ( $aspstack )
        {
            $q = end( $aspstack )->queue_;
        }
        else
        {
            $q = $this->queue_;
        }
    
        if ( $q->isEmpty() )
        {
            return;
        }
        
        $current = $q->dequeue();
        
        // Runtime optimization
        if ( $current->func === null )
        {
            $this->handle_success([]);
            return;
        }
        
        $q->rewind(); // Make sure inner add() are insert in front
        
        $asp_cls = $this->state()->{self::STATE_ASP_CLASS};
        $asp = new $asp_cls( $this, $aspstack );
        
        $next_args = &$this->next_args_;
        unset( $this->next_args_ ); //yep
        $this->next_args_ = array();
        array_unshift( $next_args, $asp );
        
        try
        {
            $asp->onerror_ = $current->onerror ;
            $asp->oncancel_ = null;
            $aspstack[] = $asp;
            
            $oc = count( $aspstack );
            call_user_func_array( $current->func, $next_args );
            
            if ( $oc === count( $aspstack ) )
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
                    $this->handle_error( \FutoIn\Error::InternalError );
                }
            }
        }
        catch ( \FutoIn\Error $e )
        {
            $this->handle_error( $e->getMessage() );
        }
    }
    
    /**
     * Cancel execution
     *
     * @ignore Do not use directly, not standard API
     * @internal
     */
    public function cancel()
    {
        if ( $this->execute_event_ !== null )
        {
            AsyncTool::cancelCall( $this->execute_event_ );
            $this->execute_event_ = null;
        }
        
        $aspstack = &$this->adapter_stack_;

        while ( count( $aspstack ) )
        {
            $asp = array_pop( $aspstack );
            
            if ( $asp->limit_event_ )
            {
                AsyncTool::cancelCall( $asp->limit_event_ );
                $asp->limit_event_ = null;
            }
            
            if ( $asp->oncancel_ )
            {
                call_user_func( $asp->oncancel_, $asp );
            }
        }
        
        // End execution. Cleanup.
        $this->queue_ = new \SplQueue(); // No clear() method so far PHP #60759
    }
    
    /**
     * @ignore Do not use directly, not standard API
     * @internal
     */
    public function getInnerQueue()
    {
        return $this->queue_;
    }
}
