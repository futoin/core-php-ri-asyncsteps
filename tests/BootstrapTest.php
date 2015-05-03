<?php
/**
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
 */

/**
 * @ignore
 */
class BootstrapTest extends PHPUnit_Framework_TestCase
{
    public function testAsyncSteps()
    {
        \FutoIn\RI\AsyncToolTest::init();
        new \FutoIn\RI\AsyncSteps();
        $this->assertTrue( true );
    }
}
