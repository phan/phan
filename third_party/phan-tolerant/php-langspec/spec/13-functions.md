#Functions

##General

When a function is called, information may be passed to it by the caller
via an *argument list*, which contains one or more *argument
expressions*, or more simply, *arguments*. These correspond by position
to the *parameters* in a *parameter list* in the called [function's
definition](#function-definitions).

An *unconditionally defined function* is a function whose definition is
at the top level of a script. A *conditionally defined function* is a
function whose definition occurs inside a compound statement,
such as the body of another function (a *nested function*), conditional statement, etc.
There is no limit on the depth of levels of function nesting. Consider the
case of an *outer function*, and an *inner function* defined within it.
Until the outer function is called at least once, its inner function
does not exist. Even if the outer function is called, if its runtime logic
bypasses the definition of the inner function, that inner function still
does not exist. The conditionally defined function comes into existance when
the execution flow reaches the point where the function is defined.

Any function containing [`yield`](10-expressions.md#yield-operator) is a *generator function*.

**Examples**

```PHP
ucf1(); // can call ucf1 before its definition is seen
function ucf1() { ... }
ucf1(); // can call ucf1 after its definition is seen
cf1(); // Error; call to non-existent function
$flag = TRUE;
if ($flag) { function cf1() { ... } } // cf1 now exists
if ($flag) { cf1(); } // can call cf1 now
// -----------------------------------------
function ucf2() { function cf2() { ... } }
cf2(); // Error; call to non-existent function
ucf2(); // now cf2 exists
cf2(); // so we can call it
```

##Function Calls

A function is called via the function-call operator [`()`](10-expressions.md#function-call-operator).

##Function Definitions

**Syntax**

<pre>
  <i>function-definition:</i>
    <i>function-definition-header   compound-statement</i>

  <i>function-definition-header:</i>
    function  &<i><sub>opt</sub></i>   <i>name</i>  (  <i>parameter-declaration-list<sub>opt</sub></i>  )  <i>return-type<sub>opt</sub></i>

  <i>parameter-declaration-list:</i>
    <i>simple-parameter-declaration-list</i>
    <i>variadic-declaration-list</i>

  <i>simple-parameter-declaration-list:</i>
    <i>parameter-declaration</i>
    <i>parameter-declaration-list</i>  ,  <i>parameter-declaration</i>

  <i>variadic-declaration-list:</i>
    <i>simple-parameter-declaration-list</i>  ,  <i>variadic-parameter</i>
    <i>variadic-parameter</i>

  <i>parameter-declaration:</i>
    <i>type-declaration<sub>opt</sub></i>  &<i><sub>opt</sub></i>  <i>variable-name   default-argument-specifier<sub>opt</sub></i>

  <i>variadic-parameter:</i>
	<i>type-declaration<sub>opt</sub></i>  &<i><sub>opt</sub></i>  ...  <i>variable-name</i>

  <i>return-type:</i>
    : <i>type-declaration</i>
    : void

  <i>type-declaration:</i>
    array
    callable
    <i>scalar-type</i>
    <i>qualified-name</i>

  <i>scalar-type:</i>
    bool
    float
    int
    string

  <i>default-argument-specifier:</i>
    =  <i>constant-expression</i>
</pre>

**Defined elsewhere**

* [*constant-expression*](10-expressions.md#constant-expressions)
* [*qualified-name*](09-lexical-structure.md#names)

**Constraints**

Each parameter name in a *function-definition* must be distinct.

A [conditionally defined function](#general) must exist before any calls are
made to that function.

The *function-definition* for constructors, destructors, and clone methods must not contain *return-type*.

For generator functions, if the the return type is specified, it can only be one of:
`Generator`, `Iterator` or `Traversable`.

**Semantics**

A *function-definition* defines a function called *name*. Function names
are **not** case-sensitive. A function can be defined with zero or more
parameters, each of which is specified in its own
*parameter-declaration* in a *parameter-declaration-list*. Each
parameter has a name, *variable-name*, and optionally, a
*default-argument-specifier*. An `&` in *parameter-declaration* indicates
that parameter is passed [byRef](04-basic-concepts.md#assignment) rather than by value. An `&`
before *name* indicates that the value returned from this function is to
be returned byRef. Returning values is described in [`return` statement description](11-statements.md#the-return-statement).

When the function is called, if there exists a parameter for which there
is a corresponding argument, the argument is assigned to the parameter
variable using value assignment, while for passing byRef, the argument is
[assigned](04-basic-concepts.md#argument-passing) to the parameter variable using [byRef assignment](04-basic-concepts.md#assignment).
If that parameter has no corresponding argument, but the parameter has a
default argument value, for passing by value or byRef, the default
value is assigned to the parameter variable using value assignment.
Otherwise, if the parameter has no corresponding argument and the parameter
does not have a default value, the parameter variable is non-existent and no corresponding
[VSlot](04-basic-concepts.md#the-memory-model) exists.  After all possible parameters have been
assigned initial values or aliased to arguments, the body of the function,
*compound-statement*, is executed. This execution may terminate [normally](04-basic-concepts.md#program-termination),
with [`return` statement](11-statements.md#the-return-statement) or [abnormally](04-basic-concepts.md#program-termination).

Each parameter is a variable local to the parent function, and is a
modifiable lvalue.

A *function-definition* may exist at the top level of a script, inside
any *compound-statement*, in which case, the function is [conditionally
defined](#general), or inside a [*method-declaration* section of a class](14-classes.md#methods).

If *variadic-parameter* is defined, every parameter that is supplied to function and is not matched
by the preceding parameters is stored in this parameter, as an array element.
The first such parameter gets index 0, the next one 1, etc. If no extra parameters is supplied,
the value of the parameter is an empty array.
Note that if type and/or byRef specifications are supplied to variadic parameter, they apply
to every extra parameter captured by it.

By default, a parameter will accept an argument of any type. However, by
specifying a *type-declaration*, the types of argument accepted can be
restricted. By specifying `array`, only an argument of the `array`
type is accepted. By specifying `callable`, only an argument designating a
function (see below) is accepted. By specifying *qualified-name*, only an instance
of a class having that type, or being derived from that type, are
accepted, or only an instance of a class that implements that interface
type directly or indirectly is accepted. The check is the same as for [`instanceof` operator](10-expressions.md#instanceof-operator).

`callable` pseudo-type accepts the following:
* A string value containing the name of a function defined at the moment of the call.
* An array value having two elements under indexes `0` and `1`. First element can be either string or object.
If the first element is a string, the second element must be a string naming a method in a class designated by the first element.
If the first element is an object, the second element must be a string naming a method
that can be called on an object designated by the first element, from the context of the function being called.
* An instance of the [`Closure`](14-classes.md#class-closure) class.
* An instance of a class implementing [`__invoke`](14-classes.md#method-__invoke).

The library function [`is_callable`](http://php.net/is_callable) reports whether the contents of
a variable can be called as a function.

Parameters typed with *scalar-type* are accepted if they pass the type check for this [scalar type](05-types.md#scalar-types),
as [described below](#type-check-modes). Once the checks have been passed, the parameter types are always of the scalar type
specified (or `NULL` if `NULL` is allowed).

If a parameter has a type declaration, `NULL` is not accepted unless it has a default value that evaluates to `NULL`.

The default value for a typed parameter must be of the type specified, or `NULL`,
and conversion is not be performed for defaults, regardless of the mode.

## Return typing

If the function is defined with *return-type* declaration, the value returned by the function should
be compatible with the defined type, using the same rules as for parameter type checks. `NULL` values
are not allowed for typed returns. If the value of the [`return` statement](11-statements.md#the-return-statement)
does not pass the type check, a fatal error is produced.

The `void` type is a special type that can only be used as a return type, and
not in other contexts. It has no effect at runtime, see the [`return` statement](11-statements.md#the-return-statement).

## Type check modes

The type checking can be performed in two modes, strict and coercive (default).
The difference between modes exists only for scalar typed parameters (`int`, `float`, `string` and `bool`).

For coercive mode, if the value passed is of the same type as the parameter, it is accepted.
If not, the [conversion](08-conversions.md#general) is attempted. If the conversion succeeds,
the converted value is the value assigned to the parameter. If the conversion fails, a fatal error
is produced.

For strict mode, the parameter must be exactly of the type that is declared (e.g., string `"1"` is not
accepted as a value for parameter typed as `int`). The only exception is that `int` values will be accepted
for `float` typed parameter and [converted to `float`](08-conversions.md#converting-to-floating-point-type).
Note that the strict mode applies not only to user-defined but also to internal functions,
please consult [the manual](http://php.net/manual/) for appropriate parameter types. If the types do not match,
an exception of type `TypeError` is thrown.

Note that if the parameter is passed byRef, and conversion happened,
then the value will be re-assigned with the newly converted value.

The mode is set by the [`declare` statement](11-statements.md#the-declare-statement).

Note that the type check mode is for the function call controleed by the caller, not the callee.
While the check is performed in the function being called, the caller defines whether the check is strict.
Same function can be called with both strict and coercive mode checks from different contexts.

The check for the return type is always defined by the script that the function was defined in.

**Examples**

```PHP
// coercive mode by default
function accept_int(int $a) { return $a+1; }
accept_int(1); // ok
accept_int("123"); // ok
accept_int("123.34"); // ok
accept_int("123.34 and some"); // ok + notice
accept_int("not 123"); // fatal error!
accept_int(null); // fatal error

function accept_int_or_not(int $a = null) { return $a+1; }
accept_int_or_not(null); // ok

function convert_int(int &$a) { return $a+1; }
$a = "12";
convert_int($a);
var_dump($a); // $a is now int

// Now in strict mode
declare(strict_types=1);
function accept_int(int $a) { return $a+1; }
function accept_float(float $a) { return $a+1; }
accept_int(1); // ok
accept_float(1); // ok
accept_int(1.5); // fatal error
accept_int("123"); // fatal error
echo substr("123", "1"); // fatal error

```

##Variable Functions

If a variable name is followed by the function-call operator [`()`](10-expressions.md#function-call-operator),
and the value of that variable designates the function currently defined and visible (see description above),
that function will be executed. If the variable does not designate a function or this function can not be called,
a fatal error is produced.

##Anonymous Functions

An *anonymous function*, also known as a *closure*, is a function
defined with no name. As such, it must be defined in the context of an
expression whose value is used immediately to call that function, or
that is saved in a variable for later execution. An anonymous function
is defined via the [anonymous function creation operator](10-expressions.md#anonymous-function-creation).

For both [`__FUNCTION__` and `__METHOD__`](06-constants.md#context-dependent-constants), an anonymous
function's name is reported as `{closure}`.
