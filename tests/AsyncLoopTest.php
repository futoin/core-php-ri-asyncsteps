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
                                $as->break_();
                            }
                            elseif ( $as->i === 1 )
                            {
                                $as->i++;
                                $as->continue_();
                            }
                            
                            $as->i++;
                        }, "INNER1" );

                        $as->loop( function( $as ) {
                            $as->s[] = "INNER2";
                            
                            if ( $as->i === 3 )
                            {
                                $as->icheck = 4;
                                $as->break_( 'MEDIUM' );
                            }
                            
                            $as->break_();
                        }, "INNER2" );
                        
                        $as->loop( function( $as ) {
                            $as->s[] = "INNER3";
                            $as->i++;
                            $as->break_( 'OUTER' );
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
}

/**
            
            it('should forward regular error', function(){
                var as = this.as;
                var reserr;
                
                as.add(
                    function(as)
                    {
                        as.loop( function( as )
                        {
                            as.error( "MyError" );
                        } );
                    },
                    function( as, err )
                    {
                        reserr = err;
                    }
                );
                
                as.execute();
                async_steps.AsyncTool.run();
                assertNoEvents();
                
                reserr.should.equal( 'MyError' );
            });
            
            it('should continue outer loop', function(){
                var as = this.as;
                var reserr = null;
                
                as.add(
                    function(as)
                    {
                        var i = 0;
                        
                        as.loop( function( as )
                        {
                            ++i;
                            
                            if ( i === 3 )
                            {
                                as.break();
                            }
                            
                            as.loop( function( as )
                            {
                                ++i;
                                as.continue( "OUTER" );
                            } );
                        }, "OUTER" );
                    },
                    function( as, err )
                    {
                        reserr = err;
                    }
                );
                
                as.execute();
                async_steps.AsyncTool.run();
                assertNoEvents();
                
                assert.equal( reserr, null );
            });
            
            it('should repeat count times', function(){
                var as = this.as;
                var reserr = null;
                var i = 0;
                
                as.add(
                    function(as)
                    {
                        as.repeat( 3, function( as )
                        {
                            ++i;
                            
                            if ( i == 2 )
                            {
                                as.continue();
                            }
                        } );
                    },
                    function( as, err )
                    {
                        reserr = err;
                    }
                );
                
                as.execute();
                async_steps.AsyncTool.run();
                assertNoEvents();
                
                assert.equal( reserr, null );
                i.should.equal( 3 );
            });
            
            it('should repeat break', function(){
                var as = this.as;
                var reserr = null;
                var i = 0;
                
                as.add(
                    function(as)
                    {
                        as.repeat( 3, function( as )
                        {
                            if ( i == 2 )
                            {
                                as.break();
                            }
                            
                            ++i;
                        } );
                    },
                    function( as, err )
                    {
                        reserr = err;
                    }
                );
                
                as.execute();
                async_steps.AsyncTool.run();
                assertNoEvents();
                
                assert.equal( reserr, null );
                i.should.equal( 2 );
            });
            
            it('should forEach array', function(){
                var as = this.as;
                var reserr = null;
                var i = 0;
                
                as.add(
                    function(as)
                    {
                        as.forEach( [ 1, 2, 3 ], function( as, k, v )
                        {
                            assert.equal( v, k + 1 );
                            i += v;
                        } );
                    },
                    function( as, err )
                    {
                        reserr = err;
                    }
                );
                
                as.execute();
                async_steps.AsyncTool.run();
                assertNoEvents();
                
                assert.equal( reserr, null );
                i.should.equal( 6 );
            });
            
            it('should forEach object', function(){
                var as = this.as;
                var reserr = null;
                var i = 0;
                
                as.add(
                    function(as)
                    {
                        as.forEach( { a: 1, b: 2, c: 3 }, function( as, k, v )
                        {
                            if ( v == 1 ) assert.equal( k, "a" );
                            if ( v == 2 ) assert.equal( k, "b" );
                            if ( v == 3 ) assert.equal( k, "c" );
                            i += v;
                        } );
                    },
                    function( as, err )
                    {
                        reserr = err;
                    }
                );
                
                as.execute();
                async_steps.AsyncTool.run();
                assertNoEvents();
                
                assert.equal( reserr, null );
                i.should.equal( 6 );
            });
        }
*/
