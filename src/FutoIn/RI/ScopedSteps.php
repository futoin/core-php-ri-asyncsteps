<?php
/**
 * A helper class to use in regular one-request one-process PHP model with
 * no additional event frameworks
 * 
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

namespace FutoIn\RI;

/**
 * Not standard API. Use for synchronous invocation of AsyncSteps, IF there is no true event loop.
 *
 * Example:
 *  $s = new ScopedSteps;
 *  $s->add( ... )->add( ... );
 *  $s->run();
 *
 * \note AsyncTool must be initialized with AsyncToolTest or not initalized at all
 * @api
 */
class ScopedSteps
    extends AsyncSteps
{
    /**
     * Init
     * @see AsyncSteps::__construct()
     * @param object $state Optionally, pass state object
     */
    public function __construct( $state=null )
    {
        // TODO: check that event loop is AsyncToolTest
    
        if ( AsyncToolTest::getEvents() === null )
        {
            AsyncToolTest::init();
        }
        else
        {
            AsyncToolTest::resetEvents();
        }
        
        parent::__construct( $state );
    }
    
    /**
     * Execute all steps until nothing is left
     */
    public function run()
    {
        $this->execute();
        AsyncToolTest::run();
    }
}



