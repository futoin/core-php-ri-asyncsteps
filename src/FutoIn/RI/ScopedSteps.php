<?php
/**
 * @package FutoIn\Core\PHP\RI\AsyncSteps
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
    /** @see AsyncSteps */
    public function __construct( $state=null )
    {
        // TODO: check that event loop is AsyncToolTest
    
        if ( AsyncToolTest::getEvents() === null )
        {
            AsyncToolTest::init();
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


