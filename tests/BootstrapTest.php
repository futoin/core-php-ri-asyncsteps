<?php

class BootstrapTest extends PHPUnit_Framework_TestCase
{
    public function testAsyncSteps()
    {
        \FutoIn\RI\AsyncToolTest::init();
        new \FutoIn\RI\AsyncSteps();
        $this->assertTrue( true );
    }
}
