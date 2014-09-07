<?php

use \FutoIn\RI\AsyncToolTest as AsyncToolTest;

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
        
        $this->assertTrue(
            $as->state()->error_called,
            "Error handler was not called"
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
            $as->state()->error_called,
            "Error step was called"
        );
        
        $this->assertHasEvents();
        
        // Auto-added final success
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        
        $this->assertTrue(
            $as->state()->error_called,
            "Error step was not called"
        );
        
        $this->assertFalse(
            $as->state()->final_called,
            "Final step was called"
        );

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
            $as->state()->error_called,
            "Error step was called"
        );
        
        $this->assertHasEvents();
        
        // Auto-added final success
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        
        $this->assertTrue(
            $as->state()->error_called,
            "Error step was not called"
        );
        
        $this->assertFalse(
            $as->state()->final_called,
            "Final step was called"
        );

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
        
        $this->assertEquals( 2, AsyncToolTest::getEvents()->count(), "There must be two timeout events pending" );
        
        AsyncToolTest::nextEvent();
        
        $this->assertEquals( 2, $as->state()->cancel_called, "Cancel callbacks call mismatch" );
        
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        
        $this->assertTrue( $as->state()->second_called, "Second was not called" );
        $this->assertEquals( 1, AsyncToolTest::getEvents()->count(), "There must be one timeout events pending" );
        
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

    public function testDerivedClasses()
    {
        $as = $this->as = new ASTestImpl();
        
        $as->as_test_used = false;
        $as->asp_test_used = false;
        
        $as->add(
            function($as){
                $this->assertFalse( $as->as_test_used, "ASTestImpl is called" );
                $this->assertTrue( $as->asp_test_used, "AsyncStepsProtection not called" );
                $as->asp_test_used = false;
                
                $p = $as->parallel();
                $p->add(
                    function( $as )
                    {
                        $this->assertTrue( $as->as_test_used, "ASTestImpl not called" );
                        $this->assertTrue( $as->asp_test_used, "AsyncStepsProtection not called" );
                    }
                );
            }
        );
        
        $as->execute();
        AsyncToolTest::nextEvent();
        AsyncToolTest::nextEvent();
        $this->assertNoEvents();
    }
}

class ASTestImpl extends \FutoIn\RI\AsyncSteps
{
    public function __construct( $state=null )
    {
        parent::__construct( $state );
        $this->state()->{self::STATE_ASP_CLASS} = __NAMESPACE__.'\\ASPTestImpl';
        $this->state()->as_test_used = true;
    }
}

class ASPTestImpl extends \FutoIn\RI\Details\AsyncStepsProtection
{
    public function __construct( $parent, &$adapter_stack )
    {
        parent::__construct( $parent, $adapter_stack );
        $this->state()->asp_test_used = true;
    }

}
