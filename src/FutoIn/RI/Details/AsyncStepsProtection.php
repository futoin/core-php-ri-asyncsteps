<?php
/**
 * Definition of actual execution AsyncSteps "protecting" proxy
 * @internal
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
        $this->root->error( $name, $error_info );
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
        $oq = $other->_getInnerQueue();
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
    
    
    /**
     * Execute loop until *as.break()* is called
     * @param callable $func loop body callable( as )
     * @param string $label optional label to use for *as.break()* and *as.continue()* in inner loops
     */
    public function loop( callable $func, $label = null )
    {
        $this->_sanityCheck();
        
        $loop_state = new \StdClass;
        $loop_state->model_as = new \FutoIn\RI\AsyncSteps();
        $loop_state->inner_as = null;
        $loop_state->outer_as = null;
        $loop_state->func = $func;
        $loop_state->label = $label;

        $this->add( function( $outer_as ) use ( $loop_state ) {
            $loop_state->outer_as = $outer_as;
            
            $create_iteration = function() use ( $outer_as, $loop_state ) {
                $inner_as = new \FutoIn\RI\AsyncSteps( $outer_as->state() );
                $inner_as->copyFrom( $loop_state->model_as );
                $loop_state->inner_as = $inner_as;
                $inner_as->execute();
            };
            
            $loop_state->model_as->add(
                function( $as ) use ( $loop_state )
                {
                    $func = $loop_state->func;
                    $func( $as );
                },
                function( $as, $err ) use ( $loop_state )
                {
                    if ( $err === \FutoIn\Error::LoopCont )
                    {
                        $label = $loop_state->label;
                        $term_label = $as->state()->_loop_term_label;

                        if ( $term_label &&
                             ( $term_label !== $label ) )
                        {
                            // Unroll loops continue
                            \FutoIn\RI\AsyncTool::callLater(
                                function() use ( $loop_state, $term_label ) {
                                    try
                                    {
                                        $loop_state->outer_as->continue_( $term_label );
                                    }
                                    catch ( \Exception $e )
                                    {}
                                }
                            );
                        }
                        else
                        {
                            $as->success();
                            return;
                        }
                    }
                    elseif ( $err === \FutoIn\Error::LoopBreak )
                    {
                        $label = $loop_state->label;
                        $term_label = $as->state()->_loop_term_label;

                        if ( $term_label &&
                             ( $term_label !== $label ) )
                        {
                            // Unroll loops and break
                            \FutoIn\RI\AsyncTool::callLater(
                                function() use ( $loop_state, $term_label ) {
                                    try
                                    {
                                        $loop_state->outer_as->break_( $term_label );
                                    }
                                    catch ( \Exception $e )
                                    {}
                                }
                            );
                        }
                        else
                        {
                            // Continue linear execution
                            \FutoIn\RI\AsyncTool::callLater(
                                function() use ( $loop_state, $term_label ) {
                                    try
                                    {
                                        $loop_state->outer_as->success();
                                    }
                                    catch ( \Exception $e )
                                    {}
                                }
                            );
                        }
                    }
                    else
                    {
                        \FutoIn\RI\AsyncTool::callLater(
                            function() use ( $loop_state, $err ) {
                                try
                                {
                                    $loop_state->outer_as->error( $err );
                                }
                                catch ( \Exception $e )
                                {}
                            }
                        );
                    }
                    
                    $loop_state->model_as->cancel();
                    $loop_state->model_as = null;
                    $loop_state->func = null;
                }
            )->add(
                function( $as ) use( $create_iteration )
                {
                    \FutoIn\RI\AsyncTool::callLater( $create_iteration );
                }
            );
            
            $outer_as->setCancel( function( $as ) use ( $loop_state ) {
                $loop_state->inner_as->cancel();
                $loop_state->inner_as = null;

                if ( $loop_state->model_as )
                {
                    $loop_state->model_as->cancel();
                    $loop_state->model_as = null;
                }

                $loop_state->func = null;
            } );
            
            $create_iteration();
        } );
        
        return $this;
    }
    
    /**
     * For each *map* or *list* element call *func( as, key, value )*
     * @param array $maplist
     * @param callable $func loop body *func( as, key, value )*
     * @param string $label optional label to use for *as.break()* and *as.continue()* in inner loops
     */
    public function forEach_( $maplist, callable $func, $label = null )
    {
        $keys = array_keys( $maplist );
        
        $this->repeat(
            count( $keys ),
            function( $as, $i ) use ( $keys, $maplist, $func )
            {
                $k = $keys[ $i ];
                $func( $as, $k, $maplist[ $k ] );
            },
            $label
        );

        return $this;
    }
    
    /**
     * Call *func(as, i)* for *count* times
     * @param integer $count how many times to call the *func*
     * @param callable $func loop body *func( as, key, value )*
     * @param string $label optional label to use for *as.break()* and *as.continue()* in inner loops
     */
    public function repeat( $count, callable $func, $label = null )
    {
        $loop_state = new \StdClass;
        $loop_state->cnt = $count;
        $loop_state->func = $func;
        $loop_state->i = 0;
        
        $this->loop(
            function( $as ) use ( $loop_state )
            {
                if ( $loop_state->i < $loop_state->cnt )
                {
                    $loop_state->func( $as, $loop_state->i++ );
                }
                else
                {
                    $as->break_();
                }
            },
            $label
        );
        
        return $this;
    }

    /**
     * Break execution of current loop, throws exception
     * @param string $label unwind loops, until *label* named loop is exited
     */
    public function break_( $label = null )
    {
        $this->_sanityCheck();
        $this->state()->_loop_term_label = $label;
        $this->error( \FutoIn\Error::LoopBreak );
    }

    /**
     * Ccontinue loop execution from the next iteration, throws exception
     * @param string $label break loops, until *label* named loop is found
     */
    public function continue_( $label = null )
    {
        $this->_sanityCheck();
        $this->state()->_loop_term_label = $label;
        $this->error( \FutoIn\Error::LoopCont );
    }
}
