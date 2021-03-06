<?php
/**
 * Parallel Step execution details
 * @internal
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
    
    private $root;
    private $as;
    private $parallel_steps;
    private $complete_count = 0;
    
    public function __construct( $root, $async_iface )
    {
        $this->root = $root;
        $this->as = $async_iface;
        $this->parallel_steps = array();
    }
    
    private function _cleanup()
    {
        $this->root = null;
        $this->as = null;
        $this->parallel_steps = null;
    }

    public function add( callable $func, callable $onerror=null )
    {
        $this->parallel_steps[] = array( $func, $onerror );
        return $this;
    }
    
    public function parallel( callable $onerror=null )
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function state()
    {
        return $this->as->state();
    }
    
    public function success()
    {
        $this->complete_count += 1;
        
        if ( $this->complete_count === count( $this->parallel_steps ) )
        {
            $this->as->success();
            $this->_cleanup();
        }
    }
    
    public function successStep()
    {
        throw new \FutoIn\Error( \FutoIn\Error::InternalError );
    }
    
    public function error( $name, $errorinfo=null )
    {
        try
        {
            $this->as->error( $name );
        }
        catch ( \Exception $e )
        {}
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
        $root = $as->_getRoot();
        
        if ( $root !== $this->root )
        {
            $p = clone $this;
            $p->root = $root;
            $p->executeParallel( $as );
            unset( $p );
            return;
        }
        
        $this->as = $as;
        
        if ( empty( $this->parallel_steps ) )
        {
            $this->success();
            return;
        }
        
        $as->setCancel([ $this, 'cancel']);
        
        $ascls = get_class( $this->root );
        
        $plist =  $this->parallel_steps;
        $this->parallel_steps = array();
        
        $success_func = function($as){
            $as->success();
            $this->success();
        };
        
        $errorfunc = function( $as, $name ){
            $this->error( $name );
        };
        
        $state = $as->state();
        
        foreach ( $plist as $ps )
        {
            $s = new $ascls( $state );
            
            $s->add(
                function( $as ) use ( $ps ) {
                    $as->add(  $ps[0], $ps[1] );
                },
                $errorfunc
            );
            $s->add( $success_func );
            
            $this->parallel_steps[] = $s;
        }
        
        // Intentional, to properly cancel
        foreach ( $this->parallel_steps as $s )
        {
            $s->execute();
        }
    }
    
    /**
     * @ignore Do not use directly
     * @internal
     */
    public function cancel()
    {
        foreach ( $this->parallel_steps as $s )
        {
            $s->cancel();
        }
        $this->_cleanup();
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

