
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



<a name="ApiIndex.md"></a>
API Index
=========

* FutoIn
    * FutoIn\RI
        * FutoIn\RI\Details
            * [AsyncStepsProtection](#FutoIn-RI-Details-AsyncStepsProtection.md)
            * [StateObject](#FutoIn-RI-Details-StateObject.md)
            * [ParallelStep](#FutoIn-RI-Details-ParallelStep.md)
            * [AsyncToolImpl](#FutoIn-RI-Details-AsyncToolImpl.md)
        * [AsyncSteps](#FutoIn-RI-AsyncSteps.md)
        * [ScopedSteps](#FutoIn-RI-ScopedSteps.md)
        * [AsyncTool](#FutoIn-RI-AsyncTool.md)
        * [AsyncToolTest](#FutoIn-RI-AsyncToolTest.md)


<a name="FutoIn-RI-AsyncSteps.md"></a>
FutoIn\RI\AsyncSteps
===============

AsyncSteps reference implementation as per &quot;FTN12: FutoIn Async API&quot;




* Class name: AsyncSteps
* Namespace: FutoIn\RI
* This class implements: FutoIn\AsyncSteps






Methods
-------


### __construct

    mixed FutoIn\RI\AsyncSteps::__construct(object $state)

Init



* Visibility: **public**


#### Arguments
* $state **object** - &lt;p&gt;for INTERNAL use only&lt;/p&gt;



### add

    \FutoIn\AsyncSteps FutoIn\RI\AsyncSteps::add(callable $func, callable $onerror)

Add \$func step executor to end of current AsyncSteps level queue



* Visibility: **public**


#### Arguments
* $func **callable** - &lt;p&gt;void execute_callback( AsyncSteps as[, previous_success_args] )&lt;/p&gt;
* $onerror **callable** - &lt;p&gt;OPTIONAL: void error_callback( AsyncSteps as, error )&lt;/p&gt;



### copyFrom

    \FutoIn\AsyncSteps FutoIn\RI\AsyncSteps::copyFrom(\FutoIn\RI\AsyncSteps $other)

Copy steps from other AsyncSteps, useful for sub-step cloning



* Visibility: **public**


#### Arguments
* $other **[FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)** - &lt;p&gt;model AsyncSteps object for re-use&lt;/p&gt;



### parallel

    \FutoIn\AsyncSteps FutoIn\RI\AsyncSteps::parallel(callable $onerror)

Create special object to queue steps for execution in parallel



* Visibility: **public**


#### Arguments
* $onerror **callable** - &lt;p&gt;OPTIONAL: void error_callback( AsyncSteps as, error )&lt;/p&gt;



### state

    \StdClass FutoIn\RI\AsyncSteps::state()

Access AsyncSteps state object



* Visibility: **public**




### success

    mixed FutoIn\RI\AsyncSteps::success()

Set "success" state of current step execution



* Visibility: **public**




### successStep

    mixed FutoIn\RI\AsyncSteps::successStep()

Call success() or add efficient dummy step equal to as.success() in behavior
(depending on presence of other sub-steps)



* Visibility: **public**




### error

    mixed FutoIn\RI\AsyncSteps::error(string $name, string $error_info)

Set "error" state of current step execution



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;Type of error&lt;/p&gt;
* $error_info **string** - &lt;p&gt;Error description to be put into &quot;error_info&quot; state field&lt;/p&gt;



### setTimeout

    mixed FutoIn\RI\AsyncSteps::setTimeout(integer $timeout_ms)

Delay further execution until as.success() or as.error() is called



* Visibility: **public**


#### Arguments
* $timeout_ms **integer** - &lt;p&gt;Timeout in milliseconds&lt;/p&gt;



### __invoke

    mixed FutoIn\RI\AsyncSteps::__invoke()

PHP-specific alias for success()



* Visibility: **public**




### setCancel

    mixed FutoIn\RI\AsyncSteps::setCancel($oncancel)

Set cancellation callback



* Visibility: **public**


#### Arguments
* $oncancel **mixed** - &lt;p&gt;void cancel_callback( AsyncSteps as )
\note Please see the specification&lt;/p&gt;



### execute

    mixed FutoIn\RI\AsyncSteps::execute()

Start execution of AsyncSteps

It can be called only on root instance of AsyncSteps

* Visibility: **public**




### cancel

    mixed FutoIn\RI\AsyncSteps::cancel()

Cancel execution of AsyncSteps

It can be called only on root instance of AsyncSteps

* Visibility: **public**




### loop

    mixed FutoIn\RI\AsyncSteps::loop(callable $func, string $label)

Execute loop until *as.break()* is called



* Visibility: **public**


#### Arguments
* $func **callable** - &lt;p&gt;loop body callable( as )&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### loopForEach

    mixed FutoIn\RI\AsyncSteps::loopForEach(array $maplist, callable $func, string $label)

For each *map* or *list* element call *func( as, key, value )*



* Visibility: **public**


#### Arguments
* $maplist **array**
* $func **callable** - &lt;p&gt;loop body &lt;em&gt;func( as, key, value )&lt;/em&gt;&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### repeat

    mixed FutoIn\RI\AsyncSteps::repeat(integer $count, callable $func, string $label)

Call *func(as, i)* for *count* times



* Visibility: **public**


#### Arguments
* $count **integer** - &lt;p&gt;how many times to call the &lt;em&gt;func&lt;/em&gt;&lt;/p&gt;
* $func **callable** - &lt;p&gt;loop body &lt;em&gt;func( as, key, value )&lt;/em&gt;&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### breakLoop

    mixed FutoIn\RI\AsyncSteps::breakLoop(string $label)

Break execution of current loop, throws exception



* Visibility: **public**


#### Arguments
* $label **string** - &lt;p&gt;unwind loops, until &lt;em&gt;label&lt;/em&gt; named loop is exited&lt;/p&gt;



### continueLoop

    mixed FutoIn\RI\AsyncSteps::continueLoop(string $label)

Ccontinue loop execution from the next iteration, throws exception



* Visibility: **public**


#### Arguments
* $label **string** - &lt;p&gt;break loops, until &lt;em&gt;label&lt;/em&gt; named loop is found&lt;/p&gt;



### __set

    mixed FutoIn\RI\AsyncSteps::__set(string $name, mixed $value)

state() access through AsyncSteps interface / set value



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;
* $value **mixed** - &lt;p&gt;state variable value&lt;/p&gt;



### __get

    mixed FutoIn\RI\AsyncSteps::__get(string $name)

state() access through AsyncSteps interface / get value



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



### __isset

    boolean FutoIn\RI\AsyncSteps::__isset(string $name)

state() access through AsyncSteps interface / check value exists



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



### __unset

    mixed FutoIn\RI\AsyncSteps::__unset(string $name)

state() access through AsyncSteps interface / delete value



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



<a name="FutoIn-RI-AsyncTool.md"></a>
FutoIn\RI\AsyncTool
===============

Wrapper interface for singleton AsyncTools implementation




* Class name: AsyncTool
* Namespace: FutoIn\RI







Methods
-------


### init

    mixed FutoIn\RI\AsyncTool::init(\FutoIn\RI\Details\AsyncToolImpl $impl)

Install Async Tool implementation



* Visibility: **public**
* This method is **static**.


#### Arguments
* $impl **[FutoIn\RI\Details\AsyncToolImpl](#FutoIn-RI-Details-AsyncToolImpl.md)** - &lt;p&gt;AsyncTools implementation&lt;/p&gt;



### callLater

    mixed FutoIn\RI\AsyncTool::callLater(callable $cb, integer $delay_ms)

Schedule $cb for later execution after $delays_ms milliseconds



* Visibility: **public**
* This method is **static**.


#### Arguments
* $cb **callable** - &lt;p&gt;Callable to execute&lt;/p&gt;
* $delay_ms **integer** - &lt;p&gt;Required delay in milliseconds&lt;/p&gt;



### cancelCall

    boolean FutoIn\RI\AsyncTool::cancelCall(mixed $ref)

Cancel previously scheduled $ref item



* Visibility: **public**
* This method is **static**.


#### Arguments
* $ref **mixed** - &lt;p&gt;Any value returned from callLater()&lt;/p&gt;



<a name="FutoIn-RI-AsyncToolTest.md"></a>
FutoIn\RI\AsyncToolTest
===============

Async Tool implementation for testing purposes

Install like \FutoIn\RI\AsyncToolTest::init().

The primary feature is predictive event firing for debugging
and Unit Testing through nextEvent()


* Class name: AsyncToolTest
* Namespace: FutoIn\RI
* Parent class: [FutoIn\RI\Details\AsyncToolImpl](#FutoIn-RI-Details-AsyncToolImpl.md)







Methods
-------


### nextEvent

    mixed FutoIn\RI\AsyncToolTest::nextEvent()

Wait and execute the next item in queue, if any



* Visibility: **public**
* This method is **static**.




### hasEvents

    boolean FutoIn\RI\AsyncToolTest::hasEvents()

Check if any item is scheduled (for unit testing)



* Visibility: **public**
* This method is **static**.




### resetEvents

    mixed FutoIn\RI\AsyncToolTest::resetEvents()

Reset event queue (for unit testing)



* Visibility: **public**
* This method is **static**.




### getEvents

    array FutoIn\RI\AsyncToolTest::getEvents()

Get internal item queue (for unit testing)



* Visibility: **public**
* This method is **static**.




### run

    mixed FutoIn\RI\AsyncToolTest::run()

Run event loop until last event pending



* Visibility: **public**
* This method is **static**.




### __construct

    mixed FutoIn\RI\Details\AsyncToolImpl::__construct()

Any derived class should call this c-tor for future-compatibility



* Visibility: **protected**
* This method is defined by [FutoIn\RI\Details\AsyncToolImpl](#FutoIn-RI-Details-AsyncToolImpl.md)




### init

    mixed FutoIn\RI\Details\AsyncToolImpl::init()

Install Async Tool implementation, call using derived class



* Visibility: **public**
* This method is **static**.
* This method is defined by [FutoIn\RI\Details\AsyncToolImpl](#FutoIn-RI-Details-AsyncToolImpl.md)




### callLater

    mixed FutoIn\RI\Details\AsyncToolImpl::callLater(callable $cb, integer $delay_ms)

Schedule $cb for later execution after $delays_ms milliseconds



* Visibility: **public**
* This method is **abstract**.
* This method is defined by [FutoIn\RI\Details\AsyncToolImpl](#FutoIn-RI-Details-AsyncToolImpl.md)


#### Arguments
* $cb **callable** - &lt;p&gt;Callable to execute&lt;/p&gt;
* $delay_ms **integer** - &lt;p&gt;Required delay in milliseconds&lt;/p&gt;



### cancelCall

    mixed FutoIn\RI\Details\AsyncToolImpl::cancelCall(mixed $ref)

Cancel previously scheduled $ref item



* Visibility: **public**
* This method is **abstract**.
* This method is defined by [FutoIn\RI\Details\AsyncToolImpl](#FutoIn-RI-Details-AsyncToolImpl.md)


#### Arguments
* $ref **mixed** - &lt;p&gt;Any value returned from callLater()&lt;/p&gt;



<a name="FutoIn-RI-Details-AsyncStepsProtection.md"></a>
FutoIn\RI\Details\AsyncStepsProtection
===============

Internal class to organize AsyncSteps levels during execution




* Class name: AsyncStepsProtection
* Namespace: FutoIn\RI\Details
* This class implements: FutoIn\AsyncSteps




Properties
----------


### $root

    private mixed $root





* Visibility: **private**


### $adapter_stack

    private mixed $adapter_stack





* Visibility: **private**


### $_onerror

    public mixed $_onerror





* Visibility: **public**


### $_oncancel

    public mixed $_oncancel





* Visibility: **public**


### $_queue

    public mixed $_queue = null





* Visibility: **public**


### $_limit_event

    public mixed $_limit_event = null





* Visibility: **public**


Methods
-------


### __construct

    mixed FutoIn\RI\Details\AsyncStepsProtection::__construct($root, $adapter_stack)





* Visibility: **public**


#### Arguments
* $root **mixed**
* $adapter_stack **mixed**



### _cleanup

    mixed FutoIn\RI\Details\AsyncStepsProtection::_cleanup()





* Visibility: **public**




### _sanityCheck

    mixed FutoIn\RI\Details\AsyncStepsProtection::_sanityCheck()





* Visibility: **private**




### add

    mixed FutoIn\RI\Details\AsyncStepsProtection::add(callable $func, callable $onerror)





* Visibility: **public**


#### Arguments
* $func **callable**
* $onerror **callable**



### parallel

    mixed FutoIn\RI\Details\AsyncStepsProtection::parallel(callable $onerror)





* Visibility: **public**


#### Arguments
* $onerror **callable**



### state

    mixed FutoIn\RI\Details\AsyncStepsProtection::state()





* Visibility: **public**




### success

    mixed FutoIn\RI\Details\AsyncStepsProtection::success()





* Visibility: **public**




### successStep

    mixed FutoIn\RI\Details\AsyncStepsProtection::successStep()





* Visibility: **public**




### error

    mixed FutoIn\RI\Details\AsyncStepsProtection::error($name, $error_info)





* Visibility: **public**


#### Arguments
* $name **mixed**
* $error_info **mixed**



### setTimeout

    mixed FutoIn\RI\Details\AsyncStepsProtection::setTimeout($timeout_ms)





* Visibility: **public**


#### Arguments
* $timeout_ms **mixed**



### __invoke

    mixed FutoIn\RI\Details\AsyncStepsProtection::__invoke()





* Visibility: **public**




### setCancel

    mixed FutoIn\RI\Details\AsyncStepsProtection::setCancel(callable $oncancel)





* Visibility: **public**


#### Arguments
* $oncancel **callable**



### execute

    mixed FutoIn\RI\Details\AsyncStepsProtection::execute()





* Visibility: **public**




### cancel

    mixed FutoIn\RI\Details\AsyncStepsProtection::cancel()





* Visibility: **public**




### copyFrom

    mixed FutoIn\RI\Details\AsyncStepsProtection::copyFrom(\FutoIn\AsyncSteps $other)





* Visibility: **public**


#### Arguments
* $other **FutoIn\AsyncSteps**



### __clone

    mixed FutoIn\RI\Details\AsyncStepsProtection::__clone()





* Visibility: **public**




### loop

    mixed FutoIn\RI\Details\AsyncStepsProtection::loop(callable $func, string $label)

Execute loop until *as.break()* is called



* Visibility: **public**


#### Arguments
* $func **callable** - &lt;p&gt;loop body callable( as )&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### loopForEach

    mixed FutoIn\RI\Details\AsyncStepsProtection::loopForEach(array $maplist, callable $func, string $label)

For each *map* or *list* element call *func( as, key, value )*



* Visibility: **public**


#### Arguments
* $maplist **array**
* $func **callable** - &lt;p&gt;loop body &lt;em&gt;func( as, key, value )&lt;/em&gt;&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### repeat

    mixed FutoIn\RI\Details\AsyncStepsProtection::repeat(integer $count, callable $func, string $label)

Call *func(as, i)* for *count* times



* Visibility: **public**


#### Arguments
* $count **integer** - &lt;p&gt;how many times to call the &lt;em&gt;func&lt;/em&gt;&lt;/p&gt;
* $func **callable** - &lt;p&gt;loop body &lt;em&gt;func( as, key, value )&lt;/em&gt;&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### breakLoop

    mixed FutoIn\RI\Details\AsyncStepsProtection::breakLoop(string $label)

Break execution of current loop, throws exception



* Visibility: **public**


#### Arguments
* $label **string** - &lt;p&gt;unwind loops, until &lt;em&gt;label&lt;/em&gt; named loop is exited&lt;/p&gt;



### continueLoop

    mixed FutoIn\RI\Details\AsyncStepsProtection::continueLoop(string $label)

Ccontinue loop execution from the next iteration, throws exception



* Visibility: **public**


#### Arguments
* $label **string** - &lt;p&gt;break loops, until &lt;em&gt;label&lt;/em&gt; named loop is found&lt;/p&gt;



### __set

    mixed FutoIn\RI\Details\AsyncStepsProtection::__set(string $name, mixed $value)

state() access through AsyncSteps interface / set value



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;
* $value **mixed** - &lt;p&gt;state variable value&lt;/p&gt;



### __get

    mixed FutoIn\RI\Details\AsyncStepsProtection::__get(string $name)

state() access through AsyncSteps interface / get value



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



### __isset

    boolean FutoIn\RI\Details\AsyncStepsProtection::__isset(string $name)

state() access through AsyncSteps interface / check value exists



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



### __unset

    mixed FutoIn\RI\Details\AsyncStepsProtection::__unset(string $name)

state() access through AsyncSteps interface / delete value



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



<a name="FutoIn-RI-Details-AsyncToolImpl.md"></a>
FutoIn\RI\Details\AsyncToolImpl
===============

Abstract base for AsyncTool implementation




* Class name: AsyncToolImpl
* Namespace: FutoIn\RI\Details
* This is an **abstract** class







Methods
-------


### __construct

    mixed FutoIn\RI\Details\AsyncToolImpl::__construct()

Any derived class should call this c-tor for future-compatibility



* Visibility: **protected**




### init

    mixed FutoIn\RI\Details\AsyncToolImpl::init()

Install Async Tool implementation, call using derived class



* Visibility: **public**
* This method is **static**.




### callLater

    mixed FutoIn\RI\Details\AsyncToolImpl::callLater(callable $cb, integer $delay_ms)

Schedule $cb for later execution after $delays_ms milliseconds



* Visibility: **public**
* This method is **abstract**.


#### Arguments
* $cb **callable** - &lt;p&gt;Callable to execute&lt;/p&gt;
* $delay_ms **integer** - &lt;p&gt;Required delay in milliseconds&lt;/p&gt;



### cancelCall

    mixed FutoIn\RI\Details\AsyncToolImpl::cancelCall(mixed $ref)

Cancel previously scheduled $ref item



* Visibility: **public**
* This method is **abstract**.


#### Arguments
* $ref **mixed** - &lt;p&gt;Any value returned from callLater()&lt;/p&gt;



<a name="FutoIn-RI-Details-ParallelStep.md"></a>
FutoIn\RI\Details\ParallelStep
===============

Internal class to organize Parallel step execution




* Class name: ParallelStep
* Namespace: FutoIn\RI\Details
* This class implements: FutoIn\AsyncSteps




Properties
----------


### $root

    private mixed $root





* Visibility: **private**


### $as

    private mixed $as





* Visibility: **private**


### $parallel_steps

    private mixed $parallel_steps





* Visibility: **private**


### $complete_count

    private mixed $complete_count





* Visibility: **private**


Methods
-------


### __construct

    mixed FutoIn\RI\Details\ParallelStep::__construct($root, $async_iface)





* Visibility: **public**


#### Arguments
* $root **mixed**
* $async_iface **mixed**



### _cleanup

    mixed FutoIn\RI\Details\ParallelStep::_cleanup()





* Visibility: **private**




### add

    mixed FutoIn\RI\Details\ParallelStep::add(callable $func, callable $onerror)





* Visibility: **public**


#### Arguments
* $func **callable**
* $onerror **callable**



### parallel

    mixed FutoIn\RI\Details\ParallelStep::parallel(callable $onerror)





* Visibility: **public**


#### Arguments
* $onerror **callable**



### state

    mixed FutoIn\RI\Details\ParallelStep::state()





* Visibility: **public**




### success

    mixed FutoIn\RI\Details\ParallelStep::success()





* Visibility: **public**




### successStep

    mixed FutoIn\RI\Details\ParallelStep::successStep()





* Visibility: **public**




### error

    mixed FutoIn\RI\Details\ParallelStep::error($name, $errorinfo)





* Visibility: **public**


#### Arguments
* $name **mixed**
* $errorinfo **mixed**



### __invoke

    mixed FutoIn\RI\Details\ParallelStep::__invoke()





* Visibility: **public**




### setTimeout

    mixed FutoIn\RI\Details\ParallelStep::setTimeout($timeout_ms)





* Visibility: **public**


#### Arguments
* $timeout_ms **mixed**



### setCancel

    mixed FutoIn\RI\Details\ParallelStep::setCancel(callable $oncancel)





* Visibility: **public**


#### Arguments
* $oncancel **callable**



### execute

    mixed FutoIn\RI\Details\ParallelStep::execute()





* Visibility: **public**




### copyFrom

    mixed FutoIn\RI\Details\ParallelStep::copyFrom(\FutoIn\AsyncSteps $other)





* Visibility: **public**


#### Arguments
* $other **FutoIn\AsyncSteps**



### __clone

    mixed FutoIn\RI\Details\ParallelStep::__clone()





* Visibility: **public**




### loop

    mixed FutoIn\RI\Details\ParallelStep::loop(callable $func, string $label)

Execute loop until *as.break()* is called



* Visibility: **public**


#### Arguments
* $func **callable** - &lt;p&gt;loop body callable( as )&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### loopForEach

    mixed FutoIn\RI\Details\ParallelStep::loopForEach(array $maplist, callable $func, string $label)

For each *map* or *list* element call *func( as, key, value )*



* Visibility: **public**


#### Arguments
* $maplist **array**
* $func **callable** - &lt;p&gt;loop body &lt;em&gt;func( as, key, value )&lt;/em&gt;&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### repeat

    mixed FutoIn\RI\Details\ParallelStep::repeat(integer $count, callable $func, string $label)

Call *func(as, i)* for *count* times



* Visibility: **public**


#### Arguments
* $count **integer** - &lt;p&gt;how many times to call the &lt;em&gt;func&lt;/em&gt;&lt;/p&gt;
* $func **callable** - &lt;p&gt;loop body &lt;em&gt;func( as, key, value )&lt;/em&gt;&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### breakLoop

    mixed FutoIn\RI\Details\ParallelStep::breakLoop(string $label)

Break execution of current loop, throws exception



* Visibility: **public**


#### Arguments
* $label **string** - &lt;p&gt;unwind loops, until &lt;em&gt;label&lt;/em&gt; named loop is exited&lt;/p&gt;



### continueLoop

    mixed FutoIn\RI\Details\ParallelStep::continueLoop(string $label)

Ccontinue loop execution from the next iteration, throws exception



* Visibility: **public**


#### Arguments
* $label **string** - &lt;p&gt;break loops, until &lt;em&gt;label&lt;/em&gt; named loop is found&lt;/p&gt;



### __set

    mixed FutoIn\RI\Details\ParallelStep::__set(string $name, mixed $value)

state() access through AsyncSteps interface / set value



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;
* $value **mixed** - &lt;p&gt;state variable value&lt;/p&gt;



### __get

    mixed FutoIn\RI\Details\ParallelStep::__get(string $name)

state() access through AsyncSteps interface / get value



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



### __isset

    boolean FutoIn\RI\Details\ParallelStep::__isset(string $name)

state() access through AsyncSteps interface / check value exists



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



### __unset

    mixed FutoIn\RI\Details\ParallelStep::__unset(string $name)

state() access through AsyncSteps interface / delete value



* Visibility: **public**


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



<a name="FutoIn-RI-Details-StateObject.md"></a>
FutoIn\RI\Details\StateObject
===============

Internal class to organize state object.




* Class name: StateObject
* Namespace: FutoIn\RI\Details





Properties
----------


### $error_info

    public mixed $error_info = null





* Visibility: **public**


### $last_exception

    public mixed $last_exception = null





* Visibility: **public**


### $_loop_term_label

    public mixed $_loop_term_label = null





* Visibility: **public**




<a name="FutoIn-RI-ScopedSteps.md"></a>
FutoIn\RI\ScopedSteps
===============

Not standard API. Use for synchronous invocation of AsyncSteps, IF there is no true event loop.

Example:
 $s = new ScopedSteps;
 $s->add( ... )->add( ... );
 $s->run();

\note AsyncTool must be initialized with AsyncToolTest or not initalized at all


* Class name: ScopedSteps
* Namespace: FutoIn\RI
* Parent class: [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)







Methods
-------


### __construct

    mixed FutoIn\RI\AsyncSteps::__construct(object $state)

Init



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $state **object** - &lt;p&gt;for INTERNAL use only&lt;/p&gt;



### run

    mixed FutoIn\RI\ScopedSteps::run()

Execute all steps until nothing is left



* Visibility: **public**




### add

    \FutoIn\AsyncSteps FutoIn\RI\AsyncSteps::add(callable $func, callable $onerror)

Add \$func step executor to end of current AsyncSteps level queue



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $func **callable** - &lt;p&gt;void execute_callback( AsyncSteps as[, previous_success_args] )&lt;/p&gt;
* $onerror **callable** - &lt;p&gt;OPTIONAL: void error_callback( AsyncSteps as, error )&lt;/p&gt;



### copyFrom

    \FutoIn\AsyncSteps FutoIn\RI\AsyncSteps::copyFrom(\FutoIn\RI\AsyncSteps $other)

Copy steps from other AsyncSteps, useful for sub-step cloning



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $other **[FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)** - &lt;p&gt;model AsyncSteps object for re-use&lt;/p&gt;



### parallel

    \FutoIn\AsyncSteps FutoIn\RI\AsyncSteps::parallel(callable $onerror)

Create special object to queue steps for execution in parallel



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $onerror **callable** - &lt;p&gt;OPTIONAL: void error_callback( AsyncSteps as, error )&lt;/p&gt;



### state

    \StdClass FutoIn\RI\AsyncSteps::state()

Access AsyncSteps state object



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)




### success

    mixed FutoIn\RI\AsyncSteps::success()

Set "success" state of current step execution



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)




### successStep

    mixed FutoIn\RI\AsyncSteps::successStep()

Call success() or add efficient dummy step equal to as.success() in behavior
(depending on presence of other sub-steps)



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)




### error

    mixed FutoIn\RI\AsyncSteps::error(string $name, string $error_info)

Set "error" state of current step execution



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $name **string** - &lt;p&gt;Type of error&lt;/p&gt;
* $error_info **string** - &lt;p&gt;Error description to be put into &quot;error_info&quot; state field&lt;/p&gt;



### setTimeout

    mixed FutoIn\RI\AsyncSteps::setTimeout(integer $timeout_ms)

Delay further execution until as.success() or as.error() is called



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $timeout_ms **integer** - &lt;p&gt;Timeout in milliseconds&lt;/p&gt;



### __invoke

    mixed FutoIn\RI\AsyncSteps::__invoke()

PHP-specific alias for success()



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)




### setCancel

    mixed FutoIn\RI\AsyncSteps::setCancel($oncancel)

Set cancellation callback



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $oncancel **mixed** - &lt;p&gt;void cancel_callback( AsyncSteps as )
\note Please see the specification&lt;/p&gt;



### execute

    mixed FutoIn\RI\AsyncSteps::execute()

Start execution of AsyncSteps

It can be called only on root instance of AsyncSteps

* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)




### cancel

    mixed FutoIn\RI\AsyncSteps::cancel()

Cancel execution of AsyncSteps

It can be called only on root instance of AsyncSteps

* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)




### loop

    mixed FutoIn\RI\AsyncSteps::loop(callable $func, string $label)

Execute loop until *as.break()* is called



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $func **callable** - &lt;p&gt;loop body callable( as )&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### loopForEach

    mixed FutoIn\RI\AsyncSteps::loopForEach(array $maplist, callable $func, string $label)

For each *map* or *list* element call *func( as, key, value )*



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $maplist **array**
* $func **callable** - &lt;p&gt;loop body &lt;em&gt;func( as, key, value )&lt;/em&gt;&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### repeat

    mixed FutoIn\RI\AsyncSteps::repeat(integer $count, callable $func, string $label)

Call *func(as, i)* for *count* times



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $count **integer** - &lt;p&gt;how many times to call the &lt;em&gt;func&lt;/em&gt;&lt;/p&gt;
* $func **callable** - &lt;p&gt;loop body &lt;em&gt;func( as, key, value )&lt;/em&gt;&lt;/p&gt;
* $label **string** - &lt;p&gt;optional label to use for &lt;em&gt;as.break()&lt;/em&gt; and &lt;em&gt;as.continue()&lt;/em&gt; in inner loops&lt;/p&gt;



### breakLoop

    mixed FutoIn\RI\AsyncSteps::breakLoop(string $label)

Break execution of current loop, throws exception



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $label **string** - &lt;p&gt;unwind loops, until &lt;em&gt;label&lt;/em&gt; named loop is exited&lt;/p&gt;



### continueLoop

    mixed FutoIn\RI\AsyncSteps::continueLoop(string $label)

Ccontinue loop execution from the next iteration, throws exception



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $label **string** - &lt;p&gt;break loops, until &lt;em&gt;label&lt;/em&gt; named loop is found&lt;/p&gt;



### __set

    mixed FutoIn\RI\AsyncSteps::__set(string $name, mixed $value)

state() access through AsyncSteps interface / set value



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;
* $value **mixed** - &lt;p&gt;state variable value&lt;/p&gt;



### __get

    mixed FutoIn\RI\AsyncSteps::__get(string $name)

state() access through AsyncSteps interface / get value



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



### __isset

    boolean FutoIn\RI\AsyncSteps::__isset(string $name)

state() access through AsyncSteps interface / check value exists



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;



### __unset

    mixed FutoIn\RI\AsyncSteps::__unset(string $name)

state() access through AsyncSteps interface / delete value



* Visibility: **public**
* This method is defined by [FutoIn\RI\AsyncSteps](#FutoIn-RI-AsyncSteps.md)


#### Arguments
* $name **string** - &lt;p&gt;state variable name&lt;/p&gt;


