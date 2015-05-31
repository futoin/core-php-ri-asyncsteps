
[![Build Status](https://travis-ci.org/futoin/core-php-ri-asyncsteps.svg)](https://travis-ci.org/futoin/core-php-ri-asyncsteps)

Reference implementation of:
 
    FTN12: FutoIn Async API
    Version: 1.7
    
Spec: [FTN12: FutoIn Async API v1.x](http://specs.futoin.org/final/preview/ftn12_async_api-1.html)

[Web Site](http://futoin.org/)


# About

Adds classical linear program flow structure to async programming
supporting exceptions. error handlers, timeouts, unlimited number of sub-steps,
execution parallelism and job state/context variables.

There is little benefit of using this tool in classical PHP aproach of one-request-one-execution approach.
However, it is base for FutoIn Invoker and Executor implementation, which target at daemon-like
execution pattern.

It should be possible to use any other async framework from AsyncSteps by using
setCancel() and/or setTimeout() methods which allow step completion without success() or
error() result. Specific step-associated AsyncSteps interface will be valid for success() or error()
call on external event.

It also possible to use FutoIn AsyncSteps from any other event framework. Just make sure to notify
external framework from the top most error handler (for errors) and last step (for success).

To minimize cost of closure creation on repetitive execution, a special feature of "model" step
is available: model step is created as usual, but must never get executed. It possible to copy steps
and state variables using AsyncSteps#copyFrom() to a newly created object.

# Installation

Command line:
```sh
$ composer require 'futoin/core-php-ri-asyncsteps'
```
and/or composer.json:
```
    "require" : {
        "futoin/core-php-ri-asyncsteps" : "^1.1",
    }
```

# Concept

*NOTE: copy&paste from [FTN12: FutoIn Async API v1.x](http://specs.futoin.org/final/preview/ftn12_async_api-1.html)*

This interface was born as a secondary option for
executor concept. However, it quickly became clear that
async/reactor/proactor/light threads/etc. should be base
for scalable high performance server implementations, even though it is more difficult for understanding and/or debugging.
Traditional synchronous program flow becomes an addon
on top of asynchronous base for legacy code and/or too
complex logic.

Program flow is split into non-blocking execution steps, represented
with execution callback function. Processing Unit (eg. CPU) halting/
spinning/switching-to-another-task is seen as a blocking action in program flow.

Any step must not call any of blocking functions, except for synchronization
with guaranteed minimal period of lock acquisition.
*Note: under minimal period, it is assumed that any acquired lock is 
immediately released after action with O(1) complexity and no delay
caused by programmatic suspension/locking of executing task*

Every step is executed sequentially. Success result of any step
becomes input for the following step.

Each step can have own error handler. Error handler is called, if
AsyncSteps.error() is called within step execution or any of its 
sub-steps. Typical behavior is to ignore error and continue or
to make cleanup actions and complete job with error.

Each step can have own sequence of sub-steps. Sub-steps can be added
only during that step execution. Sub-step sequence is executed after
current step execution is finished.

If there are any sub-steps added then current step must not call
AsyncSteps.success() or AsyncSteps.error(). Otherwise, InternalError
is raised.

It is possible to create a special "parallel" sub-step and add
independent sub-steps to it. Execution of each parallel sub-step
is started all together. Parallel step completes with success
when all sub-steps complete with success. If error is raised in
any sub-step of parallel step then all other sub-steps are canceled.

Out-of-order cancel of execution can occur by timeout, 
execution control engine decision (e.g. Invoker disconnect) or
failure of sibling parallel step. Each step can install custom
on-cancel handler to free resources and/or cancel external jobs.
After cancel, it must be safe to destroy AsyncSteps object.

AsyncSteps must be used in Executor request processing. The same 
[root] AsyncSteps object must be used for all asynchronous tasks within
given request processing.

AsyncSteps may be used by Invoker implementation.

AsyncSteps must support derived classes in implementation-defined way.
Typical use case: functionality extension (e.g. request processing API).

For performance reasons, it is not economical to initialize AsyncSteps
with business logic every time. Every implementation must support
platform-specific AsyncSteps cloning/duplicating.

## 1.1. Levels

When AsyncSteps (or derived) object is created all steps are added
sequentially in Level 0 through add() and/or parallel(). Note: each
parallel() is seen as a step.

After AsyncSteps execution is initiated, each step of Level 0 is executed.
All sub-steps are added in Level n+1. Example:

    add() -> Level 0 #1
        add() -> Level 1 #1
            add() -> Level 2 #1
            parallel() -> Level 2 #2
            add() -> Level 2 #3
        parallel() -> Level 1 #2
        add() -> Level 1 #3
    parallel() -> Level 0 #2
    add() -> Level 0 #3

    
Execution cannot continue to the next step of current Level until all steps of higher Level
are executed.

The execution sequence would be:

    Level 0 add #1
    Level 1 add #1
    Level 2 add #1
    Level 2 parallel #2
    Level 2 add #3
    Level 1 parallel #2
    Level 1 add #3
    Level 0 parallel #2
    Level 0 add #3

## 1.2. Error handling

Due to not linear programming, classic try/catch blocks are converted into execute/onerror.
Each added step may have custom error handler. If error handler is not specified then
control passed to lower Level error handler. If non is defined then execution is aborted.

Example:

    add( -> Level 0
        func( as ){
            print( "Level 0 func" )
            add( -> Level 1
                func( as ){
                    print( "Level 1 func" )
                    as.error( "myerror" )
                },
                onerror( as, error ){
                    print( "Level 1 onerror: " + error )
                    as.error( "newerror" )
                }
            )
        },
        onerror( as, error ){
            print( "Level 0 onerror: " + error )
            as.success( "Prm" )
        }
    )
    add( -> Level 0
        func( as, param ){
            print( "Level 0 func2: " + param )
            as.success()
        }
    )


Output would be:

    Level 0 func
    Level 1 func
    Level 1 onerror: myerror
    Level 0 onerror: newerror
    Level 0 func2: Prm
    
In synchronous way, it would look like:

    variable = null

    try
    {
        print( "Level 0 func" )
        
        try
        {
            print( "Level 1 func" )
            throw "myerror"
        }
        catch ( error )
        {
            print( "Level 1 onerror: " + error )
            throw "newerror"
        }
    }
    catch( error )
    {
        print( "Level 0 onerror: " + error )
        variable = "Prm"
    }
    
    print( "Level 0 func2: " + variable )


## 1.3. Wait for external resources

Very often, execution of step cannot continue without waiting for external event like input from network or disk.
It is forbidden to block execution in event waiting. As a solution, there are special setTimeout() and setCancel()
methods.

Example:

    add(
        func( as ){
            socket.read( function( data ){
                as.success( data )
            } )
            
            as.setCancel( function(){
                socket.cancel_read()
            } )
            
            as.setTimeout( 30_000 ) // 30 seconds
        },
        onerror( as, error ){
            if ( error == timeout ) {
                print( "Timeout" )
            }
            else
            {
                print( "Read Error" )
            }
        }
    )

## 1.4. Parallel execution abort

Definition of parallel steps makes no sense to continue execution if any of steps fails. To avoid
excessive time and resources spent on other steps, there is a concept of canceling execution similar to 
timeout above.

Example:
    
    as.parallel()
        .add(
            func( as ){
                as.setCancel( function(){ ... } )
                
                // do parallel job #1
                as.state()->result1 = ...;
            }
        )
        .add(
            func( as ){
                as.setCancel( function(){ ... } )

                // do parallel job #1
                as.state()->result2 = ...;
            }
        )
        .add(
            func( as ){
                as.error( "Some Error" )
            }
        )
    as.add(
        func( as ){
            print( as.state()->result1 + as.state->result2 )
            as.success()
        }
    )

## 1.5. AsyncSteps cloning

In long living applications the same business logic may be re-used multiple times
during execution.

In a REST API server example, complex business logic can be defined only once and
stored in a kind of AsyncSteps object repository.
On each request, a reference object from the repository would be copied for actual
processing with minimal overhead.

However, there would be no performance difference in sub-step definition unless
its callback function is also created at initialization time, but not at parent
step execution time (the default concept). So, it should be possible to predefine
those as well and copy/inherit during step execution. Copying steps must also
involve copying of state variables.

Example:

    AsyncSteps req_repo_common;
    req_repo_common.add(func( as ){
        as.add( func( as ){ ... } );
        as.copyFrom( as.state().business_logic );
        as.add( func( as ){ ... } );
    });
    
    AsyncSteps req_repo_buslog1;
    req_repo_buslog1
        .add(func( as ){ ... })
        .add(func( as ){ ... });

    AsyncSteps actual_exec = copy req_repo_common;
    actual_exec.state().business_logic = req_repo_buslog1;
    actual_exec.execute();

However, this approach only make sense for deep performance optimizations.

## 1.6. Implicit as.success()

If there are no sub-steps added, no timeout set and no cancel handler set then
implicit as.success() call is assumed to simplify code and increase efficiency.

    as.add(func( as ){
        doSomeStuff( as );
    })

## 1.7. Error Info and Last Exception

Pre-defined state variables:

* **error_info** - value of the second parameter passed to the last *as.error()* call
* **last_exception** - the last exception caught, if feasible

Error code is not always descriptive enough, especially, if it can be generated in multiple ways.
As a convention special "error_info" state field should hold descriptive information of the last error.
Therefore, *as.error()* is extended with optional parameter error_info.

"last_exception" state variables may hold the last exception object caught, if feasible
to implement. It should be populated with FutoIn errors as well.


## 1.8. Async Loops

Almost always, async program flow is not linear. Sometimes, loops are required.

Basic principals of async loops:

        as.loop( func( as ){
            call_some_library( as );
            as.add( func( as, result ){
                if ( !result )
                {
                    // exit loop
                    as.break();
                }
            } );
        } )
        
Inner loops and identifiers:

        // start loop
        as.loop( 
            func( as ){
                as.loop( func( as ){
                    call_some_library( as );
                    as.add( func( as, result ){
                        if ( !result )
                        {
                            // exit loop
                            as.continue( "OUTER" );
                        }

                        as.success( result );
                    } );
                } );
                
                as.add( func( as, result ){
                    // use it somehow
                    as.success();
                } );
            },
            "OUTER"
        )
        
Loop n times.

        as.repeat( 3, func( as, i ){
            print( 'Iteration: ' + i )
        } )
        
Traverse through list or map:

        as.forEach(
            [ 'apple', 'banana' ],
            func( as, k, v ){
                print( k + " = " + v )
            }
        )
        
### 1.8.1. Termination

Normal loop termination is performed either by loop condition (e.g. as.forEach(), as.repeat())
or by as.break() call. Normal termination is seen as as.success() call.

Abnormal termination is possible through as.error(), including timeout, or external as.cancel().
Abnormal termination is seen as as.error() call.


## 1.9. The Safety Rules of libraries with AsyncSteps interface

1. as.success() should be called only in top-most function of the
    step (the one passed to as.add() directly)
1. setCancel() and/or setTimeout() must be called only in top most function
    as repeated call overrides in scope of step

## 1.10. Reserved keyword name clash

If any of API identifiers clashes with reserved word or has illegal symbols then
implementation-defined name mangling is allowed, but with the following guidelines
in priority.

Pre-defined alternative method names, if the default matches language-specific reserved keywords:

* *loop* -> makeLoop
* *forEach* -> loopForEach
* *repeat* -> repeatLoop
* *break* -> breakLoop
* *continue* -> continueLoop
* Otherwise, - try adding underscore to the end of the
    identifier (e.g. do -> do_)


# Examples

## Simple steps

```php
use \FutoIn\RI\AsyncSteps;
use \FutoIn\RI\ScopedSteps;

$root_as = new ScopedSteps();

$root_as->add(
    function( $as ){
        $as->success( "MyValue" );
    }
)->add(
    function( $as, $arg ){
        if ( $arg === 'MyValue' )
        {
            $as->add( function( $as ){
                $as->error( 'MyError', 'Something bad has happened' );
            });
        }

        $as->successStep();
    },
    function( $as, $err )
    {
        if ( $err === 'MyError' )
        {
            $as->success( 'NotSoBad' );
        }
    }
);

$root_as->add(
    function( $as, $arg )
    {
        if ( $arg === 'NotSoBad' )
        {
            echo 'MyError was ignored: ' . $as->state()->error_info . PHP_EOL;
        }
        
        $as->state()->p1arg = 'abc';
        $as->state()->p2arg = 'xyz';
        
        $p = $as->parallel();
        
        $p->add( function( $as ){
            echo 'Parallel Step 1' . PHP_EOL;
            
            $as->add( function( $as ){
                echo 'Parallel Step 1->1' . PHP_EOL;
                $as->p1 = $as->p1arg . '1';
                $as->success();
            } );
        } )
        ->add( function( $as ){
            echo 'Parallel Step 2' . PHP_EOL;
            
            $as->add( function( $as ){
                echo 'Parallel Step 2->1' . PHP_EOL;
                $as->p2 = $as->p2arg . '2';
                $as->success();
            } );
        } );
    }
)->add( function( $as ){
    echo 'Parallel 1 result: ' . $as->state()->p1 . PHP_EOL;
    echo 'Parallel 2 result: ' . $as->p2 . PHP_EOL;
} );

// Note: we use ScopedSteps instead of AsyncSteps in this example
//$root_as->execute();
$root_as->run();
```

Result:
```
MyError was ignored: Something bad has happened
Parallel Step 1
Parallel Step 2
Parallel Step 1->1
Parallel Step 2->1
Parallel 1 result: abc1
Parallel 2 result: xyz2

```


## External event wait

```php
use \FutoIn\RI\AsyncSteps;
use \FutoIn\RI\ScopedSteps;
use \FutoIn\RI\AsyncTool;


function dummy_service_read( $success, $error ){
    // We expect it calles success when data is available
    // and error, if error occurs
    // Returns some request handle
    return null;
}

function dummy_service_cancel( $reqhandle ){
    // We assume it cancels previously scheduled reqhandle
}

$root_as = new ScopedSteps();

$root_as->add( function( $as ){
    AsyncTool::callLater( function() use ( $as ) {
        $as->success( 'async success()' );
    } );
    
    $as->setTimeout( 10 ); // ms
} )->add(
    function( $as, $arg ){
        echo $arg . PHP_EOL;
        
        $reqhandle = dummy_service_read(
            function( $data ) use ( $as ) {
                $as->success( $data );
            },
            function( $err ) use ( $as ) {
                if ( $err !== 'SomeSpecificCancelCode' )
                {
                    $as->error( $err );
                }
            }
        );
        
        $as->setCancel(function($as) use ( $reqhandle ) {
            dummy_service_cancel( $reqhandle );
        });
        
        // OPTIONAL. Set timeout of 1s
        $as->setTimeout( 1000 );
    },
    function( $as, $err )
    {
        echo $err . ": " . $as->error_info . PHP_EOL;
    }
);

// Note: we use ScopedSteps instead of AsyncSteps in this example
//$root_as->execute();
$root_as->run();
```
Result:

```
async success()
Timeout: 
```

## Model steps (avoid closure creation overhead on repetitive execution)

```php
use \FutoIn\RI\AsyncSteps;
use \FutoIn\RI\AsyncToolTest;

// Note, we have no default event engine in PHP
AsyncToolTest::init();

$model_as = new AsyncSteps();
$model_as->state()->variable = 'Vanilla';

$model_as->add( function($as){
    echo '-----' . PHP_EOL;
    echo 'Hi! I am from model_as' . PHP_EOL;
    echo 'State.var: ' . $as->variable . PHP_EOL;
    $as->variable = 'Dirty';
    $as->success();
});

for ( $i = 0; $i < 3; ++$i )
{
    $root_as = new AsyncSteps();
    $root_as->copyFrom( $model_as );
    $root_as->add( function( $as ) use ( $model_as ) {
        $as->add( function( $as ){
            echo '>> The first inner step' . PHP_EOL;
            $as->success();
        });
        $as->copyFrom( $model_as );
        $as->successStep();
    });
    $root_as->execute();
}

// Process all pending events
AsyncToolTest::run();
```
Result. Please note the order as only the first step is executed in the loop.
The rest is executed quasi-parallel by nature of async programming.
The model_as closure gets executed 6 times, but created only once.

```
-----
Hi! I am from model_as
State.var: Vanilla
-----
Hi! I am from model_as
State.var: Vanilla
-----
Hi! I am from model_as
State.var: Vanilla
>> The first inner step
>> The first inner step
>> The first inner step
-----
Hi! I am from model_as
State.var: Dirty
-----
Hi! I am from model_as
State.var: Dirty
-----
Hi! I am from model_as
State.var: Dirty
```
    
# API documentation


