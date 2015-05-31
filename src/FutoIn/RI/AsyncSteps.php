<?php
/**
 * Definition of main package interface - AsyncSteps
 *
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI;

/**
 * AsyncSteps reference implementation as per "FTN12: FutoIn Async API"
 *
 * @link http://specs.futoin.org/final/preview/ftn12_async_api.html
 *
 * \note AsyncTool must be initialized prior use
 * @api
 */
class AsyncSteps
    implements \FutoIn\AsyncSteps
{
    use Details\AsyncStepsStateAccessorTrait;

    /** @internal */
    private $queue;
    
    /** @internal */
    private $adapter_stack;
    
    /** @internal */
    private $state;
    
    /** @internal */
    private $next_args;
    
    /** @internal */
    private $execute_event = null;
    
    /** @internal */
    private $in_execute = false;
    
    /**
     * Init
     * @param object $state for INTERNAL use only
     */
    public function __construct( $state=null )
    {
        $this->queue = new \SplQueue();
        $this->adapter_stack = [];

        if ( $state === null )
        {
            $state = new Details\StateObject();
        }
        
        $this->state = $state;
        $this->next_args = [];
    }

    /**
     * Add \$func step executor to end of current AsyncSteps level queue
     * @param callable $func void execute_callback( AsyncSteps as[, previous_success_args] )
     * @param callable $onerror OPTIONAL: void error_callback( AsyncSteps as, error )
     * @return \FutoIn\AsyncSteps reference to $this
     */
    public function add( callable $func, callable $onerror=null )
    {
        if ( !empty( $this->adapter_stack ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }
    
        $this->queue->enqueue( [ $func, $onerror ] );
        
        return $this;
    }
    
    /**
     * Copy steps from other AsyncSteps, useful for sub-step cloning
     *
     * @param \FutoIn\RI\AsyncSteps $other model AsyncSteps object for re-use
     * @return \FutoIn\AsyncSteps reference to $this
     */
    public function copyFrom( \FutoIn\AsyncSteps $other ){
        if ( ! ( $other instanceof \FutoIn\RI\AsyncSteps ) )
        {
            throw new \FutoIn\Error( \FutoIn\Error::InternalError );
        }

        // Copy steps
        $oq = $other->_getInnerQueue();
        $oq->rewind();
        
        $q = $this->queue;
        
        for ( ; $oq->valid(); $oq->next() )
        {
            $q->enqueue( $oq->current() );
        }
        
        // Copy state
        $s = $this->state;
        foreach ( $other->state as $k => $v )
        {
            if ( !isset( $s->{$k} ) )
            {
                $s->{$k} = $v;
            }
        }
    }

    /**
     * PHP-specific: properly copy internal structures after cloning of AsyncSteps
     * @internal
     */
    public function __clone()
    {
        assert( empty( $this->adapter_stack ) );
        
        $this->queue = clone $this->queue;
        $this->state = clone $this->state;
    }

    /**
     * Create special object to queue steps for execution in parallel
     *
     * @param callable $onerror OPTIONAL: void error_callback( AsyncSteps as, error )
     * @return \FutoIn\AsyncSteps Special parallel interface, identical to AsyncSteps
     *
     * All steps added through returned parallel object are seen as a single step and
     * are executed quasi-parallel
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
     * @return \StdClass State object
     */
    public function state()
    {
        return $this->state;
    }

    /**
     * Set "success" state of current step execution
     *
     * @param mixed ... Any passed argument is used as input for the next step
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
    public function _handle_success( $args )
    {
        $this->next_args = $args;

        $aspstack = &$this->adapter_stack;

        if ( !count( $aspstack ) )
        {
            // Not inside AsyncSteps execution
            $this->error( \FutoIn\Error::InternalError, 'Invalid success completion' );
        }

        for ( $asp = end( $aspstack );; )
        {
            if ( $asp->_limit_event )
            {
                AsyncTool::cancelCall( $asp->_limit_event );
                $asp->_limit_event = null;
            }
            
            $asp->_cleanup();
            $asp = array_pop( $aspstack );
            
            //--
            if ( !$aspstack )
            {
                break;
            }
            
            $asp = end( $aspstack );
            
            if ( !$asp->_queue->isEmpty() )
            {
                break;
            }
        }

        if ( $aspstack ||
             !$this->queue->isEmpty() )
        {
            $this->execute_event = AsyncTool::callLater( [$this, 'execute'] );
        }
    }
    
    /**
     * Call success() or add efficient dummy step equal to as.success() in behavior
     * (depending on presence of other sub-steps)
     */
    public function successStep()
    {
        // Not inside AsyncSteps execution
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }

    
    /**
     * Set "error" state of current step execution
     *
     * @param string $name Type of error
     * @param string $error_info Error description to be put into "error_info" state field
     * @see \FutoIn\Error
     */
    public function error( $name, $error_info=null )
    {
        $this->state->error_info = $error_info;

        if ( !$this->in_execute )
        {
            $this->_handle_error( $name );
        }
        
        throw new \FutoIn\Error( $name );
    }
    
    /**
     * Set "error" state of current step execution
     * @ignore Do not use directly, not standard API
     * @internal
     */
    public function _handle_error( $name )
    {
        $this->next_args = array();
        
        $aspstack = &$this->adapter_stack;

        while ( count( $aspstack ) )
        {
            $asp = end( $aspstack );
            
            if ( $asp->_limit_event )
            {
                AsyncTool::cancelCall( $asp->_limit_event );
                $asp->_limit_event = null;
            }
            
            if ( $asp->_oncancel )
            {
                call_user_func( $asp->_oncancel, $asp );
                $asp->_oncancel = null;
            }
            
            if ( $asp->_onerror )
            {
                $oc = count( $aspstack );

                // Supress non-empty sub-steps error
                $asp->_queue = null;

                try
                {
                    $this->in_execute = true;
                    call_user_func( $asp->_onerror, $asp, $name );
                    $this->in_execute = false;
                }
                catch ( \Exception $e )
                {
                    $this->in_execute = false;
                    $this->state()->last_exception = $e;
                    $name = $e->getMessage();
                }
                
                // onError stack may decreases on success()
                if ( $oc !== count( $aspstack ) )
                {
                    return;
                }
            }
            
            $asp->_cleanup();

            array_pop( $aspstack );
        }

        // Reach the bottom of error handler stack. End execution. Cleanup.
        $this->queue = new \SplQueue(); // No clear() method so far PHP #60759
        
        if ( $this->execute_event !== null )
        {
            AsyncTool::cancelCall( $this->execute_event );
            $this->execute_event = null;
        }
    }
    
    /**
     * Delay further execution until as.success() or as.error() is called
     *
     * @param int $timeout_ms Timeout in milliseconds
     */
    public function setTimeout( $timeout_ms )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    /**
     * PHP-specific alias for success()
     *
     * @param mixed ... Any passed argument is used as input for the next step
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
     *
     * It can be called only on root instance of AsyncSteps
     */
    public function execute()
    {
        if ( $this->execute_event !== null )
        {
            AsyncTool::cancelCall( $this->execute_event );
            $this->execute_event = null;
        }
        
        $aspstack = &$this->adapter_stack;
        
        if ( $aspstack )
        {
            $q = end( $aspstack )->_queue;
        }
        else
        {
            $q = $this->queue;
        }
    
        if ( $q->isEmpty() )
        {
            return;
        }
        
        $current = $q->dequeue();
        
        // Runtime optimization
        if ( $current[0] === null )
        {
            $this->_handle_success([]);
            return;
        }
        
        $q->rewind(); // Make sure inner add() are insert in front
        
        $asp = new Details\AsyncStepsProtection( $this, $aspstack );
        
        $next_args = &$this->next_args;
        unset( $this->next_args ); //yep
        $this->next_args = array();
        array_unshift( $next_args, $asp );
        
        try
        {
            $asp->_onerror = $current[1] ;
            $asp->_oncancel = null;
            $aspstack[] = $asp;
            
            $oc = count( $aspstack );
            $this->in_execute = true;
            call_user_func_array( $current[0], $next_args );
            
            if ( $oc === count( $aspstack ) )
            {
                // If inner add(), continue to that one
                if ( $asp->_queue )
                {
                    $this->execute_event = AsyncTool::callLater( [$this, 'execute'] );
                }
                // If no setTimeout() and no setCancel() called
                elseif ( !$asp->_limit_event &&
                         !$asp->_oncancel )
                {
                    $this->_handle_success([]);
                }
            }
            
            $this->in_execute = false;
        }
        catch ( \Exception $e )
        {
            $this->in_execute = false;
            $this->state()->last_exception = $e;
            $this->_handle_error( $e->getMessage() );
        }
    }
    
    /**
     * Cancel execution of AsyncSteps
     *
     * It can be called only on root instance of AsyncSteps
     */
    public function cancel()
    {
        if ( $this->execute_event !== null )
        {
            AsyncTool::cancelCall( $this->execute_event );
            $this->execute_event = null;
        }
        
        $aspstack = &$this->adapter_stack;

        while ( count( $aspstack ) )
        {
            $asp = array_pop( $aspstack );
            
            if ( $asp->_limit_event )
            {
                AsyncTool::cancelCall( $asp->_limit_event );
                $asp->_limit_event = null;
            }
            
            if ( $asp->_oncancel )
            {
                call_user_func( $asp->_oncancel, $asp );
            }
            
            $asp->_cleanup();
        }
        
        // End execution. Cleanup.
        $this->queue = new \SplQueue(); // No clear() method so far PHP #60759
    }
    
    /**
     * @ignore Do not use directly, not standard API
     * @internal
     */
    public function _getInnerQueue()
    {
        return $this->queue;
    }
    
    /**
     * Execute loop until *as.break()* is called
     * @param callable $func loop body callable( as )
     * @param string $label optional label to use for *as.break()* and *as.continue()* in inner loops
     */
    public function loop( callable $func, $label = null )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    /**
     * For each *map* or *list* element call *func( as, key, value )*
     * @param array $maplist
     * @param callable $func loop body *func( as, key, value )*
     * @param string $label optional label to use for *as.break()* and *as.continue()* in inner loops
     */
    public function loopForEach( $maplist, callable $func, $label = null )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    /**
     * Call *func(as, i)* for *count* times
     * @param integer $count how many times to call the *func*
     * @param callable $func loop body *func( as, key, value )*
     * @param string $label optional label to use for *as.break()* and *as.continue()* in inner loops
     */
    public function repeat( $count, callable $func, $label = null )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }

    /**
     * Break execution of current loop, throws exception
     * @param string $label unwind loops, until *label* named loop is exited
     */
    public function breakLoop( $label = null )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }

    /**
     * Ccontinue loop execution from the next iteration, throws exception
     * @param string $label break loops, until *label* named loop is found
     */
    public function continueLoop( $label = null )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
}
