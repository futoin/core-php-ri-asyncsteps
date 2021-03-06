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
class AsyncStepsTest extends PHPUnit_Framework_TestCase
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

    public function testExecuteNoAction()
    {
        $as = $this->as;
        
        $as->state()->error_called = false;
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
            },
            function( \FutoIn\AsyncSteps $as, $name ){
                $this->assertEquals( $name, "InternalError" );
                $as->state()->error_called = true;
            }
        );
        
        $as->execute();
        
        $this->assertFalse(
            $as->state()->error_called,
            "Error handler was called"
        );
        
        $this->assertNoEvents();
    }
    
    public function testExecuteSuccess()
    {
        $as = $this->as;
        
        $as->state()->error_called = false;
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->success();
            },
            function( \FutoIn\AsyncSteps $as, $name ){
                $as->state()->error_called = true;
            }
        );
        
        $as->execute();
        
        $this->assertFalse(
            $as->state()->error_called,
            "Error handler was called"
        );
        
        $this->assertNoEvents();
    }
    
    public function testExecuteError()
    {
        $as = $this->as;
        
        $as->state()->error_called = false;
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->error( "MyError" );
            },
            function( \FutoIn\AsyncSteps $as, $name ){
                $as->state()->error_called = true;
            }
        );
        
        $as->execute();
        
        $this->assertTrue(
            $as->state()->error_called,
            "Error handler was not called"
        );
        
        $this->assertNoEvents();
    }

    
    public function testAddSimple()
    {
        $as = $this->as;
        
        $as->state()->executed1 = false;
        $as->state()->executed2 = false;
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->state()->executed1 = true;
                $as->success();
            }
        );
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->state()->executed2 = true;
                $as->success();
            }
        );
        
        $as->execute();
        
        $this->assertTrue(
            $as->state()->executed1,
            "Step 1 was not executed"
        );
        
        $this->assertFalse(
            $as->state()->executed2,
            "Step 2 was executed"
        );
        
        $this->assertTrue(
            AsyncToolTest::hasEvents(),
            "Step 2 is not scheduled"
        );
        
        $as->state()->executed1 = false;
        AsyncToolTest::nextEvent();

        $this->assertFalse(
            $as->state()->executed1,
            "Step 1 was executed"
        );
        
        $this->assertTrue(
            $as->state()->executed2,
            "Step 2 was not executed"
        );

        $this->assertNoEvents();
    }
    
    public function testAddInner()
    {
        $as = $this->as;
        
        $as->state()->executed1 = false;
        $as->state()->executed1_1 = false;
        $as->state()->executed1_2 = false;
        $as->state()->executed2 = false;
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->state()->executed1 = true;
                
                $as->add(function( \FutoIn\AsyncSteps $as ){
                    $as->state()->executed1_1 = true;
                    $as->success( "1_1" );
                });
                
                $as->add(function( \FutoIn\AsyncSteps $as, $val ){
                    $as->state()->executed1_2 = true;
                    $this->assertEquals( "1_1", $val );
                    $as->success( "1_2" );
                });
                
            }
        );
        
        $as->add(
            function( \FutoIn\AsyncSteps $as, $val ){
                $as->state()->executed2 = true;
                $this->assertEquals( "1_2", $val );
            }
        );
        
        $as->execute();
        
        $this->assertTrue(
            $as->state()->executed1,
            "Step 1 was not executed"
        );
        
        $this->assertFalse(
            $as->state()->executed1_1,
            "Step 1_1 was executed"
        );
        
        $this->assertFalse(
            $as->state()->executed1_2,
            "Step 1_1 was executed"
        );

        $this->assertFalse(
            $as->state()->executed2,
            "Step 2 was executed"
        );
        
        $this->assertTrue(
            AsyncToolTest::hasEvents(),
            "Next steps are not scheduled"
        );
        
        AsyncToolTest::nextEvent();
        
        $this->assertTrue(
            $as->state()->executed1_1,
            "Step 1_1 was not executed"
        );
        
        $this->assertFalse(
            $as->state()->executed1_2,
            "Step 1_2 was executed"
        );
        
        $this->assertTrue(
            AsyncToolTest::hasEvents(),
            "Next steps are not scheduled"
        );
        
        AsyncToolTest::nextEvent();

        $this->assertTrue(
            $as->state()->executed1_2,
            "Step 1_2 was not executed"
        );

        $this->assertFalse(
            $as->state()->executed2,
            "Step 2 was executed"
        );
        
        $this->assertTrue(
            AsyncToolTest::hasEvents(),
            "Next steps are not scheduled"
        );
        
        AsyncToolTest::nextEvent();
        
        $this->assertTrue(
            $as->state()->executed2,
            "Step 2 was not executed"
        );

        $this->assertNoEvents();
    }
    
    public function testSuccessInOnError()
    {
        $as = $this->as;
        
        $as->state()->error_called = false;
        $as->state()->second_called = false;
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->error( "MyError" );
            },
            function( \FutoIn\AsyncSteps $as, $name ){
                $this->assertEquals( "MyError", $name );
                $as->state()->error_called = true;
                $as->success( "ErrorSuccess" );
            }
        )->add(
            function( \FutoIn\AsyncSteps $as, $val ){
                $this->assertEquals( "ErrorSuccess", $val );
                $as->state()->second_called = true;
            }
        );
        
        $as->execute();
        
        $this->assertTrue(
            $as->state()->error_called,
            "Error handler was not called"
        );
        
        $this->assertHasEvents();
        
        AsyncToolTest::nextEvent();

        $this->assertTrue(
            $as->state()->second_called,
            "Second step was not called"
        );

        $this->assertNoEvents();
    }

    public function testSuccessInOnErrorWithSubsteps()
    {
        $as = $this->as;
        
        $as->state()->error_called = false;
        $as->state()->second_called = false;
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->add(
                    function( $as ){
                        $as->add(function( $as ){
                            $as->error( "MyError" );
                        });
                        // null error handler
                    },
                    function( $as, $name ){
                        // intercept here
                        $this->assertEquals( "MyError", $name );
                        $as->success( "ErrorSuccess" );
                    }
                );
            },
            function( \FutoIn\AsyncSteps $as, $name ){
                // should not be called
                $as->state()->error_called = true;
            }
        )->add(
            function( \FutoIn\AsyncSteps $as, $val ){
                $this->assertEquals( "ErrorSuccess", $val );
                $as->state()->second_called = true;

                // Make sure, adapter stack is cleared => no previous error handler is triggered
                //$as->success();
            }
        );
        
        $as->execute();
        
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        
        $this->assertHasEvents();
        
        AsyncToolTest::nextEvent();
        
        $this->assertFalse(
            $as->state()->error_called,
            "Error handler was called"
        );

        $this->assertTrue(
            $as->state()->second_called,
            "Second step was not called"
        );

        $this->assertNoEvents();
    }

    public function testParallelSuccess()
    {
        $as = $this->as;
        
        $as->state()->first_called = false;
        $as->state()->second_called = false;
        $as->state()->final_called = false;

        $parallel = $as->parallel();
        
        $parallel->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->state()->first_called = true;
                $as->success();
            }
        )->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->state()->second_called = true;
                $as->success();
            }
        );
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->state()->final_called = true;
            }
        );
        
        // Initialize parallel
        $as->execute();

        $this->assertFalse(
            $as->state()->first_called,
            "First step was called"
        );
        $this->assertFalse(
            $as->state()->second_called,
            "Second step was called"
        );

        // Auto-added final success
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();

        $this->assertTrue(
            $as->state()->first_called,
            "First step was not called"
        );
        $this->assertTrue(
            $as->state()->second_called,
            "Second step was not called"
        );
        
        $this->assertFalse(
            $as->state()->final_called,
            "Final step was called"
        );
        
        $this->assertHasEvents();
        
        // Auto-added final success
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        
        $this->assertFalse(
            $as->state()->final_called,
            "Final step was called"
        );
        
        AsyncToolTest::nextEvent();
        
        $this->assertTrue(
            $as->state()->final_called,
            "Final step was not called"
        );

        $this->assertNoEvents();
    }
    
    public function testParallelInnerSuccess()
    {
        $as = $this->as;
        
        $as->state()->first_called = false;
        $as->state()->second_called = false;
        $as->state()->final_called = false;

        $as->add(function($as){
            $parallel = $as->parallel();
            
            $parallel->add(
                function( \FutoIn\AsyncSteps $as ){
                    $as->state()->first_called = true;
                    $as->success();
                }
            )->add(
                function( \FutoIn\AsyncSteps $as ){
                    $as->state()->second_called = true;
                    $as->success();
                }
            );
            
            $as->add(
                function( \FutoIn\AsyncSteps $as ){
                    $as->state()->final_called = true;
                }
            );
        });
        
        // Initialize parallel
        $as->execute();
        AsyncToolTest::nextEvent();

        $this->assertFalse(
            $as->state()->first_called,
            "First step was called"
        );
        $this->assertFalse(
            $as->state()->second_called,
            "Second step was called"
        );

        // Auto-added final success
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();

        $this->assertTrue(
            $as->state()->first_called,
            "First step was not called"
        );
        $this->assertTrue(
            $as->state()->second_called,
            "Second step was not called"
        );
        
        $this->assertFalse(
            $as->state()->final_called,
            "Final step was called"
        );
        
        $this->assertHasEvents();
        
        // Auto-added final success
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        
        $this->assertFalse(
            $as->state()->final_called,
            "Final step was called"
        );
        
        AsyncToolTest::nextEvent();
        
        $this->assertTrue(
            $as->state()->final_called,
            "Final step was not called"
        );

        $this->assertNoEvents();
    }

    public function testParallelError()
    {
        $as = $this->as;
        
        $as->state()->first_called = false;
        $as->state()->second_called = false;
        $as->state()->final_called = false;
        $as->state()->error_called = false;

        $parallel = $as->parallel(function( \FutoIn\AsyncSteps $as, $name ){
            $as->found_error = $name;
            $as->state()->error_called = true;
        });
        
        $parallel->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->state()->first_called = true;
                $as->error("MyError");
            }
        )->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->state()->second_called = true;
                $as->success();
            }
        );
        
        $as->add(
            function( \FutoIn\AsyncSteps $as ){
                $as->state()->final_called = true;
            }
        );
        
        
        // Initialize parallel
        $as->execute();

        $this->assertFalse(
            $as->state()->first_called,
            "First step was called"
        );
        $this->assertFalse(
            $as->state()->second_called,
            "Second step was called"
        );
        $this->assertFalse(
            $as->state()->error_called,
            "Error step was called"
        );


        // Auto-added final success
        AsyncToolTest::nextEvent();

        $this->assertTrue(
            $as->state()->first_called,
            "First step was not called"
        );
        $this->assertFalse(
            $as->state()->second_called,
            "Second step was called"
        );
        $this->assertTrue(
            $as->state()->error_called,
            "Error step was not called"
        );

        $this->assertFalse(
            $as->state()->final_called,
            "Final step was called"
        );

        $this->assertEquals( 'MyError', $as->found_error );
        
        $this->assertNoEvents();
    }
    
    public function testParallelInnerError()
    {
        $as = $this->as;
        
        $as->state()->first_called = false;
        $as->state()->second_called = false;
        $as->state()->final_called = false;
        $as->state()->error_called = false;

        $as->add(function($as){
            $parallel = $as->parallel(function( \FutoIn\AsyncSteps $as, $name ){
                $as->found_error = $name;
                $as->state()->error_called = true;
            });
            
            $parallel->add(
                function( \FutoIn\AsyncSteps $as ){
                    $as->state()->first_called = true;
                    $as->error("MyError");
                }
            )->add(
                function( \FutoIn\AsyncSteps $as ){
                    $as->state()->second_called = true;
                    $as->success();
                }
            );
            
            $as->add(
                function( \FutoIn\AsyncSteps $as ){
                    $as->state()->final_called = true;
                }
            );
        });
        
        // Initialize parallel
        $as->execute();
        AsyncToolTest::nextEvent();

        $this->assertFalse(
            $as->state()->first_called,
            "First step was called"
        );
        $this->assertFalse(
            $as->state()->second_called,
            "Second step was called"
        );
        $this->assertFalse(
            $as->state()->error_called,
            "Error step was called"
        );


        // Auto-added final success
        AsyncToolTest::nextEvent();

        $this->assertTrue(
            $as->state()->first_called,
            "First step was not called"
        );
        $this->assertFalse(
            $as->state()->second_called,
            "Second step was called"
        );
        $this->assertTrue(
            $as->state()->error_called,
            "Error step was not called"
        );
        
        $this->assertFalse(
            $as->state()->final_called,
            "Final step was called"
        );
        
        $this->assertEquals( 'MyError', $as->found_error );

        $this->assertNoEvents();
    }

    public function testCancelInner()
    {
        $as = $this->as;
        
        $as->state()->first_called = false;
        $as->state()->second_called = false;
        $as->state()->error_called = false;
        
        $as->add(
            function($as)
            {
                $as->add(
                    function($as)
                    {
                        $as->setCancel(function(\FutoIn\AsyncSteps $as){
                            $as->state()->first_called = true;
                            
                            $this->assertFalse(
                                $as->state()->second_called,
                                "Second cancel was called"
                            );
                        });
                    },
                    function($as,$name)
                    {
                        $as->state()->error_called = true;
                    }

                );
                
                $as->setCancel(function(\FutoIn\AsyncSteps $as){
                    $as->state()->second_called = true;
                    
                    $this->assertTrue(
                        $as->state()->first_called,
                        "First cancel was not called"
                    );
                });

            },
            function($as,$name)
            {
                $as->state()->error_called = true;
            }
        );
        
        //--
        $as->execute();

        $this->assertHasEvents();

        //--
        $as->execute();
    
        $this->assertNoEvents();
        
        $this->assertFalse(
            $as->state()->first_called,
            "First cancel was called"
        );
        
        $this->assertFalse(
            $as->state()->second_called,
            "First cancel was called"
        );

                
        //--
        $as->cancel();
        
        $this->assertTrue(
            $as->state()->second_called,
            "Second cancel was not called"
        );

        $this->assertFalse(
            $as->state()->error_called,
            "Error step was called"
        );
        
        $this->assertNoEvents();
    }

    public function testCancelParallel()
    {
        $as = $this->as;
        
        $as->state()->first_called = false;
        $as->state()->second_called = false;
        $as->state()->error_called = false;
        
        $parallel = $as->parallel(function( \FutoIn\AsyncSteps $as, $name ){
            $as->state()->error_called = true;
        });

        
        $parallel->add(
            function($as)
            {
                $as->setCancel(function($as){
                    $as->state()->first_called = true;
                });
            }
        );
        $parallel->add(
            function($as)
            {
                $as->setCancel(function($as){
                    $as->state()->second_called = true;
                });
            }
        );
        
        $as->add(function($as){});
        
        //--
        $as->execute();
        
        // Whitebox test...
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        
        $this->assertFalse(
            $as->state()->error_called,
            "Error step was called"
        );
    
        $this->assertNoEvents();
        
        $this->assertFalse(
            $as->state()->first_called,
            "First cancel was called"
        );
        
        $this->assertFalse(
            $as->state()->second_called,
            "First cancel was called"
        );

                
        //--
        $as->cancel();

        $this->assertTrue(
            $as->state()->first_called,
            "First cancel was not called"
        );

        $this->assertTrue(
            $as->state()->second_called,
            "Second cancel was not called"
        );

        $this->assertFalse(
            $as->state()->error_called,
            "Error step was called"
        );
        
        $this->assertNoEvents();
    }

    public function testTimeout()
    {
        $as = $this->as;
        $as->state()->error = "";
        
        $start = microtime(true);
        
        $as->add(
            function($as){
                $as->setTimeout(1e4);
            },
            function($as,$name){
                $as->state()->error = $name;
            }
        );
        
        $as->execute();
        $this->assertEquals( "", $as->state()->error, "Error handler was called" );
        
        AsyncToolTest::nextEvent();
        $this->assertEquals( "Timeout", $as->state()->error, "Invalid error reason/not called" );
        
        $this->assertTrue( (microtime(true)-$start)*1e6>1e4, "Minimal timeout delay is not obeyed" );
        
        $this->assertNoEvents();
    }
    
    public function testClearTimeout()
    {
        $as = $this->as;
        $as->state()->error = "";
        
        $start = microtime(true);
        
        $as->add(
            function($as){
                $as->state()->inner_as = $as;
                $as->setTimeout(1e4);
            },
            function($as,$name){
                $as->state()->error = $name;
            }
        )->add(
            function($as){
                $as->success();
            });
        
        $as->execute();
        $this->assertEquals( "", $as->state()->error, "Error handler was called" );
        
        $as->state()->inner_as->success();
        AsyncToolTest::nextEvent();
        $this->assertEquals( "", $as->state()->error, "Error handler was called" );
        
        $this->assertNoEvents();
    }

    public function testUberComplex()
    {
        // first step
        // inner Steps, 3 levels
        // Timeouts on level 2
        // Longer timeout on level 3
        // cancel on all levels
        // error handler, level 2, ignore timeout
        // second step with error handler
        // add inner with timeout
        // cancel
        // make sure no error handlers are called and nore pending events
        
        $as = $this->as;
        
        $as->state()->cancel_called = 0;
        $as->state()->second_called = false;
        $as->state()->error_called = false;
        
        $as->add(
            function($as)
            {
                $as->setCancel(function($as){
                    $as->state()->cancel_called += 1;
                });
                
                $as->add(
                    function($as)
                    {
                        $as->setCancel(function($as){
                            $as->state()->cancel_called += 1;
                        });
                        
                        $as->setTimeout(1e4);

                        $as->add(
                            function($as)
                            {
                                $as->setCancel(function($as){
                                    $as->state()->cancel_called += 1;
                                });
                                
                                $as->setTimeout(1e5);
                            },
                            function($as,$name)
                            {
                                // fall through
                            }
                        );
                    },
                    function($as,$name)
                    {
                        if ( $name === 'Timeout' )
                        {
                            $as->success( 'S' );
                            return;
                        }
                        
                        $as->state()->error_called = true;
                    }
                );
            },
            function($as,$name)
            {
                $as->state()->error_called = true;
            }
        )->add(
            function($as,$success)
            {
                $as->add(function($as){
                    $as->state()->second_called = true;
                    $as->setTimeout(1e6);
                });
            },
            function($as,$name)
            {
                $as->state()->error_called = true;
            }
        );
        
        $as->execute();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        
        $this->assertEquals( 2, count(AsyncToolTest::getEvents()), "There must be two timeout events pending" );
        
        AsyncToolTest::nextEvent();
        
        $this->assertEquals( 2, $as->state()->cancel_called, "Cancel callbacks call mismatch" );
        
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        
        $this->assertTrue( $as->state()->second_called, "Second was not called" );
        $this->assertEquals( 1, count(AsyncToolTest::getEvents()), "There must be one timeout events pending" );
        
        // During inner second step timeout
        $as->cancel();
        $this->assertFalse( $as->state()->error_called, "Error handler was called" );
        $this->assertNoEvents();
    }
    
    public function testStateAccessors()
    {
        $as = $this->as;
        
        $as->my_var = "my_val";
        $this->assertTrue( isset( $as->my_var ), "Not set (accessor)" );
        $this->assertTrue( isset( $as->state()->my_var ), "Not set (state)" );
        $this->assertEquals( "my_val", $as->my_var );
        
        $as->add(
            function($as){
                $this->assertTrue( isset( $as->my_var ), "Not set (accessor)" );
                $this->assertTrue( isset( $as->state()->my_var ), "Not set (state)" );
                $this->assertEquals( "my_val", $as->my_var );
                
                $as->my_var = "my_val2";
                
                $p = $as->parallel();
                $this->assertTrue( isset( $p->my_var ), "Not set (accessor)" );
                $this->assertTrue( isset( $p->state()->my_var ), "Not set (state)" );
                $this->assertEquals( "my_val2", $p->my_var );
                
                // Test also implicit success by empty parallel

                unset( $as->my_var );
            }
        )->add(
            function($as){
                $this->assertFalse( isset( $as->my_var ), "Is set (accessor)" );
                $this->assertFalse( isset( $as->state()->my_var ), "Is set (state)" );
                $as->success();
            }
        );
        
        $as->execute();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        $this->assertNoEvents();
    }

    public function testClone()
    {
        $ras = new ASTestImpl();
        
        $ras->as_test_used = false;
        
        $pas = $this->as;
        $pas->inner_copied = true;
        $p = $pas->parallel();
        $p->add(
            function( $as )
            {
                $this->assertTrue( $as->as_test_used, "ASTestImpl not called" );
            }
        );

        
        $ras->add(
            function($as) use ($pas){
                $this->assertFalse( $as->as_test_used, "ASTestImpl is called" );
                
                $this->assertFalse( isset( $as->inner_copied ), "inner_copied is set" );
                
                $as->copyFrom( $pas );
                
                $this->assertTrue( isset( $as->inner_copied ), "inner_copied is not set" );
            }
        );
        
        $as = $this->as = clone $ras;
        
        $as->execute();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        $this->assertNoEvents();
        
        $this->assertTrue( $as->as_test_used, "ASTestImpl not called" );
        $this->assertFalse( $ras->as_test_used, "reference model state is affected as_test_used" );
    }
    
    public function testScopedSteps()
    {
        $s = new \FutoIn\RI\ScopedSteps;
        
        $s->add(function( $as ){
            $as->success( "123" );
        })->add(function( $as, $val ){
            $as->my_val = $val;
        });

        $s->run();
        
        $this->assertEquals( "123", $s->my_val );
        $this->assertNoEvents();
    }
    
    public function testSuccessStep()
    {
        $as = $this->as;
        $as->final_called = false;
        
        $as->add(function($as){
            $as->successStep();
        })->add(function($as){
            $as->add(function($as){
                $as->success();
            });
            $as->successStep();
        })->add(function($as){
            $p = $as->parallel();
            $p->add(function($as){
                $as->add(function($as){
                    $as->success();
                });
                $as->successStep();
            });
            $as->successStep();
        })->add(function($as){
            $as->final_called = true;
        });
        
        $as->execute();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        $this->assertNoEvents();
        $this->assertTrue( $as->final_called, "Final step was not called" );
    }
    
    /**
     * Situations when control is passed to external step machines
     * with setCancel and/or setTimeout()
     */
    
    public function testAsyncError()
    {
        $as = $this->as;
        $as->executed = false;
        
        $as->add( function($as){
            $as->setCancel(function(){});
            AsyncTool::callLater(function() use ($as) {
                $as->success( 'MyValue' );
            });
        })->add( function($as, $value ){
            $as->executed = ( $value === 'MyValue' ); 
        });
        
        $as->execute();
        AsyncToolTest::run();
        $this->assertTrue( $as->executed );
    }
    
    public function testAsyncSuccess()
    {
        $as = $this->as;
        $as->executed = false;
        
        $as->add(
            function($as){
                $as->setCancel(function(){});
                AsyncTool::callLater(function() use ($as) {
                    try
                    {
                        $as->error( 'MyError' );
                    }
                    catch ( \Exception $e )
                    {}
                });
            },
            function($as, $err ){
                $as->executed = ( $err === 'MyError' ); 
            }
        );
        
        $as->execute();
        AsyncToolTest::run();
        $this->assertTrue( $as->executed );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testRootAddException()
    {
        $as = $this->as;
        
        $as->add( function($as){
            $as->add(function($as){
                $as->success();
            });
        } );
        
        $as->execute();
        $as->add( function($as){} );
    }
    
    public function testSanityCheck()
    {
        $as = $this->as;
        
        $as->add(
        function($as){
            $ras = $as;
            $as->add(function($as) use ($ras) {
                $ras->success();
            });
        },
        function($as, $err)
        {
            $this->assertEquals( 'InternalError', $err );
        });
        
        $as->execute();
        AsyncToolTest::run();
    }

    public function testInvalidSuccess()
    {
        $as = $this->as;
        
        $as->add(
        function($as){
            $as->add(function($as){
                $as->success();
            });
            $as->success();
        },
        function($as, $err)
        {
            $this->assertEquals( 'InternalError', $err );
        });
        
        $as->execute();
        AsyncToolTest::run();
    }
    
    public function testInvalidInvoke()
    {
        $as = $this->as;
        
        $as->add(
        function($as){
            $as->add(function($as){
                $as->success();
            });
            $as();
        },
        function($as, $err)
        {
            $this->assertEquals( 'InternalError', $err );
            $as();
        });
        
        $as->execute();
        AsyncToolTest::run();
    }
    
    public function testInvalidError()
    {
        $as = $this->as;
        
        $as->add(
        function($as){
            $as->setTimeout( 10 );
            $as->setTimeout( 20 );
            $as->add(function($as){
                $as->success();
            });
            $as->error( 'MyError' );
        },
        function($as, $err)
        {
            $this->assertEquals( 'InternalError', $err );
        });
        
        $as->execute();
        AsyncToolTest::run();
    }
    
    public function testInvalidControlCalls()
    {
        $as = $this->as;
        
        $as->add(
            function($as){
                $as->execute();
                $this->assertFalse( true );
            },
            function($as, $err)
            {
                $this->assertEquals( 'InternalError', $err );
                $as();
            }
        )->add(
            function($as){
                $as->cancel();
                $this->assertFalse( true );
            },
            function($as, $err)
            {
                $this->assertEquals( 'InternalError', $err );
                $as();
            }
        )->add(
            function($as){
                clone $as;
                $this->assertFalse( true );
            },
            function($as, $err)
            {
                $this->assertEquals( 'InternalError', $err );
                $as();
            }
        )->add(
            function($as){
                $as->copyFrom( new \StdClass );
                $this->assertFalse( true );
            },
            function($as, $err)
            {
                $this->assertEquals( 'InternalError', $err );
                $as();
            }
        );
        
        $as->execute();
        AsyncToolTest::run();
    }

    /**
     * @expectedException \FutoIn\Error
     */
    public function testRootSuccessException()
    {
        $this->as->success();
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testRootSuccessStepException()
    {
        $this->as->successStep();
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testRootHandleSuccessException()
    {
        $this->as->_handle_success( [] );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testRootTimeoutException()
    {
        $this->as->setTimeout( 0 );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testRootSetCancelException()
    {
        $this->as->setCancel( function($as){} );
    }
    
    /**
     * @expectedException \FutoIn\Error
     */
    public function testRootInvokeException()
    {
        $as = $this->as;
        $as();
    }
    
    public function testErrorOverride()
    {
        $as = $this->as;
        
        $as->found_error = '';
        
        $as->add(
            function($as){
                $as->add(
                    function($as){
                        $as->error('MyError');
                    },
                    function($as, $err){
                        $as->error('OtherError');
                    }
                );
            },
            function($as, $err){
                $as->found_error = $err;
            }
        );
        
        $as->execute();
        AsyncToolTest::run();
        $this->assertEquals( 'OtherError', $as->found_error );
    }
    
    public function testExecuteEmpty()
    {
         $this->as->execute();
         $this->assertTrue( true );
    }
    
    public function testAsyncToolTestInit()
    {
         AsyncToolTest::init();
         $this->assertTrue( true );
    }

    public function testScopedStepsInit()
    {
         $val = &AsyncToolTest::getEvents();
         $val = null;
         $as = new \FutoIn\RI\ScopedSteps();
         $as->run();
         $this->assertTrue( is_array( AsyncToolTest::getEvents() ) );
    }

    public function testOuterAsyncError()
    {
        $as = $this->as;
        
        $as->add(
            function($as){
                $as->success();
            }
        )->add(
            function($as){
                $as->success();
            }
        );
        
        $as->execute();
        
        try
        {
            $as->error( 'MyError' );
        }
        catch ( \FutoIn\Error $e )
        {
            $this->assertTrue( true );
        }
    }
    
    public function testModel()
    {
        $model_as = new \FutoIn\RI\AsyncSteps();
        $model_as->model_run = false;
        $model_as->add(
            function($as){
                $as->model_run = true;
                $as->success();
            }
        );
        
        $as = $this->as;
        
        $as->copyFrom( $model_as );
        $as->add(
            function($as) use ( $model_as ) {
                $this->assertTrue( $as->model_run );
                $as->model_run = false;
                $as->copyFrom( $model_as );
                $as->successStep();
            }
        );
        
        $as->execute();
        AsyncToolTest::run();
        $this->assertTrue( $as->model_run );
    }
}

/**
 * Internal class for testing
 * @ignore
 */
class ASTestImpl extends \FutoIn\RI\AsyncSteps
{
    public function __construct( $state=null )
    {
        parent::__construct( $state );
        $this->state()->as_test_used = true;
    }
}
