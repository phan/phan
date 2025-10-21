#Exception Handling

##General

An *exception* is some unusual condition in that it is outside the
ordinary expected behavior. Examples include dealing with situations in
which a critical resource is needed, but is unavailable, and detecting
an out-of-range value for some computation. As such, exceptions require
special handling. This chapter describes how exceptions can be created
and handled.

Whenever some exceptional condition is detected at runtime, an exception
is *thrown*. A designated exception handler can *catch* the thrown
exception and service it. Among other things, the handler might recover
from the situation completely (allowing the script to continue
execution), it might perform some recovery and then throw an exception
to get further help, or it might perform some cleanup action and
terminate the script. Exceptions may be thrown on behalf of the Engine
or by explicit code source code in the script.

Exception handling involves the use of the following keywords:

-   [`try`](11-statements.md#the-try-statement), which allows a *try-block* of code containing one or
    more possible exception generations, to be tried.
-   [`catch`](11-statements.md#the-try-statement), which defines a handler for a specific type of
    exception thrown from the corresponding try-block or from some
    function it calls.
-   [`finally`](11-statements.md#the-try-statement), which allows the *finally-block* of a try-block to
    be executed (to perform some cleanup, for example), whether or not
    an exception occurred within that try-block.
-   [`throw`](11-statements.md#the-throw-statement), which generates an exception of a given type, from
    a place called a *throw point*.

When an exception is thrown, an *exception object* of type [`Exception`](#class-exception),
or of a subclass of that type, is created and made available to
the first catch-handler that can catch it. Among other things, the
exception object contains an *exception message* and an *exception
code*, both of which can be used by a handler to determine how to handle
the situation.

PHP errors also can be translated to exceptions via the class
[`ErrorException`](http://php.net/manual/class.errorexception.php)
(which is not part of this specification).

##Class `Exception`

Class `Exception` is the base class of all exception types. This class is
defined, as follows:

```PHP
class Exception implements Throwable
{
  protected $message = 'Unknown exception';
  protected $code = 0;
  protected $file;
  protected $line;

  public function __construct($message = "", $code = 0,
               Throwable $previous = NULL);

  final private function __clone();
}
```

For information about exception trace-back and nested exceptions, see [tracing exceptions](#tracing-exceptions).

For information about the base interface, see [Throwable](15-interfaces.md#interface-throwable).
Note that the methods from Throwable are implemented as `final` in the Exception class, which means
the extending class can not override them.

The class members are defined below:

Name  | Purpose
----    | -------
`$code` | `int`; the exception code (as provided by the constructor)
`$file` | `string`; the name of the script where the exception was generated
`$line` | `int`; the source line number in the script where the exception was generated
`$message`  | `string`; the exception message (as provided by the constructor)
`__construct` | Takes three (optional) arguments – `string`: the exception message (defaults to ""), `int`: the exception code (defaults to 0), and `Exception`: the previous exception in the chain (defaults to `NULL`)
`__clone` | Present to inhibit the cloning of exception objects

##Tracing Exceptions

When an exception is caught, the `get*` functions in class `Exception`
provide useful information. If one or more nested function calls were
involved to get to the place where the exception was generated, a record
of those calls is also retained, and made available by `getTrace, through
what is referred to as the *function stack trace*, or simply, `*trace*`.

Let's refer to the top level of a script as *function-level* 0.
Function-level 1 is inside any function called from function-level 0.
Function-level 2 is inside any function called from function-level 1,
and so on. The method `getTrace` returns an array. Exceptions
generated at function-level 0 involve no function call, in which case,
the array returned by `getTrace` is empty.

Each element of the array returned by `getTrace` provides information
about a given function level. Let us call this array *trace-array* and
the number of elements in this array *call-level*. The key for each of
trace-array's elements has type int, and ranges from 0 to
call-level - 1. For example, when a top-level script calls function `f1`,
which calls function `f2`, which calls function `f3`, which then generates
an exception, there are four function levels, 0–3, and there are three
lots of trace information, one per call level. That is, trace-array
contains three elements, and they each correspond to the reverse order
of the function calls. For example, `trace-array[0]` is for the call to
function `f3`, `trace-array[1]` is for the call to function `f2`, and
`trace-array[2]` is for the call to function `f1`.

Each element in trace-array is itself an array that contains elements
with the following key/value pairs:

Key | Value Type  | Value
--- | ----------    | -----
"args"  | `array` | The set of arguments passed to the function
"class" | `string` |  The name of the function's parent class
"file"  | `string` |  The name of the script where the function was called
"function"  | `string` |  The name of the function or class method
"line"  | `int` | The line number in the source where the function was called
"object" |  `object` | The current object
"type"  | `string` |  Type of call; `->` for an instance method call, `::` for a static method call, for ordinary function call, empty string(`""`) is returned.

The key `args` has a value that is yet another array, which we shall
call *argument-array*. That array contains a set of values that
corresponds directly to the set of values passed as arguments to the
corresponding function. Regarding element order, `argument-array[0]`
corresponds to the left-most argument, `argument-array[1]` corresponds to
the next argument to the right, and so on.

Note that only the actual arguments passed to the function are reported.
Consider the case in which a function has a default argument value
defined for a parameter. If that function is called without an argument
for the parameter having the default value, no corresponding argument
exists in the argument array. Only arguments present at the function-call
site have their values recorded in array-argument.

See also, library functions [`debug_backtrace`](http://www.php.net/debug_backtrace) and
[`debug_print_backtrace`](http://www.php.net/debug_print_backtrace).

##User-Defined Exception Classes

An exception class is defined simply by having it extend class [`Exception`](#class-exception).
However, as that class's `__clone` method is declared [`final`](14-classes.md#methods),
exception objects cannot be cloned.

When an exception class is defined, typically, its constructors call the
parent class' constructor as their first operation to ensure the
base-class part of the new object is initialized appropriately. They
often also provide an augmented implementation of
[`__toString()`](14-classes.md#method-__tostring).


