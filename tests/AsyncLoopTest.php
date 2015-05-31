<?php
/**
 * @copyright 2014 FutoIn Project (http://futoin.org)
 * @author Andrey Galkin
  */

use \FutoIn\RI\AsyncToolTest;
use \FutoIn\RI\AsyncTool;

/**
 * @ignore
 */
class AsyncLoopTest extends PHPUnit_Framework_TestCase
{
    private $as = null;
    
    public static function setUpBeforeClass()
    {
        AsyncToolTest::init();
    }
    
    public function setUp()
    {
        AsyncToolTest::resetEvents();
        $this->as = new \FutoIn\RI\AsyncSteps();
    }
    
    public function tearDown()
    {
        $this->as = null;
        gc_collect_cycles();
    }
    
    public function assertHasEvents()
    {
        $this->assertTrue(
            AsyncToolTest::hasEvents(),
            "Event is not pending"
        );
    }
    
    public function assertNoEvents()
    {
        $this->assertFalse(
            AsyncToolTest::hasEvents(),
            "Event is pending"
        );
    }
    
    public function testComplexLook()
    {
        $as = $this->as;
        $as->i = 0;
        $as->icheck = 1;
        $as->s = [];
        
        $as->add(
            function( $as )
            {
                $as->loop( function( $as ) {
                    $as->s[] = "OUTER";
                    $as->i++;
                    
                    $as->loop( function( $as ) {
                        $as->s[] = "MEDIUM";
                        $this->assertEquals( $as->icheck, $as->i );
                        
                        $as->loop( function( $as ) {
                            $as->s[] = "INNER1";
                            
                            if ( $as->i > 2 )
                            {
                                $as->breakLoop();
                            }
                            elseif ( $as->i === 1 )
                            {
                                $as->i++;
                                $as->continueLoop();
                            }
                            
                            $as->i++;
                        }, "INNER1" );

                        $as->loop( function( $as ) {
                            $as->s[] = "INNER2";
                            
                            if ( $as->i === 3 )
                            {
                                $as->icheck = 4;
                                $as->breakLoop( 'MEDIUM' );
                            }
                            
                            $as->breakLoop();
                        }, "INNER2" );
                        
                        $as->loop( function( $as ) {
                            $as->s[] = "INNER3";
                            $as->i++;
                            $as->breakLoop( 'OUTER' );
                        }, "INNER3" );
                        
                    }, "MEDIUM" );
                }, "OUTER" );
            },
            function( $as, $err )
            {
                echo "\n$err: ".$as->error_info."\n";
                echo $as->last_exception->getTraceAsString();
            }
        )->execute();
        
        AsyncToolTest::run();
        $this->assertNoEvents();
        
        $this->assertEquals( [ 'OUTER',
                    'MEDIUM',
                    'INNER1',
                    'INNER1',
                    'INNER1',
                    'INNER2',
                    'OUTER',
                    'MEDIUM',
                    'INNER1',
                    'INNER2',
                    'INNER3' ], $as->s );
                    
        $this->assertEquals( 5, $as->i );
    }
    
    public function testForwardRegularError()
    {
        $as = $this->as;
        
        $as->reserr = null;
        
        $as->add(
            function( $as )
            {
                $as->loop( function( $as ) {
                    $as->error( 'MyError' );
                } );
            },
            function( $as, $err )
            {
                $as->reserr = $err;
            }
        )
        ->execute();
        
        AsyncToolTest::run();
        $this->assertNoEvents();
        
        $this->assertEquals( 'MyError', $as->reserr );
    }
    
    public function testContinueOuterLoop()
    {
        $as = $this->as;
        
        $as->reserr = null;
        
        $as->add(
            function( $as )
            {
                $as->i = 0;
                
                $as->loop( function( $as ) {
                    $as->i++;
                    
                    if ( $as->i === 3 )
                    {
                        $as->breakLoop();
                    }
                    
                    $as->loop( function( $as ) {
                        $as->i++;
                        $as->continueLoop( 'OUTER' );
                    } );
                }, 'OUTER' );
            },
            function( $as, $err )
            {
                $as->reserr = $err;
            }
        )
        ->execute();
        
        AsyncToolTest::run();
        $this->assertNoEvents();
        
        $this->assertNull( $as->reserr );
    }
    
    public function testRepeatCountTimes()
    {
        $as = $this->as;
        
        $as->reserr = null;
        
        $as->add(
            function( $as )
            {
                $as->i = 0;
                
                $as->repeat( 3, function( $as ) {
                    $as->i++;
                    
                    if ( $as->i === 2 )
                    {
                        $as->continueLoop();
                    }
                } );
            },
            function( $as, $err )
            {
                $as->reserr = $err;
            }
        )
        ->execute();
        
        AsyncToolTest::run();
        $this->assertNoEvents();
        
        $this->assertNull( $as->reserr );
        $this->assertEquals( 3, $as->i );
    }
    
    public function testBreakRepeat()
    {
        $as = $this->as;
        
        $as->reserr = null;
        
        $as->add(
            function( $as )
            {
                $as->i = 0;
                
                $as->repeat( 3, function( $as ) {
                    if ( $as->i === 2 )
                    {
                        $as->breakLoop();
                    }
                    
                    $as->i++;
                } );
            },
            function( $as, $err )
            {
                $as->reserr = $err;
            }
        )
        ->execute();
        
        AsyncToolTest::run();
        $this->assertNoEvents();
        
        $this->assertNull( $as->reserr );
        $this->assertEquals( 2, $as->i );
    }
    
    function testForEachArray()
    {
        $as = $this->as;
        
        $as->reserr = null;
        
        $as->add(
            function( $as )
            {
                $as->i = 0;
                
                $as->loopForEach( [1, 2, 3], function( $as, $k, $v ) {
                    $this->assertEquals( $k + 1, $v );
                    $as->i += $v;
                } );
            },
            function( $as, $err )
            {
                $as->reserr = $err;
            }
        )
        ->execute();
        
        AsyncToolTest::run();
        $this->assertNoEvents();
        
        $this->assertNull( $as->reserr );
        $this->assertEquals( 6, $as->i );
    }
    
    function testForEachObject()
    {
        $as = $this->as;
        
        $as->reserr = null;
        
        $as->add(
            function( $as )
            {
                $as->i = 0;
                $o = new \StdClass;
                $o->a = 1;
                $o->b = 2;
                $o->c = 3;
                
                $as->loopForEach( $o, function( $as, $k, $v ) {
                    switch ( $k )
                    {
                        case 'a': $rv = 1; break;
                        case 'b': $rv = 2; break;
                        case 'c': $rv = 3; break;
                        default: $this->assertTrue( false );
                    }
                    
                    $this->assertEquals( $rv, $v );
                    $as->i += $v;
                } );
            },
            function( $as, $err )
            {
                $as->reserr = $err;
            }
        )
        ->execute();
        
        AsyncToolTest::run();
        $this->assertNoEvents();
        
        $this->assertNull( $as->reserr );
        $this->assertEquals( 6, $as->i );
    }
}

