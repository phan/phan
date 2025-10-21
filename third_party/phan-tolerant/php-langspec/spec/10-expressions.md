#Expressions

##General

An *expression* involves one or more terms and zero or more operators.

A *full expression* is an expression that is not part of another
expression.

A *side effect* is an action that changes the state of the execution
environment. (Examples of such actions are modifying a variable, writing
to a device or file, or calling a function that performs such
operations).

When an expression is evaluated, it produces a result. It might also
produce a side effect. Only a few operators produce side effects. (For
example, given the [expression statement](11-statements.md#expression-statements) `$v = 10`; the
expression 10 is evaluated to the result 10, and there is no side
effect. Then the assignment operator is executed, which results in the
side effect of `$v` being modified. The result of the whole expression is
the value of `$v` after the assignment has taken place. However, that
result is never used. Similarly, given the expression statement `++$v`;
the expression is evaluated to the result incremented-value-of-`$v`, and
the side effect is that `$v` is actually incremented. Again, the result
is never used).

The occurrence of value computation and side effects is delimited by
*sequence points*, places in a program's execution at which all the
computations and side effects previously promised are complete, and no
computations or side effects of future operations have yet begun. There
is a sequence point at the end of each full expression. The [logical and](#logical-and-operator-form-1),
[logical or](#logical-inclusive-or-operator-form-1),
[conditional](#logical-inclusive-or-operator-form-1), [coalesce](#coalesce-operator) and [function call](#function-call-operator)
operators each contain a sequence point. (For example, in the
following series of expression statements, `$a = 10; ++$a; $b = $a;`,
there is sequence point at the end of each full expression, so the
assignment to $a is completed before `$a` is incremented, and the
increment is completed before the assignment to `$b`).

When an expression contains multiple operators, the *precedence* of
those operators controls the order in which those operators are applied.
(For example, the expression `$a - $b / $c` is evaluated as
`$a - ($b / $c)` because the / operator has higher precedence than the
binary - operator). The precedence of an operator is determined by the
definition of its associated grammar production.

If an operand occurs between two operators having the same precedence,
the order in which the operations are performed is determined by those
operators' *associativity*. With *left-associative* operators,
operations are performed left-to-right. (For example, `$a + $b - $c` is
evaluated as `($a + $b) - $c`). With *right-associative* operators,
operations are performed right-to-left. (For example, `$a = $b = $c` is
evaluated as `$a = ($b = $c)`).

Precedence and associativity can be controlled using *grouping
parentheses*. (For example, in the expression `($a - $b) / $c`, the
subtraction is done before the division. Without the grouping
parentheses, the division would take place first).

While precedence, associativity, and grouping parentheses control the
order in which operators are applied, they do *not* control the order of
evaluation of the terms themselves. Unless stated explicitly in this
specification, the order in which the operands in an expression are
evaluated relative to each other is unspecified. See the discussion
above about the operators that contain sequence points. (For example, in
the full expression `$list1[$i] = $list2[$i++]`, whether the value
of `$i` on the left-hand side is the old or new `$i`, is unspecified.
Similarly, in the full expression `$j = $i + $i++`, whether the value
of `$i` is the old or new `$i`, is unspecified. Finally, in the full
expression `f() + g() * h()`, the order in which the three functions are
called, is unspecified).

**Implementation Notes**

An expression that contains no side effects and whose resulting value is
not used need not be evaluated. For example, the expression statements
`6;, $i + 6;`, and `$i/$j`; are well formed, but they contain no side
effects and their results are not used.

A side effect need not be executed if it can be determined that no other
program code relies on its having happened. (For example, in the cases
of `return $a++;` and `return ++$a;`, it is obvious what value must be
returned in each case, but if `$a` is a variable local to the enclosing
function, `$a` need not actually be incremented.

##Primary Expressions

###General

**Syntax**

<pre>
  <i>primary-expression:</i>
    <i>variable-name</i>
    <i>qualified-name</i>
    <i>literal</i>
    <i>constant-expression</i>
    <i>intrinsic</i>
    <i>anonymous-function-creation-expression</i>
    (  <i>expression</i>  )
    $this
</pre>

**Defined elsewhere**

* [*variable-name*](09-lexical-structure.md#names)
* [*qualified-name*](09-lexical-structure.md#names)
* [*literal*](#literals)
* [*constant-expression*](#constant-expressions)
* [*intrinsic*](#general-2)
* [*anonymous-function-creation-expression*](#anonymous-function-creation)
* [*expression*](#script-inclusion-operators)

**Semantics**

The type and value of parenthesized expression are identical to those of
the un-parenthesized expression.

The variable `$this` is predefined inside any non-static instance method (including
constructor) when that method is called from within an object
context. The value of `$this` is the calling object or the object being constructed.

###Literals

**Syntax**

<pre>
  <i>literal:</i>
    <i>integer-literal</i>
    <i>floating-literal</i>
    <i>string-literal</i>
</pre>

**Defined elsewhere**

* [*integer-literal*](09-lexical-structure.md#integer-literals)
* [*floating-literal*](09-lexical-structure.md#floating-point-literals)
* [*string-literal*](09-lexical-structure.md#string-literals)

**Semantics**

A literal evaluates to its value, as specified in the lexical specification for
[literals](09-lexical-structure.md#literals).

###Intrinsics

####General

**Syntax**
<pre>
  <i>intrinsic:</i>
    <i>intrisic-construct</i>
    <i>intrisic-operator</i>

  <i>intrisic-construct:</i>
    <i>echo-intrinsic</i>
    <i>list-intrinsic</i>
    <i>unset-intrinsic</i>

  <i>intrinsic-operator:</i>
    <i>array-intrinsic</i>
    <i>empty-intrinsic</i>
    <i>eval-intrinsic</i>
    <i>exit-intrinsic</i>
    <i>isset-intrinsic</i>
    <i>print-intrinsic</i>
</pre>

**Defined elsewhere**

* [*array-intrinsic*](#array)
* [*echo-intrinsic*](#echo)
* [*empty-intrinsic*](#empty)
* [*eval-intrinsic*](#eval)
* [*exit-intrinsic*](#exitdie)
* [*isset-intrinsic*](#isset)
* [*list-intrinsic*](#list)
* [*print-intrinsic*](#print)
* [*unset-intrinsic*](#unset)

**Semantics**

The names in this series of sections have special meaning and are
called *intrinsics*, but they are not keywords; nor are they functions, they
are language constructs that are interpreted by the Engine.

*intrinsic-operator* can be used as part of an expression, in any place
other values or expressions could be used.

*intrisic-construct* can be used only as stand-alone [statement](11-statements.md#statements).

####array

**Syntax**

<pre>
  <i>array-intrinsic:</i>
    array ( <i>array-initializer<sub>opt</sub></i>  )
</pre>

**Defined elsewhere**

* [*array-initializer*](#array-creation-operator)

**Semantics**

This intrinsic creates and initializes an array. It is equivalent to the
array-creation operator [`[]`](#array-creation-operator).

####echo

**Syntax**

<pre>
  <i>echo-intrinsic:</i>
    echo  <i>expression</i>
    echo  <i>expression-list-two-or-more</i>

  <i>expression-list-two-or-more:</i>
    <i>expression</i>  ,  <i>expression</i>
    <i>expression-list-two-or-more</i>  ,  <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

*expression* value must be [convertable to a string](08-conversions.md#converting-to-string-type).
In particular, it should not be an array and if it is an object, it must implement
a [`__toString` method](14-classes.md#method-__tostring).

**Semantics**

After converting each of its *expression*s' values to strings, if
necessary, `echo` concatenates them in order given, and writes the
resulting string to [`STDOUT`](06-constants.md#core-predefined-constants). Unlike [`print`](#print), it does
not produce a result.

See also: [double quioted strings](09-lexical-structure.md#double-quoted-string-literals) and
[heredoc documents](09-lexical-structure.md#heredoc-string-literals), [conversion to string](08-conversions.md#converting-to-string-type).

**Examples**

```PHP
$v1 = TRUE;
$v2 = 123;
echo  '>>' . $v1 . '|' . $v2 . "<<\n";    // outputs ">>1|123<<"
echo  '>>' , $v1 , '|' , $v2 , "<<\n";    // outputs ">>1|123<<"
echo ('>>' . $v1 . '|' . $v2 . "<<\n");   // outputs ">>1|123<<"
$v3 = "qqq{$v2}zzz";
echo "$v3\n";
```

####empty

**Syntax**

<pre>
  <i>empty-intrinsic:</i>
    empty ( <i>expression</i>  )
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Semantics**

This intrinsic returns `TRUE` if the variable or value designated by
*expression* is empty, where *empty* means that the variable designated by it does not
exist, or it exists and its value compares equal to `FALSE`. Otherwise,
the intrinsic returns `FALSE`.

The following values are considered empty: `FALSE`, `0`, `0.0`, `""` (empty string), `"0"`, `NULL`,
an empty array, and any uninitialized variable.

If this intrinsic is used with an expression that designates a [dynamic
property](14-classes.md#dynamic-members), then if the class of that property has
an [`__isset`](14-classes.md#method-__isset), that method is called.
If that method returns `TRUE`, the value of the property is retrieved
(which may call [__get](4-classes.md#method-__get) if defined) and compared
to `FALSE` as described above. Otherwise, the result is `FALSE`.

**Examples**

```PHP
empty("0");  // results in TRUE
empty("00"); // results in FALSE
$v = [10, 20];
empty($v);   // results in FALSE
```

####eval

**Syntax**

<pre>
  <i>eval-intrinsic:</i>
    eval (  <i>expression</i>  )
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

*expression* must designate a string, or be [convertable to a string](08-conversions.md#converting-to-string-type).
The contents of the string must be valid PHP source code. If the source code is ill formed, an exception of type `ParseError` is thrown.

The PHP source code in the string must not be delimited by opening and
closing [PHP tags](04-basic-concepts.md#program-structure). However, the source code
itself may contain the tags.

**Semantics**

This intrinsic evaluates the contents of the string designated by
*expression*, as PHP script code.

Execution of a [`return` statement](11-statements.md#the-return-statement) from within the source code
terminates the execution, and the value returned becomes the value
returned by `eval`. If the source code is ill formed, `eval` returns `FALSE`;
otherwise, `eval` returns `NULL`.

The source code is executed in the scope of that from which `eval` is
called.

**Examples**

```PHP
$str = "Hello";
eval("echo \$str . \"\\n\";");  // → echo $str . "\n"; → prints Hello
```

####exit/die

**Syntax**

<pre>
  <i>exit-intrinsic:</i>
    exit  <i>expression<sub>opt</sub></i>
    exit  (  <i>expression<sub>opt</sub></i>  )
    die   <i>expression<sub>opt</sub></i>
    die   (   <i>expression<sub>opt</sub></i> )
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

When *expression* designates an integer, its value must be in the range
0–254.

**Semantics**

`exit` and `die` are equivalent.

This intrinsic terminates the current script. If *expression* designates
a string, that string is written to [`STDOUT`](06-constants.md#core-predefined-constants). If *expression*
designates an integer, that represents the script's *exit status code*.
Code 255 is reserved by PHP. Code 0 represents "success". The exit
status code is made available to the execution environment. If
*expression* is omitted or is a string, the exit status code is zero.
`exit` does not have a resulting value.

`exit` performs the following operations, in order:

-   Writes the optional string to [`STDOUT`](06-constants.md#core-predefined-constants).
-   Calls any functions registered via the library function
    [`register_shutdown_function`](http://www.php.net/register_shutdown_function) in their order of registration.
-   Invokes [destructors](14-classes.md#destructors) for all remaining instances.

**Examples**

```PHP
exit ("Closing down");
exit (1);
exit;
```

####isset

**Syntax**

<pre>
  <i>isset-intrinsic:</i>
    isset  (  <i>expression-list-one-or-more</i>  )

  <i>expression-list-one-or-more</i>:
    <i>expression</i>
    <i>expression-list-one-or-more</i>  ,  <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

Each *expression* must designate a variable.

**Semantics**

This intrinsic returns `TRUE` if all the variables designated by
*expression*s are set and their values are not `NULL`. Otherwise, it
returns `FALSE`.

If this intrinsic is used with an expression that designate a [dynamic
property](14-classes.md#dynamic-members), then if the class of that property has
an [`__isset`](14-classes.md#method-__isset), that method is called.
If that method returns `TRUE`, the value of the property is retrieved
(which may call [`__get`](4-classes.md#method-__get) if defined) and
if it is not `NULL`, the result is `TRUE`. Otherwise, the result is `FALSE`.

**Examples**

```PHP
$v = TRUE;
isset($v);     // results in TRUE
$v = NULL;
isset($v);     // results in FALSE
$v1 = TRUE; $v2 = 12.3; $v3 = NULL;
isset($v1, $v2, $v3);  // results in FALSE
```

####list

**Syntax**

<pre>
  <i>list-intrinsic:</i>
    list  (  <i>list-expression-list<sub>opt</sub></i>  )

  <i>list-expression-list:</i>
    <i>unkeyed-list-expression-list</i>
    <i>keyed-list-expression-list</i> ,<sub>opt</sub>

  <i>unkeyed-list-expression-list:</i>
    <i>list-or-variable</i>
    ,
    <i>unkeyed-list-expression-list</i>  ,  <i>list-or-variable<sub>opt</sub></ii>

  <i>keyed-list-expression-list:</i>
    <i>expression</i>  =>  <i>list-or-variable</i>
    <i>keyed-list-expression-list</i>  ,  <i>expression</i>  =>  <i>list-or-variable</i>

  <i>list-or-variable:</i>
    <i>list-intrinsic</i>
    <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

*list-intrinsic* must be used as the left-hand operand in a
[*simple-assignment-expression*](#simple-assignment) of which the right-hand
operand must be an expression that designates an array or object implementing
the `ArrayAccess` interface (called the *source array*).

Each *expression* in *list-or-variable* must designate a variable (called
the *target variable*).

At least one of the elements of the *list-expression-list* must be non-empty.

**Semantics**

This intrinsic assigns one or more elements of the source array to the
target variables. On success, it returns a copy of the source array. If the
source array is not an array or object implementing `ArrayAccess` no
assignments are performed and the return value is `NULL`.

For *unkeyed-list-expression-list*, all elements in the source array having
keys of type `string` are ignored.
The element having an `int` key of 0 is assigned to the first target
variable, the element having an `int` key of 1 is assigned to the second
target variable, and so on, until all target variables have been
assigned. Any other array elements are ignored. If there are
fewer source array elements having int keys than there are target
variables, the unassigned target variables are set to `NULL` and
a non-fatal error is produced.

For *keyed-list-expression-list*, each key-variable pair is handled in turn,
with the key and variable being separated by the `=>` symbol.
The element having the first key, with the key having been converted using the
same rules as the [subscript operator](10-expressions.md#subscript-operator),
is assigned to the frst target variable. This process is repeated for the
second `=>` pair, if any, and so on. Any other array elements are ignored.
If there is no array element with a given key, the unassigned target variable
is set to `NULL` and a non-fatal error is produced.

The assignments must occur in this order.

Any target variable may be a list, in which case, the corresponding
element is expected to be an array.

If the source array elements and the target variables overlap in any
way, the behavior is unspecified.

**Examples**

```PHP
list($min, $max, $avg) = array(0, 100, 67);
  // $min is 0, $max is 100, $avg is 67
list($min, $max, $avg) = array(2 => 67, 1 => 100, 0 => 0);
  // same as example above
list($min, , $avg) = array(0, 100, 67);
  // $min is 0, $avg is 67
list($min, $max, $avg) = array(0, 2 => 100, 4 => 67);
  // $min is 0, $max is NULL, $avg is 100
list($min, list($max, $avg)) = [0, [1 => 67, 99, 0 => 100], 33];
  // $min is 0, $max is 100, $avg is 67

list($arr[1], $arr[0]) = [0, 1];
  // $arr is [1 => 0, 0 => 1], in this order
list($arr2[], $arr2[]) = [0, 1];
  // $arr2 is [0, 1]

list("one" => $one, "two" => $two) = ["one" => 1, "two" => 2];
  // $one is 1, $two is 2
list(
    "one" => $one,
    "two" => $two,
) = [
    "one" => 1,
    "two" => 2,
];
  // $one is 1, $two is 2
list(list("x" => $x1, "y" => $y1), list("x" => $x2, "y" => $y2)) = [
    ["x" => 1, "y" => 2],
    ["x" => 3, "y" => 4]
];
  // $x1 is 1, $y1 is 2, $x2 is 3, $y2 is 4
list(0 => list($x1, $x2), 1 => list($x2, $y2)) = [[1, 2], [3, 4]];
  // $x1 is 1, $y1 is 2, $x2 is 3, $y2 is 4
```

####print

**Syntax**

<pre>
  <i>print-intrinsic:</i>
    print  <i>expression</i>
    print  (  <i>expression</i>  )
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

*expression* value must be [convertable to a string](08-conversions.md#converting-to-string-type).
In particular, it should not be an array and if it is an object, it must implement
a [`__toString` method](14-classes.md#method-__tostring).

**Semantics**

After converting its *expression*'s value to a string, if necessary,
`print` writes the resulting string to [`STDOUT`](06-constants.md#core-predefined-constants).
Unlike [`echo`](#echo), `print` can be used in any context allowing an expression. It
always returns the value 1.

See also: [double quioted strings](09-lexical-structure.md#double-quoted-string-literals) and
[heredoc documents](09-lexical-structure.md#heredoc-string-literals), [conversion to string](08-conversions.md#converting-to-string-type).

**Examples**

```PHP
$v1 = TRUE;
$v2 = 123;
print  '>>' . $v1 . '|' . $v2 . "<<\n";   // outputs ">>1|123<<"
print ('>>' . $v1 . '|' . $v2 . "<<\n");  // outputs ">>1|123<<"
$v3 = "qqq{$v2}zzz";
print "$v3\n";            // outputs "qqq123zzz"
$a > $b ? print "..." : print "...";
```

####unset

**Syntax**

<pre>
  <i>unset-intrinsic:</i>
    unset  (  <i>expression-list-one-or-more</i>  )
</pre>

**Defined elsewhere**

* [*expression-list-one-or-more*](#isset)

**Constraints**

Each *expression* must designate a variable.

**Semantics**

This intrinsic [unsets](07-variables.md#general) the variables designated by each
*expression* in *expression-list-one-or-more*. No value is returned. An
attempt to unset a non-existent variable (such as a non-existent element
in an array) is ignored.

When called from inside a function, this intrinsic behaves, as follows:

-   For a variable declared `global` in that function, `unset` removes the
    alias to that variable from the scope of the current call to that
    function. The global variable remains set.
    (To unset the global variable, use unset on the corresponding
    [`$GLOBALS`](07-variables.md#predefined-variables) array entry.
-   For a variable passed byRef to that function, `unset` removes the
    alias to that variable from the scope of the current call to that
    function. Once the function returns, the passed-in argument variable
    is still set.
-   For a variable declared static in that function, `unset` removes the
    alias to that variable from the scope of the current call to that
    function. In subsequent calls to that function, the static variable
    is still set and retains its value from call to call.

Any visible instance property may be unset, in which case, the property
is removed from that instance.

If this intrinsic is used with an expression that designates a [dynamic
property](14-classes.md#dynamic-members), then if the class of that property has an [`__unset`
method](14-classes.md#method-__unset), that method is called.

**Examples**

```PHP
unset($v);
unset($v1, $v2, $v3);
unset($x->m); // if m is a dynamic property, $x->__unset("m") is called
```

###Anonymous Function Creation

**Syntax**

<pre>
  <i>anonymous-function-creation-expression:</i>
  static<sub>opt</sub> function  &<sub>opt</sub> (  <i>parameter-declaration-list<sub>opt<sub></i>  ) <i>return-type<sub>opt</sub></i> <i>anonymous-function-use-clause<sub>opt</sub></i>
      <i>compound-statement</i>

  <i>anonymous-function-use-clause:</i>
    use  (  <i>use-variable-name-list</i>  )

  <i>use-variable-name-list:</i>
    &amp;<sub>opt</sub>   <i>variable-name</i>
    <i>use-variable-name-list</i>  ,  &<sub>opt</sub>  <i>variable-name</i>
</pre>

**Defined elsewhere**

* [*parameter-declaration-list*](13-functions.md#function-definitions)
* [*return-type*](13-functions.md#function-definitions)
* [*compound-statement*](11-statements.md#compound-statements)
* [*variable-name*](09-lexical-structure.md#names)

**Semantics**

This operator returns an object of type [`Closure`](14-classes.md#class-closure), or a derived
type thereof, that encapsulates the [anonymous function](13-functions.md#anonymous-functions) defined
within. An anonymous function is defined like, and behaves like, a [named
function](13-functions.md#function-definitions) except that the former has no name and has an optional
*anonymous-function-use-clause*.

An expression that designates an anonymous function is compatible with
the pseudo-type [`callable`](13-functions.md#function-definitions).

The *use-variable-name-list* is a list of variables from the enclosing
scope, which are to be made available by name to the body of the
anonymous function. Each of these may be passed by value or byRef, as
needed. The values used for these variables are those at the time the
`Closure` object is created, not when it is used to call the function it
encapsulates.

An anonymous function defined inside an instance or static method has its
[*scope*](14-classes.md#class-closure) set to the class it was defined in. Otherwise,
an anonymous function is [*unscoped*](14-classes.md#class-closure).

An anonymous function defined inside an instance method is [*bound*](14-classes.md#class-closure)
to the object on which that method is called, while an
an anonymous function defined inside a static method, or prefixed with the
optional `static` modifier is [*static*](14-classes.md#class-closure), and otherwise
an anonymous function is [*unbound*](14-classes.md#class-closure).

**Examples**

```PHP
function doit($value, callable $process)
{
  return $process($value);
}
$result = doit(5, function ($p) { return $p * 2; });  // doubles a value
$result = doit(5, function ($p) { return $p * $p; }); // squares a value
// -----------------------------------------
class C
{
  public function compute(array $values)
  {
    $count = 0;
    $callback1 = function () use (&amp;$count) // has C as its scope
    {
      ++$count;
      //...
    };
    //...
    $callback2 = function()   // also has C as its scope
    {
      //...
    };
    //...
  }
  //...
}
```

##Postfix Operators

###General

**Syntax**

<pre>
  <i>postfix-expression:</i>
    <i>primary-expression</i>
    <i>clone-expression</i>
    <i>object-creation-expression</i>
    <i>array-creation-expression</i>
    <i>subscript-expression</i>
    <i>function-call-expression</i>
    <i>member-selection-expression</i>
    <i>postfix-increment-expression</i>
    <i>postfix-decrement-expression</i>
    <i>scope-resolution-expression</i>
    <i>exponentiation-expression</i>
</pre>

**Defined elsewhere**

* [*primary-expression*](#general-1)
* [*clone-expression*](#the-clone-operator)
* [*object-creation-expression*](#the-new-operator)
* [*array-creation-expression*](#array-creation-operator)
* [*subscript-expression*](#subscript-operator)
* [*function-call-expression*](#function-call-operator)
* [*member-selection-expression*](#member-selection-operator)
* [*postfix-increment-expression*](#postfix-increment-and-decrement-operators)
* [*postfix-decrement-expression*](#postfix-increment-and-decrement-operators)
* [*scope-resolution-expression*](#scope-resolution-operator)
* [*exponentiation-expression*](#exponentiation-operator)

**Semantics**

These operators associate left-to-right.

###The `clone` Operator

**Syntax**

<pre>
  <i>clone-expression:</i>
    clone  <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

*expression* must designate an object.

**Semantics**

The `clone` operator creates a new object that is a shallow copy of the object designated by *expression*.
Then, if the class type of *expression* has a method called [`__clone`](14-classes.md#method-__clone), it is called to perform a deep copy.
The result is the new object.

**Examples**

Consider a class `Employee`, from which is derived a class `Manager`. Let us
assume that both classes contain properties that are objects. `clone` is
used to make a copy of a `Manager` object, and behind the scenes, the
`Manager` object uses clone to copy the properties for the base class,
`Employee`.

```PHP
class Employee
{
  //...
  public function __clone()
  {
    // make a deep copy of Employee object
  }
}
class Manager extends Employee
{
  //...
  public function __clone()
  {
    $v = parent::__clone();
    // make a deep copy of Manager object

  }
}
$obj1 = new Manager("Smith", 23);
$obj2 = clone $obj1;  // creates a new Manager that is a deep copy
```

###The `new` Operator

**Syntax**

<pre>
  <i>object-creation-expression:</i>
    new  <i>class-type-designator</i>  (  <i>argument-expression-list<sub>opt</sub></i>  )
    new  <i>class-type-designator</i>
    new  class  (  <i>argument-expression-list<sub>opt</sub></i>  )
        <i>class-base-clause<sub>opt</sub></i>   <i>class-interface-clause<sub>opt</sub></i>
        {   <iclass-member-declarations<sub>opt</sub></i>   }
    new  class <i>class-base-clause<sub>opt</sub></i>  <i>class-interface-clause<sub>opt</sub></i>
        {   <i>class-member-declarations<sub>opt</sub></i>   }

  <i>class-type-designator:</i>
    <i>qualified-name</i>
    <i>expression</i>
</pre>

**Defined elsewhere**

* [*argument-expression-list*](#function-call-operator)
* [*class-base-clause*](14-classes.md#class-declarations)
* [*class-interface-clause*](14-classes.md#class-declarations)
* [*class-member-declarations*](14-classes.md#class-members)
* [*expression*](#general-6)
* [*qualified-name*](09-lexical-structure.md#names)

**Constraints**

*qualified-name* must name a class.

*expression* must be a value of type `string` (but not be a string
literal) that contains the name of a class, or an object.

*class-type-designator* must not designate an [abstract class](14-classes.md#general).

The number of arguments in *argument-expression-list* must be at least
as many as the number of non-optional parameters defined for the class's [constructor](14-classes.md#constructors).

**Semantics**

The `new` *class-type-designator* forms create an object of the class type specified by *class-type-designator*. The `new class` forms create an object of an *anonymous class type*, a type that has an unspecified name. In all other respects, however, an anonymous class has the same capabilities as a named class type.

If the *class-type-designator* is an expression resulting in a string value,
that string is used as the class name. If the expression results in an object,
the class of the object is used as the class for the new object.

The *qualified-name* is resolved according to the rules described in
[scope resolution operator](#scope-resolution-operator), including
support for `self`, `parent` and `static`.

After the object has been created, each instance property is initialized
with the values [specified in property definition](14-classes.md#properties),
or the value `NULL` if no initializer value is provided.

The object is then initialized by calling the class's [constructor](14-classes.md#constructors)
passing it the optional *argument-expression-list*. If the class has no
constructor, the constructor that class inherits (if any) is used. The class
can also specify no constructor definition, in this case the constructor call is omitted.

The result of a named-type *object-creation-expression* is an object of the type specified by *class-type-designator*. The result of an anonymous class *object-creation-expression* is an object of unspecified type. However, this type will subtype all types
provided by *class-base-clause* and *class-interface-clause* and the *class-members* definition should follow the same inheritance and implementation rules as the regular [class declaration](14-classes.md#class-declarations) does.

Each distinct source code expression of the form `new class` results in the class type that is different from that of all other anonymous class types. However, multiple evaluations of the same source code expression of the form `new class` result in instances of the same class type.

Because a constructor call is a function call, the relevant parts of
[function call operator](#function-call-operator) section also apply.

**Examples**

```PHP
class Point
{
  public function __construct($x = 0, $y = 0)
  {
    ...
  }
  ...
}
$p1 = new Point;     // create Point(0, 0)
$p1 = new Point(12);   // create Point(12, 0)
$cName = 'Point';
$p1 = new $cName(-1, 1); // create Point(-1, 1)
// -----------------------------------------
$v2 = new class (100) extends C1 implements I1, I2 {
	public function __construct($p) {
	    echo "Inside class " . __CLASS__ . " constructor with parameter $p\n";
	}
};
```

###Array Creation Operator

An array is created and initialized by one of two equivalent ways: via
the array-creation operator `[]`, as described below, or the intrinsic
[`array`](#array).

**Syntax**

<pre>
  <i>array-creation-expression:</i>
    array  (  <i>array-initializer<sub>opt</sub></i>  )
    [ <i>array-initializer<sub>opt</sub></i> ]

  <i>array-initializer:</i>
    <i>array-initializer-list</i>  ,<sub>opt</sub>

  <i>array-initializer-list:</i>
    <i>array-element-initializer</i>
    <i>array-element-initializer  ,  array-initializer-list</i>

  <i>array-element-initializer:</i>
    &<sub>opt</sub>   <i>element-value</i>
    <i>element-key</i>  =>  &<sub>opt</sub>   <i>element-value</i>

  <i>element-key:</i>
    <i>expression</i>

  <i>element-value</i>
    <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

If *array-element-initializer* contains &, *expression* in *element-value*
must designate a [variable](09-lexical-structure.md#names).

**Semantics**

If *array-initializer* is omitted, the array has zero elements. For
convenience, an *array-initializer* may have a trailing comma; however,
this comma is ignored. An *array-initializer-list* consists of a
comma-separated list of one or more *array-element-initializer* items, each
of which is used to provide an *element-value* and an optional
*element-key*.

If the type of *element-key* is neither `int` nor `string`, keys with `float`
or `bool` values, or strings whose contents match exactly the pattern of
[*decimal-literal*](09-lexical-structure.md#integer-literals), are [converted to integer](08-conversions.md#converting-to-integer-type),
and keys of all other types are [converted to string](08-conversions.md#converting-to-string-type).

If *element-key* is omitted from an *array-element-initializer*, an
element key of type `int` is associated with the corresponding
*element-value*. The key associated is one more than the largest previously
assigned non-negative `int` key for this array, regardless of whether that key was
provided explicitly or by default. If the array has no non-negative `int` keys,
the key `0` is used.
If the largest previously assigned `int` key is the largest integer value that can be represented,
the new element is not added.

Once the element keys have been converted to `int` or `string`, and omitted
element keys have each been associated by default, if two or more
*array-element-initializer* elements in an *array-initializer* contain the same
key, the lexically right-most one is the one whose *element-value* is used
to initialize that element.

The result of this operator is the newly created array value.

If *array-element-initializer* contains &, *element-value's* value is
stored using [byRef assignment](04-basic-concepts.md#assignment).

**Examples**

```PHP
$v = [];      // array has 0 elements, i.e. empty array
$v = array(TRUE);   // array has 1 element, the Boolean TRUE
$v = [123, -56];  // array of two ints, with implicit int keys 0 and 1
$v = [0 => 123, 1 => -56]; // array of two ints, with explicit int keys 0 and 1
$i = 10;
$v = [$i - 10 => 123, $i - 9 => -56]; // key can be a runtime expression
$v = [NULL, 1 => FALSE, 123, 3 => 34e12, "Hello"];  // implicit & explicit keys
$i = 6; $j = 12;
$v = [7 => 123, 3 => $i, 6 => ++$j];  // keys are in arbitrary order
$v[4] = 99;   // extends array with a new element
$v = [2 => 23, 1 => 10, 2 => 46, 1.9 => 6];
     // array has 2, with keys 2 and 1, values 46 and 6, respectively
$v = ["red" => 10, "4" => 3, 9.2 => 5, "12.8" => 111, NULL => 1];
     // array has 5 elements, with keys “red”, 4, 9, “12.8”, and “”.
$c = array("red", "white", "blue");
$v = array(10, $c, NULL, array(FALSE, NULL, $c));
$v = array(2 => TRUE, 0 => 123, 1 => 34.5, -1 => "red");
foreach($v as $e) { /* ... */ } // iterates over keys 2, 0, 1, -1
for ($i = -1; $i <= 2; ++$i) { echo $v[$i]; } // retrieves via keys -1, 0, 1, 2
```

###Subscript Operator

**Syntax**

<pre>
  <i>subscript-expression:</i>
    <i>postfix-expression</i>  [  <i>expression<sub>opt</sub></i>  ]
    <i>postfix-expression</i>  {  <i>expression</i>  }   <b>[Deprecated form]</b>
</pre>

**Defined elsewhere**

* [*postfix-expression*](#general-3)
* [*expression*](#general-6)

**Constraints**

If *postfix-expression* designates a string, *expression* must not
designate a string.

*expression* can be omitted only if *subscript-expression* is used in a
modifiable-lvalue context and *postfix-expression* does not designate a string.
Exception from this is when *postfix-expression* is an empty string - then it is
converted to an empty array.

If *subscript-expression* is used in a non-lvalue context, the element
being designated must exist.

**Semantics**

A *subscript-expression* designates a (possibly non-existent) element of
an array or string. When *subscript-expression* designates an object of
a type that implements [`ArrayAccess`](15-interfaces.md#interface-arrayaccess), the minimal semantics are
defined below; however, they can be augmented by that object's methods
[`offsetGet`](15-interfaces.md#interface-arrayaccess) and [`offsetSet`](15-interfaces.md#interface-arrayaccess).

The element key is designated by *expression*. If the type of *element-key* is neither `int` nor `string`, keys with `float`
or `bool` values, or strings whose contents match exactly the pattern of
[*decimal-literal*](09-lexical-structure.md#integer-literals), are [converted to integer](08-conversions.md#converting-to-integer-type),
and key values of all other types are [converted to string](08-conversions.md#converting-to-string-type).

If both *postfix-expression* and *expression* designate strings,
*expression* is treated as if it specified the `int` key zero instead
and a non-fatal error is produces.

A *subscript-expression* designates a modifiable lvalue if and only if
*postfix-expression* designates a modifiable lvalue.

**postfix-expression designates an array**

If *expression* is present, if the designated element exists, the type
and value of the result is the type and value of that element;
otherwise, the result is `NULL`.

If *expression* is omitted, a new element is inserted. Its key has type
`int` and is one more than the highest, previously assigned, non-negative
`int` key for this array. If this is the first element with a non-negative
`int` key, key `0` is used.
If the largest previously assigned `int` key is the largest integer value that can be represented,
the new element is not added.
The result is the added new element, or `NULL` if the element was not added.

-   If the usage context is as the left-hand side of a
    [*simple-assignment-expression*](#simple-assignment), the value of the new
    element is the value of the right-hand side of that
    *simple-assignment-expression*.
-   If the usage context is as the left-hand side of a
    [*compound-assignment-expression*](#compound-assignment): the expression
    `e1 op= e2` is evaluated as `e1 = NULL op (e2)`.
-   If the usage context is as the operand of a
    [postfix- or prefix-increment or decrement operator](#postfix-increment-and-decrement-operators), the value
    of the new element is considered to be `NULL`.

**postfix-expression designates a string**

The *expression* is converted to `int` and the result is the character of the
string at the position corresponding to that integer. If the integer is negative,
the position is counted backwards from the end of the string. If the position refers
to a non-existing offset, the result is an empty string.

If the operator is used as the left-hand side of a [*simple-assignment-expression*](#simple-assignment),

- If the assigned string is empty, or in case of non-existing negative offset (absolute value larger than string length), a warning is raised and no assignment is performed.
- If the offset is larger than the current string length, the string is extended to a length equal to the offset value, using space (0x20) padding characters.
- The value being assigned is converted to string and the character in the specified offset is replaced by the first character of the string.

The subscript operator can not be used on a string value in a byRef context or as the operand of the
[postfix- or prefix-increment or decrement operators](#postfix-increment-and-decrement-operators) or on the left
side of [*compound-assignment-expression*](#compound-assignment),
doing so will result in a fatal error.

**postfix-expression designates an object of a type that implements `ArrayAccess`**

If *expression* is present,

-   If *subscript-expression* is used in a non-lvalue context, the
    object's method `offsetGet` is called with an argument of
    *expression*. The return value of the `offsetGet` is the result.
-   If the usage context is as the left-hand side of a
    *simple-assignment-expression*, the object's method `offsetSet` is
    called with a first argument of *expression* and a second argument
    that is the value of the right-hand side of that
    *simple-assignment-expression*. The value of the right-hand side
    is the result.
-   If the usage context is as the left-hand side of a
    *compound-assignment-expression*, the expression `e1[e] op= e2` is
    evaluated as `e1[e] = e1->offsetGet(e) op (e2)`, which is then
    processed according to the rules for simple assignment immediately
    above.
-   If the usage context is as the operand of
    the [postfix- or prefix-increment or decrement operators](#postfix-increment-and-decrement-operators),
    the object's method `offsetGet` is called with an argument of
    *expression*. However, this method has no way of knowing if an
    increment or decrement operator was used, or whether it was a prefix
    or postfix operator. In order for the value to be modified by the increment/decrement,
    `offsetGet` must return byRef.
    The result of the subscript operator value returned by `offsetGet`.

If *expression* is omitted,

-   If the usage context is as the left-hand side of a
    *simple-assignment-expression*, the object's method
    [`offsetSet`](15-interfaces.md#interface-arrayaccess) is called with a first argument of `NULL` and a second
    argument that is the value of the right-hand side of that
    *simple-assignment-expression*. The type and value of the result is
    the type and value of the right-hand side of that
    *simple-assignment-expression*.
-   If the usage context is as the left-hand side of a
    *compound-assignment-expression*: The expression `e1[] op= e2` is
    evaluated as `e1[] = e1->offsetGet(NULL) op (e2)`, which is then processed
    according to the rules for simple assignment immediately above.
-   If the usage context is as the operand of
    the [postfix- or prefix-increment or decrement operators](#postfix-increment-and-decrement-operators),
    the object's method `offsetGet` is called with an argument of `NULL`.
    However, this method has no way of knowing if an increment or
    decrement operator was used, or whether it was a prefix or postfix
    operator. In order for the value to be modified by the increment/decrement,
    `offsetGet` must return byRef.
    The result of the subscript operator value returned by `offsetGet`.

Note: The brace (`{...}`) form of this operator has been deprecated.

**Examples**

```PHP
$v = array(10, 20, 30);
$v[1] = 1.234;    // change the value (and type) of element [1]
$v[-10] = 19;   // insert a new element with int key -10
$v["red"] = TRUE; // insert a new element with string key "red"
[[2,4,6,8], [5,10], [100,200,300]][0][2]  // designates element with value 6
["black", "white", "yellow"][1][2]  // designates substring "i" in "white"
function f() { return [1000, 2000, 3000]; }
f()[2];      // designates element with value 3000
"red"[1.9];    // designates "e"
"red"[-2];    // designates "e"
"red"[0][0][0];    // designates "r"
// -----------------------------------------
class MyVector implements ArrayAccess { /* ... */ }
$vect1 = new MyVector(array(10, 'A' => 2.3, "up"));
$vect1[10] = 987; // calls Vector::offsetSet(10, 987)
$vect1[] = "xxx"; // calls Vector::offsetSet(NULL, "xxx")
$x = $vect1[1];   // calls Vector::offsetGet(1)
```

###Function Call Operator

**Syntax**

<pre>
  <i>function-call-expression:</i>
    <i>qualified-name</i>  (  <i>argument-expression-list<sub>opt</sub></i>  )
    <i>postfix-expression</i>  (  <i>argument-expression-list<sub>opt</sub></i>  )

  <i>argument-expression-list:</i>
    <i>argument-expression</i>
    <i>argument-expression-list</i>  ,  <i>argument-expression</i>

  <i>argument-expression:</i>
    <i>variadic-unpacking</i>
    <i>assignment-expression</i>

  <i>variadic-unpacking:</i>
    ... <i>assignment-expression</i>

</pre>

**Defined elsewhere**

* [*postfix-expression*](#general-3)
* [*assignment-expression*](#general-5)

**Constraints**

*postfix-expression* must designate a function, either by being its
*name*, by being a value of type string (but not a string literal) that
contains the function's name, or by being an object of a type that implements
[`__invoke`](14-classes.md#method-__invoke) method (including [`Closure`](14-classes.md#class-closure) objects).

The number of arguments present in a function call must be at least as
many as the number of non-optional parameters defined for that function.

No calls can be made to a [conditionally defined function](13-functions.md#general) until
that function exists.

Any argument that matches a parameter passed byRef should (but need not)
designate an lvalue.

If *variadic-unpacking* is used, the result of the expression must be an array or [`Traversable`](15-interfaces.md#interface-traversable).
If incompatible value is supplied, the argument is ignored and a non-fatal error is issued.

**Semantics**

An expression of the form *function-call-expression* is a *function
call*. The expression designates the *called function*, and
*argument-expression-list* specifies the arguments to be passed to that
function. An argument can be any value. In a function call,
*postfix-expression* is evaluated first, followed by each
*assignment-expression* in the order left-to-right. There is
a [sequence point](#general) after each argument is evaluated and right before the function is called.
For details of the result of a function call see [`return` statement](11-statements.md#the-return-statement).
The value of a function call is a modifiable lvalue only if the function returns a modifiable value byRef.

When *postfix-expression* designates an instance method or constructor,
the instance used in that designation is used as the value of `$this` in
the invoked method or constructor. However, if no instance was used in
that designation (for example, in the call `C::instance_method()`) the
invoked instance has no `$this` defined.

When a function is called, the value of each argument passed to it is
assigned to the corresponding parameter in that function's definition,
if such a parameter exists. The assignment of argument values to
parameters is defined in terms of [simple](#simple-assignment) or
[byRef assignment](#byref-assignment), depending on how the parameter was declared.
There may be more arguments than parameters, in which case, the library functions
[`func_num_args`](http://php.net/manual/function.func-num-args.php),
[`func_get_arg`](http://php.net/manual/function.func-get-arg.php)
and [`func_get_args`](http://php.net/manual/function.func-get-args.php)
can be used to get access to the complete argument list that was
passed. If the number of arguments present in a function call is fewer
than the number of parameters defined for that function, any parameter
not having a corresponding argument is considered undefined if it has no
[default argument value](13-functions.md#function-definitions); otherwise, it is considered defined with
that default argument value.

If an undefined variable is passed using byRef, that variable becomes
defined, with an initial value of `NULL`.

Direct and indirect recursive function calls are permitted.

If *postfix-expression* is a string, this is
a [variable function call](13-functions.md#variable-functions).

If *variadic-unpacking* operation is used, the operand is considered to be a parameter list.
The values contained in the operand are fetched one by one (in the same manner as `foreach` would do)
and used for next arguments of for the call. The keys for in the iteration are ignored.

Multiple unpacking operations can be used in the same function call, and unpacking and regular
parameters can be mixed in any order.

**Examples**

```PHP
function square($v) { return $v * $v; }
square(5);     // call square directly; it returns 25
$funct = square;  // assigns the string "square" to $funct
$funct(-2.3)    // call square indirectly; it returns 5.29
strlen($lastName); // returns the # of bytes in the string
// -----------------------------------------
function f1() { ... }  function f2() { ... }  function f3() { ... }
for ($i = 1; $i <= 2; ++$i) { $f = 'f' . $i;  $f(); }
// -----------------------------------------
function f($p1, $p2, $p3, $p4, $p5) { ... }
function g($p1, $p2, $p3, $p4, $p5) { ... }
function h($p1, $p2, $p3, $p4, $p5) { ... }
$funcTable = array(f, g, h);  // list of 3 function designators
$i = 1;
$funcTable[$i++]($i, ++$i, $i, $i = 12, --$i); // calls g(2,3,3,12,11)
// -----------------------------------------
function f4($p1, $p2 = 1.23, $p3 = "abc") { ... }
f4(); // inside f4, $p1 is undefined, $p2 is 1.23, $p3 is "abc"
// -----------------------------------------
function f(&$p) { ... }
$a = array(10, 20, 30);
f($a[5]); // non-existent element going in, but element exists afterwards
// -----------------------------------------
function factorial($int)  // contains a recursive call
{
  return ($int > 1) ? $int * factorial($int - 1) : $int;
}
// -----------------------------------------
$anon = function () { ... };  // store a Closure in $anon
$anon();  // call the anonymous function encapsulated by that object
```

###Member-Selection Operator

**Syntax**

<pre>
  <i>member-selection-expression:</i>
    <i>postfix-expression</i>  ->  <i>member-selection-designator</i>

  <i>member-selection-designator:</i>
    <i>name</i>
    <i>expression</i>
</pre>

**Defined elsewhere**

* [*postfix-expression*](#general-3)
* [*name*](09-lexical-structure.md#names)
* [*expression*](#general-6)

**Constraints**

*postfix-expression* must designate an object or be `NULL`, `FALSE`, or an
empty string.

*name* must designate an instance property, or an instance or static
method of *postfix-expression*'s class type.

*expression* must be a value of type `string` (but not a string literal)
that contains the name of an instance property (**without** the
leading `$`) or an instance or static method of that instance's class
type.

**Semantics**

A *member-selection-expression* designates an instance property or an
instance or static method of the object designated by
*postfix-expression*. For a property, the value is that of the property,
and is a modifiable lvalue if *postfix-expression* is a modifiable
lvalue.

When the `->` operator is used in a modifiable lvalue context and *name*
or *expression* designate a property that is not visible, the property
is treated as a [dynamic property](14-classes.md#dynamic-members). If *postfix-expression*'s class
type defines a [`__set` method](14-classes.md#method-__set), it is called to store the
property's value. When the `->` operator is used in a non-lvalue context
and *name* or *expression* designate a property that is not visible, the
property is treated as a dynamic property. If *postfix-expression*'s
class type defines a [`__get` method](14-classes.md#method-__get), it is called to retrieve
the property's value.

If *postfix-expression* is `NULL`, `FALSE`, or an empty string, an expression
of the form `$p->x = 10` causes an instance of [`stdClass`](14-classes.md#class-stdclass) to be
created with a dynamic property x having a value of 10. `$p` is then made
to refer to this instance.

**Examples**

```PHP
class Point
{
  private $x;
  private $y;
  public function move($x, $y)
  {
    $this->x = $x;  // sets private property $x
    $this->y = $y;  // sets private property $x
  }
  public function __toString()
  {
    return '(' . $this->x . ',' . $this->y . ')';
  }     // get private properties $x and $y
    public function __set($name, $value) { ... }
    public function __get($name) { ... }
}
$p1 = new Point;
$p1->move(3, 9);  // calls public instance method move by name
$n = "move";
$p1->$n(-2, 4);   // calls public instance method move by variable
$p1->color = "red"; // turned into $p1->__set("color", "red");
$c = $p1->color;  // turned into $c = $p1->__get("color");
```

###Postfix Increment and Decrement Operators

**Syntax**

<pre>
  <i>postfix-increment-expression:</i>
    <i>unary-expression</i>  ++

  <i>postfix-decrement-expression:</i>
    <i>unary-expression</i>  --
</pre>

**Defined elsewhere**

* [*unary-expression*](#general-4)

**Constraints**

The operand of the postfix ++ and -- operators must be a modifiable
lvalue that has scalar-compatible type.

**Semantics**

These operators behave like their [prefix counterparts](#prefix-increment-and-decrement-operators) except
that the value of a postfix ++ or -- expression is the value before any
increment or decrement takes place.

**Examples**

```PHP
$i = 10; $j = $i-- + 100;   // old value of $i (10) is added to 100
$a = array(100, 200); $v = $a[1]++; // old value of $ia[1] (200) is assigned
```

###Scope-Resolution Operator

**Syntax**

<pre>
  <i>scope-resolution-expression:</i>
    <i>scope-resolution-qualifier</i>  ::  <i>member-selection-designator</i>
    <i>scope-resolution-qualifier</i>  ::  class

  <i>scope-resolution-qualifier:</i>
    <i>relative-scope</i>
    <i>qualified-name</i>
    <i>expression</i>

  <i>relative-scope</i>:
    self
    parent
    static
</pre>

**Defined elsewhere**

* [*member-selection-designator*](#member-selection-operator)

**Constraints**

*qualified-name* must be the name of a class or interface type.

*expression* must be a value of type string (but not a string literal)
that contains the name of a class or interface type.

**Semantics**

From inside or outside a class or interface, operator `::` allows the
selection of a constant. From inside or outside a class, this operator
allows the selection of a static property, static method, or instance
method. From within a class, it also allows the selection of an
overridden property or method. For a property, the value is that of the
property, and is a modifiable lvalue if *member-selection-designator* is
a modifiable lvalue.

If *member-selection-designator* is a [*name*](09-lexical-structure.md#names), this operator is accessing
a class constant. This form can not be used as an lvalue.

If the operator is used as a *postfix-expression* for *function-call-expression*
then the operator is accessing the method - which, outside of the object context,
is treated as static method call.

Inside of the object context when `$this` is defined and the called method is not `static` and
the called class is the same of a parent of the class of `$this`, then the method is
non-static with the same `$this`. Otherwise it is a static method call.

Otherwise, the operator is accessing a static property.

*relative-scope* designates the class with relation to the current class scope.
From within a class, `self` refers to the same class, `parent` refers to the
class the current class extends from. From within a method, `static` refers
to the class corresponds to the class inheritance context in which the method is called.
This allows *late static binding*, when class resolution depends on the dynamic
call context.

```PHP
class Base
{
  public function b()
  {
    static::f();  // calls the most appropriate f()
  }
  public function f() { ... }
}
class Derived extends Base
{
  public function f() { ... }
}
$b1 = new Base;
$b1->b(); // as $b1 is an instance of Base, Base::b() calls Base::f()
$d1 = new Derived;
$d1->b(); // as $d1 is an instance of Derived, Base::b() calls Derived::f()
```

The value of the form of *scope-resolution-expression* ending in `::class`
is a string containing the fully qualified name of the current class,
which for a `static` qualifier, means the current class context.

**Examples**

```PHP
final class MathLibrary
{
  public static function sin() { ... }
  ...
}
$v = MathLibrary::sin(2.34);  // call directly by class name
$clName = 'MathLibrary';
$v = $clName::sin(2.34);    // call indirectly via string
// -----------------------------------------
class MyRangeException extends Exception
{
  public function __construct($message, ...)
  {
    parent::__construct($message);
    ...
  }
  ...
}
// -----------------------------------------
class Point
{
  private static $pointCount = 0;
  public static function getPointCount()
  {
    return self::$pointCount;
  }
  ...
}
```

###Exponentiation Operator

**Syntax**

<pre>
  <i>exponentiation-expression:</i>
    <i>expression</i>  **  <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Semantics**

The `**` operator produces the result of raising the value of the
left-hand operand to the power of the right-hand one.

If either of the operands have an object type supporting `**` operation,
then the object semantics defines the result. The left operand is checked first.

If either or both operands have non-numeric types, their values are converted
to type `int` or `float`, as appropriate. If both operands have non-negative
integer values and the result can be represented as an `int`, the result has
type `int`; otherwise, the result has type `float`.  If either or both operands
were leading-numeric or non-numeric strings, a non-fatal error must be produced
for each.  **Examples**

```PHP
2**3;   // int with value 8
2**3.0;   // float with value 8.0
"2.0"**"3"; // float with value 8.0
```

##Unary Operators

###General

**Syntax**

<pre>
  <i>unary-expression:</i>
    <i>postfix-expression</i>
    <i>prefix-increment-expression</i>
    <i>prefix-decrement-expression</i>
    <i>unary-op-expression</i>
    <i>error-control-expression</i>
    <i>shell-command-expression</i>
    <i>cast-expression</i>
    <i>variable-name-creation-expression</i>
</pre>

**Defined elsewhere**

* [*postfix-expression*](#general-3)
* [*prefix-increment-expression*](#prefix-increment-and-decrement-operators)
* [*prefix-decrement-expression*](#prefix-increment-and-decrement-operators)
* [*unary-op-expression*](#unary-arithmetic-operators)
* [*error-control-expression*](#error-control-operator)
* [*shell-command-expression*](#shell-command-operator)
* [*cast-expression*](#cast-operator)
* [*variable-name-creation-expression*](#variable-name-creation-operator)

**Semantics**

These operators associate right-to-left.

###Prefix Increment and Decrement Operators

**Syntax**

<pre>
  <i>prefix-increment-expression:</i>
    ++ <i>unary-expression</i>

  <i>prefix-decrement-expression:</i>
    -- <i>unary-expression</i>
</pre>

**Defined elsewhere**

* [*unary-expression*](#general-4)

**Constraints**

The operand of the prefix `++` or `--` operator must be a modifiable lvalue
that has scalar-compatible type.

**Semantics**

*Arithmetic Operands*

For a prefix `++` operator used with an arithmetic operand, the [side
effect](#general) of the operator is to increment the value of the operand by 1.
The result is the value of the operand after it
has been incremented. If an `int` operand's value is the largest
representable for that type, the operand is incremented as if it were `float`.

For a prefix `--` operator used with an arithmetic operand, the side
effect of the operator is to decrement the value of the operand by 1.
The result is the value of the operand after it has been
decremented. If an `int` operand's value is the smallest representable for
that type, the operand is decremented as if it were `float`.

For a prefix `++` or `--` operator used with an operand having the value
`INF`, `-INF`, or `NAN`, there is no side effect, and the result is the
operand's value.

*Boolean Operands*

For a prefix `++` or `--` operator used with a Boolean-valued operand, there
is no side effect, and the result is the operand's value.

*NULL-valued Operands*

For a prefix -- operator used with a `NULL`-valued operand, there is no
side effect, and the result is the operand's value. For a prefix `++`
operator used with a `NULL`-valued operand, the side effect is that the
operand's type is changed to int, the operand's value is set to zero,
and that value is incremented by 1. The result is the value of the
operand after it has been incremented.

*String Operands*

For a prefix `--` operator used with an operand whose value is an empty
string, the side effect is that the operand's type is changed to `int`,
the operand's value is set to zero, and that value is decremented by 1.
The result is the value of the operand after it has been incremented.

For a prefix `++` operator used with an operand whose value is an empty
string, the side effect is that the operand's value is changed to the
string "1". The type of the operand is unchanged. The result is the new
value of the operand.

For a prefix `--` or `++` operator used with a numeric string, the numeric
string is treated as the corresponding `int` or `float` value.

For a prefix `--` operator used with a non-numeric string-valued operand,
there is no side effect, and the result is the operand's value.

For a non-numeric string-valued operand that contains only alphanumeric
characters, for a prefix `++` operator, the operand is considered to be a
representation of a base-36 number (i.e., with digits 0–9 followed by A–Z or a–z) in
which letter case is ignored for value purposes. The right-most digit is
incremented by 1. For the digits 0–8, that means going to 1–9. For the
letters "A"–"Y" (or "a"–"y"), that means going to "B"–"Z" (or "b"–"z").
For the digit 9, the digit becomes 0, and the carry is added to the next
left-most digit, and so on. For the digit "Z" (or "z"), the resulting
string has an extra digit "A" (or "a") appended. For example, when
incrementing, "a" -> "b", "Z" -> "AA", "AA" -> "AB", "F29" -> "F30", "FZ9" -> "GA0", and "ZZ9" -> "AAA0". A digit position containing a number wraps
modulo-10, while a digit position containing a letter wraps modulo-26.

For a non-numeric string-valued operand that contains any
non-alphanumeric characters, for a prefix `++` operator, all characters up
to and including the right-most non-alphanumeric character is passed
through to the resulting string, unchanged. Characters to the right of
that right-most non-alphanumeric character are treated like a
non-numeric string-valued operand that contains only alphanumeric
characters, except that the resulting string will not be extended.
Instead, a digit position containing a number wraps modulo-10, while a
digit position containing a letter wraps modulo-26.

*Object Operands*

If the operand has an object type supporting the operation,
then the object semantics defines the result. Otherwise, the operation has
no effect and the result is the operand.

**Examples**

```PHP
$i = 10; $j = --$i + 100;   // new value of $i (9) is added to 100
$a = array(100, 200); $v = ++$a[1]; // new value of $a[1] (201) is assigned
$a = "^^Z"; ++$a; // $a is now "^^A"
$a = "^^Z^^"; ++$a; // $a is now "^^Z^^"
```

###Unary Arithmetic Operators

**Syntax**

<pre>
  <i>unary-op-expression:</i>
    <i>unary-operator cast-expression</i>

  <i>unary-operator: one of</i>
    +  -  !  ~
</pre>

**Defined elsewhere**

* [*cast-expression*](#cast-operator)

**Constraints**

The operand of the unary `+` and unary `-` must have scalar-compatible type.

The operand of the unary `~` operator must have arithmetic or string type, or be
an object supporting `~`.

**Semantics**

For a unary `!` operator the type of the result is `bool`.
The value of the operand is [converted to type `bool`](08-conversions.md#converting-to-boolean-type)
and if it is `TRUE` then the of the operator result is `FALSE`, and the result is `TRUE` otherwise.

*Arithmetic Operands*

For a unary `+` operator used with an arithmetic operand, the type and
value of the result is the type and value of the operand.

For a unary `-` operator used with an arithmetic operand, the value of the
result is the negated value of the operand. However, if an int operand's
original value is the [smallest representable for that type](05-types.md#the-integer-type), the operand is
treated as if it were `float` and the result will be `float`.

For a unary `~` operator used with an `int` operand, the type of the result
is `int`. The value of the result is the bitwise complement of the value
of the operand (that is, each bit in the result is set if and only if
the corresponding bit in the operand is clear). For a unary `~` operator
used with a `float` operand, the value of the operand is first converted
to `int` before the bitwise complement is computed.

*Boolean Operands*

For a unary `+` operator used with a `TRUE`-valued operand, the value of the
result is 1 and the type is `int`. When used with a `FALSE`-valued operand,
the value of the result is zero and the type is `int`.

For a unary `-` operator used with a `TRUE`-valued operand, the value of the
result is -1 and the type is `int`. When used with a `FALSE`-valued operand,
the value of the result is zero and the type is `int`.

*NULL-valued Operands*

For a unary `+` or unary `-` operator used with a `NULL`-valued operand, the
value of the result is zero and the type is `int`.

*String Operands*

For a unary `+` or `-` operator used with a numeric string or a
leading-numeric string, the string is first converted to an `int` or
`float`, as appropriate, after which it is handled as an arithmetic
operand. The trailing non-numeric characters in leading-numeric strings
are ignored. With a non-numeric string, the result has type `int` and
value 0. If the string was leading-numeric or non-numeric, a non-fatal error
MUST be produced.

For a unary `~` operator used with a string, the result is the string with each byte
being bitwise complement of the corresponding byte of the source string.

*Object Operands*

If the operand has an object type supporting the operation,
then the object semantics defines the result. Otherwise, for `~` the fatal error is issued
and for `+` and `-` the object is [converted to `int`](08-conversions.md#converting-to-integer-type).

**Examples**

```PHP
$v = +10;
if ($v1 > -5) // ...
$t = TRUE;
if (!$t) // ...
$v = ~0b1010101;
$s = "\x86\x97"; $s = ~$s; // $s is "yh"
```

###Error Control Operator

**Syntax**

<pre>
  <i>error-control-expression:</i>
    @   <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Semantics**

Operator `@` suppresses the reporting of any error messages generated by the evaluation of
*expression*.

If a custom error-handler has been established using the library
function [`set_error_handler`](http://php.net/manual/function.set-error-handler.php), that handler is
still called.

**Examples**

```PHP
$infile = @fopen("NoSuchFile.txt", 'r');
```

On open failure, the value returned by `fopen` is `FALSE`, which is
sufficient to know to handle the error. The error message that may have been generated
by the `fopen` call is suppressed (not displayed and not logged).

**Implementation Notes**

Given the following example:

```PHP
function f() {
  $ret = $y;
  return $ret;
}

$x = @f();  // without @, get "Undefined variable: y"
```

The following code shows how this statement is handled:

```PHP
$origER = error_reporting();
error_reporting(0);
$tmp = f();
$curER = error_reporting();
if ($curER === 0) error_reporting($origER);
$x = $tmp;
```

###Shell Command Operator

**Syntax**

<pre>
  <i>shell-command-expression:</i>
    `  <i>dq-char-sequence<sub>opt</sub></i>  `
</pre>

where \` is the GRAVE ACCENT character U+0060, commonly referred to as a
*backtick*.

**Defined elsewhere**

* [*dq-char-sequence*](09-lexical-structure.md#double-quoted-string-literals)

**Semantics**

This operator passes *dq-char-sequence* to the command shell for
execution, as though it was being passed to the library function
[`shell_exec`](http://www.php.net/shell_exec). If the output from execution of that command is
written to [`STDOUT`](06-constants.md#core-predefined-constants), that output is the result of this operator
as a string. If the output is redirected away from `STDOUT`, or
*dq-char-sequence* is empty or contains only white space, the result of
the operator is `NULL`.

If [`shell_exec`](http://php.net/manual/function.shell-exec.php) is disabled, this operator is disabled.

**Examples**

```PHP
$result = `ls`;           // result is the output of command ls
$result = `ls >dirlist.txt`;  // result is NULL
$d = "dir"; $f = "*.*";
$result = `$d {$f}`;      // result is the output of command dir *.*
```

###Cast Operator

**Syntax**

<pre>
  <i>cast-expression:</i>
    <i>unary-expression</i>
    (  <i>cast-type</i>  ) <i>expression</i>

  <i>cast-type: one of</i>
    array  binary  bool  boolean  double  int  integer  float  object
    real  string  unset
</pre>

**Defined elsewhere**

* [*unary-expression*](#general-4)

**Semantics**

With the exception of the *cast-type* unset and binary (see below), the
value of the operand *cast-expression* is converted to the type
specified by *cast-type*, and that is the type and value of the result.
This construct is referred to as a *cast* and is used as the verb, "to
cast". If no conversion is involved, the type and value of the result
are the same as those of *cast-expression*.

A cast can result in a loss of information.

A *cast-type* of `array` results in a [conversion to type array](08-conversions.md#converting-to-array-type).

A *cast-type* of `binary` is reserved for future use in dealing with
so-called *binary strings*. For now, it is fully equivalent to `string` cast.

A *cast-type* of `bool` or `boolean` results in a [conversion to type `bool`](08-conversions.md#converting-to-boolean-type).

A *cast-type* of `int` or `integer` results in a [conversion to type `int`](08-conversions.md#converting-to-integer-type).

A *cast-type* of `float`, `double`, or `real` results in a [conversion to type `float`](08-conversions.md#converting-to-floating-point-type).

A *cast-type* of `object` results in a [conversion to type `object`](08-conversions.md#converting-to-object-type).

A *cast-type* of `string` results in a [conversion to type `string`](08-conversions.md#converting-to-string-type).

A *cast-type* of `unset` always results in a value of `NULL`. (This use of
`unset` should not be confused with the [`unset` intrinsic](#unset).

**Examples**

```PHP
(int)(10/3)          // results in the int 3 rather than the float 3.333...
(array)(16.5)      // results in an array of 1 float; [0] = 16.5
(int)(float)"123.87E3" // results in the int 123870
```

###Variable-Name Creation Operator

**Syntax**

<pre>
  <i>variable-name-creation-expression:</i>
    $   <i>expression</i>
    $  {  <i>expression</i>  }
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

In the non-brace form, *expression* must be a
*variable-name-creation-expression* or a *variable-name* that designates
a scalar value or an object convertible to string.

In the brace form, *expression* must be a
*variable-name-creation-expression* or an expression that designates a
scalar value or an object convertible to string.

**Semantics**

The result of this operator is a variable name spelled using the string
representation of the value of *expression* even though such a name
might not be permitted as a [variable-name](09-lexical-structure.md#names) source code token.

The operator will consume the following *variable-name-creation-expression* and *variable-name* tokens, and
also tokens representing [subscript operator](#subscript-operator).

In the absense of braces, the variable is parsed with left-to-right semantics, i.e., in example `$$o->pr`
the expression is treated as `${$o}->pr`, i.e. it is parsed as "take the value of $o, consider it a variable name,
and assuming the variable with this name is an object take the property 'pr' of it".

**Examples**

```PHP
$color = "red";
$$color = 123;    // equivalent to $red = 123
// -----------------------------------------
$x = 'ab'; $ab = 'fg'; $fg = 'xy';
$$ $ $x = 'Hello';  // equivalent to $xy = Hello
// -----------------------------------------
$v1 = 3;
$$v1 = 22;        // equivalent to ${3} = 22, variable name is "3"
$v2 = 9.543;
$$v2 = TRUE;    // equivalent to ${9.543} = TRUE
$v3 = NULL;
$$v3 = "abc";   // equivalent to ${NULL} = "abc", here we create a variable with empty name
// -----------------------------------------
function f1 () { return 2.5; }
${1 + f1()} = 1000;   // equivalent to ${3.5} = 1000
// -----------------------------------------
$v = array(10, 20); $a = 'v';
$$a[0] = 5;       // $v is [5, 20], since $$a is on the left of [] so it gets the first shot
$v = array(10, 20); $a = 'v';
${$a[0]} = 5;   // $v is 5
$v = array(10, 20); $a = 'v';
${$a}[0] = 5;   // $ gets first shot at $a, $v is [5, 20]
```

##`instanceof` Operator

**Syntax**

<pre>
  <i>instanceof-expression:</i>
    <i>unary-expression</i>
    <i>instanceof-subject</i>  instanceof   <i>instanceof-type-designator</i>

  <i>instanceof-subject:</i>
    <i>expression</i>

  <i>instanceof-type-designator:</i>
    <i>qualified-name</i>
    <i>expression</i>
</pre>

**Defined elsewhere**

* [*unary-expression*](#general-4)
* [*expression*](#general-6)
* [*qualified-name*](09-lexical-structure.md#names)

**Constraints**

The *expression* in *instanceof-type-designator* and *instanceof-subject* must not be any form of
literal.

**Semantics**

Operator `instanceof` returns `TRUE` if the value designated by
*expression* in *instanceof-subject* is an object having the type specified
by *instanceof-type-designator*, is an object whose type is derived from that type,
or is an object whose type implements the interface specified by *instanceof-type-designator*.
Otherwise, it returns `FALSE`.

The type can be specified by *instanceof-type-designator* in one of the three forms:
  1. *qualified-name* specifies the type name directly.
  2. When the *expression* form is used, *expression* may have a string value that contains a class or interface name.
  3. Alternatively, *expression* can designate an object, in which case the type of the object is used as the specified type.
     Note that an interface can not be specified with this form.

Note that `instanceof` will not invoke autoloader if the name of the type given does not
correspond to the existing class or interface, instead it will return `FALSE`.

**Examples**

```PHP
class C1 {  }
$c1 = new C1;
class C2 {  }
$c2 = new C2;
class D extends C1 { };
$d = new D;
var_dump($d instanceof C1);      // TRUE
var_dump($d instanceof C2);      // FALSE
var_dump($d instanceof D);       // TRUE
// -----------------------------------------
interface I1 { }
interface I2 { }
class E1 implements I1, I2 { }
$e1 = new E1;
var_dump($e1 instanceof I1);       // TRUE
$iName = "I2";
var_dump($e1 instanceof $iName);   // TRUE
$e2 = new E1;
var_dump($e2 instanceof $e1);      // TRUE
```

##Multiplicative Operators

**Syntax**

<pre>
  <i>multiplicative-expression:</i>
    <i>instanceof-expression</i>
    <i>multiplicative-expression</i>  *  <i>instanceof-expression</i>
    <i>multiplicative-expression</i>  /  <i>instanceof-expression</i>
    <i>multiplicative-expression</i>  %  <i>instanceof-expression</i>
</pre>

**Defined elsewhere**

* [*instanceof-expression*](#instanceof-operator)

**Constraints**

The right-hand operand of operator `/` and operator `%` must not be zero.

**Semantics**

If either of the operands is an object supporting the operation, the result is
defined by that object's semantics, with the left operand checked first.

The binary `*` operator produces the product of its operands. If either or both
operands have non-numeric types, their values are converted to type `int` or
`float`, as appropriate. If either or both operands were leading-numeric or
non-numeric strings, a non-fatal error MUST be produced for each.  Then if
either operand has type `float`, the other is converted to that type, and the
result has type `float`. Otherwise, both operands have type `int`, in which
case, if the resulting value can be represented in type `int` that is the
result type.  Otherwise, the result would have type `float`.

Division by zero results in a non-fatal error. If the value of the numerator is positive, the result value is `INF`. If the value of the numerator is negative, the result value is `-INF`. If the value of the numerator is zero, the result value is `NAN`.

The binary `/` operator produces the quotient from dividing the left-hand
operand by the right-hand one. If either or both operands have non-numeric
types, their values are converted to type `int` or `float`, as appropriate. If
either or both operands were leading-numeric or non-numeric strings, a
non-fatal error must be produced for each. Then if either operand has type
`float`, the other is converted to that type, and the result has type `float`.
Otherwise, both operands have type `int`, in which case, if the mathematical
value of the computation can be preserved using type `int`, that is the result
type; otherwise, the type of the result is `float`.

The binary `%` operator produces the remainder from dividing the left-hand
operand by the right-hand one. If the type of both operands is not `int`, their
values are converted to that type.  If either or both operands were
leading-numeric or non-numeric strings, a non-fatal error MUST be produced for
each.  The result has type `int`. If the right-hand operand has value zero, an
exception of type `DivisionByZeroError` is thrown.

These operators associate left-to-right.

**Examples**

```PHP
-10 * 100;       // int with value -1000
100 * -3.4e10;   // float with value -3400000000000
"123" * "2e+5;   // float with value 24600000
100 / 100;       // int with value 1
100  / "123";    // float with value 0.8130081300813
"123" % 100;     // int with value 23
100 / 0;         // results in a diagnostic followed by bool with value false
100 / 0.0;       // results in a diagnostic followed by bool with value false
1.3 / 0;         // results in a diagnostic followed by bool with value false
1.3 / 0.0;       // results in a diagnostic followed by bool with value false
100 / "a";       // results in a diagnostic followed by bool with value false (a is converted to 0)
```

##Additive Operators

**Syntax**

<pre>
  <i>additive-expression:</i>
    <i>multiplicative-expression</i>
    <i>additive-expression</i>  +  <i>multiplicative-expression</i>
    <i>additive-expression</i>  -  <i>multiplicative-expression</i>
    <i>additive-expression</i>  .  <i>multiplicative-expression</i>
</pre>

**Defined elsewhere**

* [*multiplicative-expression*](#multiplicative-operators)

**Constraints**

If either operand of `+` has array type, the other operand must also have array
type.

Binary `-` operator can not be applied to arrays.

**Semantics**

If either of the operands is an object supporting the operation, the result is
defined by that object's semantics, with the left operand checked first.

For non-array operands, the binary `+` operator produces the sum of those
operands, while the binary `-` operator produces the difference of its operands
when subtracting the right-hand operand from the left-hand one.  If either or
both operands have non-array, non-numeric types, their values are converted to
type `int` or `float`, as appropriate.  If either or both operands were
leading-numeric or non-numeric strings, a non-fatal error MUST be produced for
each.  Then if either operand has type `float`, the other is converted to that
type, and the result has type `float`. Otherwise, both operands have type
`int`, in which case, if the resulting value can be represented in type `int`
that is the result type.  Otherwise, the result would have type `float`.

If both operands have array type, the binary `+` operator produces a new
array that is the union of the two operands. The result is a copy of the
left-hand array with elements inserted at its end, in order, for each
element in the right-hand array whose key does not already exist in the
left-hand array. Any element in the right-hand array whose key exists in
the left-hand array is ignored.

The binary `.` operator creates a string that is the concatenation of the
left-hand operand and the right-hand operand, in that order. If either
or both operands have types other than `string`, their values are
converted to type `string`. The result has type `string`.

These operators associate left-to-right.

**Examples**

```PHP
-10 + 100;        // int with value 90
100 + -3.4e10;    // float with value -33999999900
"123" + "2e+5";   // float with value 200123
100 - "123";      // int with value 23
-3.4e10 - "abc";  // float with value -34000000000
// -----------------------------------------
[1, 5 => FALSE, "red"] + [4 => -5, 1.23]; // [1, 5 => FALSE, "red", 4 => -5]
  // dupe key 5 (value 1.23) is ignored
[NULL] + [1, 5 => FALSE, "red"];          // [NULL, 5 => FALSE, "red"]
  // dupe key 0 (value 1) is ignored
[4 => -5, 1.23] + [NULL];                 // [4 => -5, 1.23, 0 => NULL]
// -----------------------------------------
-10 . NAN;        // string with value "-10NAN"
INF . "2e+5";     // string with value "INF2e+5"
TRUE . NULL;      // string with value "1"
10 + 5 . 12 . 100 - 50;  // int with value 1512050; ((((10 + 5).12).100)-50)
```

##Bitwise Shift Operators

**Syntax**

<pre>
  <i>shift-expression:</i>
    <i>additive-expression</i>
    <i>shift-expression</i>  &lt;&lt;  <i>additive-expression</i>
    <i>shift-expression</i>  &gt;&gt;  <i>additive-expression</i>
</pre>

**Defined elsewhere**

* [*additive-expression*](#additive-operators)

**Constraints**

Each of the operands must have scalar-compatible type.

**Semantics**

If either of the operands is an object supporting the operation, the result is
defined by that object's semantics, with the left operand checked first.

Given the expression `e1 << e2`, the bits in the value of `e1` are shifted
left by `e2` positions. Bits shifted off the left end are discarded, and
zero bits are shifted on from the right end. Given the expression
`e1 >> e2`, the bits in the value of `e1` are shifted right by
`e2` positions. Bits shifted off the right end are discarded, and the sign
bit is propagated from the left end.

If either operand does not have type `int`, its value is first converted to
that type.  If either or both operands were leading-numeric or non-numeric
strings, a non-fatal error MUST be produced for each.

The type of the result is `int`, and the value of the result is that after
the shifting is complete. The values of `e1` and `e2` are unchanged.

Left shifts where the shift count is greater than the bit width of the integer
type (e.g. 32 or 64) must always result in 0, even if there is no native
processor support for this.

Right shifts where the shift count is greater than the bit width of the integer
type (e.g. 32 or 64) must always result in 0 when `e1` is positive and -1 when
`e1` is negative, even if there is no native processor support for this.

If the shift count is negative, an exception of type `ArithmeticError` is thrown.

These operators associate left-to-right.

**Examples**

```PHP
1000 >> 2   // 0x3E8 is shifted right 2 places
-1000 << 2  // 0xFFFFFC18 is shifted left 5 places
123 >> 128  // Shift count larger than bit width => result 0
123 << 33   // For 32-bit integers the result is zero, otherwise
            // it is 0x7B shifted left 33 places
```

##Relational Operators

**Syntax**

<pre>
  <i>relational-expression:</i>
    <i>shift-expression</i>
    <i>relational-expression</i>  &lt;   <i>shift-expression</i>
    <i>relational-expression</i>  &gt;   <i>shift-expression</i>
    <i>relational-expression</i>  &lt;=  <i>shift-expression</i>
    <i>relational-expression</i>  &gt;=  <i>shift-expression</i>
    <i>relational-expression</i>  &lt;=&gt; <i>shift-expression</i>
</pre>

**Defined elsewhere**

* [*shift-expression*](#bitwise-shift-operators)

**Semantics**

Operator `<=>` represents comparison operator between two expressions, with the
result being an integer less than `0` if the expression on the left is less than the expression on the right
(i.e. if `$a < $b` would return `TRUE`), as defined below by the semantics of the operator `<`,
integer `0` if those expressions are equal (as defined by the semantics of the `==` operator)
and integer greater than `0` otherwise.

Operator `<` represents *less-than*, operator `>` represents
*greater-than*, operator `<=` represents *less-than-or-equal-to*, and
operator `>=` represents *greater-than-or-equal-to*.

The type of the result is `bool`.

Note that *greater-than* semantics is implemented as the reverse of *less-than*, i.e.
`$a > $b` is the same as `$b < $a`. This may lead to confusing results if the operands
are not well-ordered - such as comparing two objects not having comparison semantics, or
comparing arrays.

The following table shows the result for comparison of different types, with the left
operand displayed vertically and the right displayed horizontally.
The conversions are performed according to [type conversion rules](08-conversions.md).

|      | NULL | bool | int | float | string | array | object | resource |
|:------|:---:|:----:|----:|:-----:|:------:|:-----:|:------:|:--------:|
| NULL |  =   | ->   | ->  | ->    | ->     |   ->  | <      | <        |
| bool | <-   | 1    | <-  | <-    | <-     |  <-   | <-     | <-       |
| int  | <-   | ->   | 2   |   2   | <-     | <     |  3     | <-       |
| float | <-  | ->   | 2   |  2    | <-     | <     |  3     | <-       |
| string | <- | ->   | ->  |  ->   | 2, 4   | <     |  3     | 2        |
| array | <-  | ->   | >   |  >    | >      | 5     |  3     | >        |
| object | >  | ->   |  3  | 3     | 3      | 3     |  6     | 3        |
| resource | > | ->  | ->  | ->    | 2      | <     |  3     | 2        |

 - `=` means the result is always "equals", i.e. strict comparisons are always `FALSE` and equality comparisons are always `TRUE`.
 - `<` means that the left operand is always less than the right operand.
 - `>` means that the left operand is always greater than the right operand.
 - `->` means that the left operand is converted to the type of the right operand.
 - `<-` means that the right operand is converted to the type of the left operand.
 - A number means one of the cases below:

1.  If either operand has type `bool`, the other operand is converted to
    that type. The result is the logical comparison of the two operands
    after conversion, where `FALSE` is defined to be less than `TRUE`.
2.  If one of the operands has arithmetic type, is a resource, or a numeric string,
    which can be represented as `int` or `float` without loss of precision,
    the operands are converted to the corresponding arithmetic type, with `float` taking precedence over `int`,
    and resources converting to `int`.
    The result is the numerical comparison of the two operands after conversion.
3.  If only one operand has object type, if the object has comparison handler,
    that handler defines the result.
    Otherwise, if the object can be converted to the other operand's type,
    it is converted and the result is used for the comparison. Otherwise, the object
    compares greater-than any other operand type.
4.  If both operands are non-numeric strings, the result is the lexical
    comparison of the two operands. Specifically, the strings are
    compared byte-by-byte starting with their first byte. If the two
    bytes compare equal and there are no more bytes in either string,
    the strings are equal and the comparison ends; otherwise, if this is
    the final byte in one string, the shorter string compares less-than
    the longer string and the comparison ends. If the two bytes compare
    unequal, the string having the lower-valued byte compares less-than
    the other string, and the comparison ends. If there are more bytes
    in the strings, the process is repeated for the next pair of bytes.
5.  If both operands have array type, if the arrays have different
    numbers of elements, the one with the fewer is considered less-than
    the other one, regardless of the keys and values in each, and the
    comparison ends. For arrays having the same numbers of elements, the
    keys from the left operand are considered one by one, if
    the next key in the left-hand operand exists in the right-hand
    operand, the corresponding values are compared. If they are unequal,
    the array containing the lesser value is considered less-than the
    other one, and the comparison ends; otherwise, the process is
    repeated with the next element. If the next key in the left-hand
    operand does not exist in the right-hand operand, the arrays cannot
    be compared and `FALSE` is returned. If all the values are equal,
    then the arrays are considered equal.
6.  When comparing two objects, if any of the object types has its own compare
    semantics, that would define the result, with the left operand taking precedence.
    Otherwise, if the objects are of different types, the comparison result is `FALSE`.
    If the objects are of the same type, the properties of the objects are
    compares using the array comparison described above.

These operators associate left-to-right.

**Examples**

```PHP
"" < "ab"       // result has value TRUE
"a" > "A"       // result has value TRUE
"a0" < "ab"     // result has value TRUE
"aA <= "abc"    // result has value TRUE
// -----------------------------------------
NULL < [10,2.3] // result has value TRUE
TRUE > -3.4     // result has value FALSE
TRUE < -3.4     // result has value FALSE
TRUE >= -3.4    // result has value TRUE
FALSE < "abc"   // result has value TRUE
// -----------------------------------------
10 <= 0         // result has value FALSE
10 >= "-3.4"    // result has value TRUE
"-5.1" > 0      // result has value FALSE
// -----------------------------------------
[100] < [10,20,30] // result has value TRUE (LHS array is shorter)
[10,20] >= ["red"=>0,"green"=>0] // result has value FALSE, (key 10 does not exists in RHS)
["red"=>0,"green"=>0] >= ["green"=>0,"red"=>0] // result has value TRUE (order is irrelevant)
// ------------------------------------
function order_func($a, $b) {
    return ($a->$x <=> $b->x) ?: ($a->y <=> $b->y) ?: ($a->z <=> $b->z);
}
```

##Equality Operators

**Syntax**

<pre>
  <i>equality-expression:</i>
    <i>relational-expression</i>
    <i>equality-expression</i>  ==  <i>relational-expression</i>
    <i>equality-expression</i>  !=  <i>relational-expression</i>
    <i>equality-expression</i>  &lt;&gt;  <i>relational-expression</i>
    <i>equality-expression</i>  ===  <i>relational-expression</i>
    <i>equality-expression</i>  !==  <i>relational-expression</i>
</pre>

**Defined elsewhere**

* [*relational-expression*](#relational-operators)

**Semantics**

Operator `==` represents *value equality*, operators `!=` and `<>` are
equivalent and represent *value inequality*.

For operators `==`, `!=`, and `<>`, the operands of different types are converted and
compared according to the same rules as in [relational operators](#relational-operators).
Two objects of different types are always not equal.

Operator `===` represents *same type and value equality*, or *identity*, comparison, and operator `!==` represents
the opposite of `===`. The values are considered identical if they have the same type and compare as equal, with the
additional conditions below:
- When comparing two objects, identity operators check to
see if the two operands are the exact same object, not two different objects of the same type and value.
- Arrays must have the same elements in the same order to be considered identical.
- Strings are identical if they contain the same characters, unlike value comparison operators no conversions are performed for numeric strings.

The type of the result is `bool`.

These operators associate left-to-right.

**Examples**

```PHP
"a" <> "aa" // result has value TRUE
// -----------------------------------------
NULL == 0   // result has value TRUE
NULL === 0  // result has value FALSE
TRUE != 100  // result has value FALSE
TRUE !== 100  // result has value TRUE
// -----------------------------------------
"10" != 10  // result has value FALSE
"10" !== 10 // result has value TRUE
// -----------------------------------------
[10,20] == [10,20.0]  // result has value TRUE
[10,20] === [10,20.0] // result has value FALSE
["red"=>0,"green"=>0] === ["red"=>0,"green"=>0] // result has value TRUE
["red"=>0,"green"=>0] === ["green"=>0,"red"=>0] // result has value FALSE
```

## Bitwise AND Operator

**Syntax**

<pre>
  <i>bitwise-AND-expression:</i>
    <i>equality-expression</i>
    <i>bit-wise-AND-expression</i>  &  <i>equality-expression</i>
</pre>

**Defined elsewhere**

* [*equality-expression*](#equality-operators)

**Constraints**

Each of the operands must have scalar-compatible type.

**Semantics**

If either of the operands is an object supporting the operation, the result is
defined by that object's semantics, with the left operand checked first.

If either operand does not have type `int`, its value is first converted to
that type.  If either or both operands were leading-numeric or non-numeric
strings, a non-fatal error MUST be produced for each.

The result of this operator is the bitwise-AND of the two operands, and
the type of that result is `int`.

However, if both operands are strings, the result is the string composed of the sequence of bytes
that are the result of bitwise AND operation performed on the bytes of the operand strings
in the matching positions (`result[0] = s1[0] & s2[0]`, etc.).
If one of the strings is longer than the other, it is cut to the length of the shorter one.

This operator associates left-to-right.

**Examples**

```PHP
0b101111 & 0b101          // 0b101
$lLetter = 0x73;          // letter 's'
$uLetter = $lLetter & ~0x20;  // clear the 6th bit to make letter 'S'
```

##Bitwise Exclusive OR Operator

**Syntax**

<pre>
  <i>bitwise-exc-OR-expression:</i>
    <i>bitwise-AND-expression</i>
    <i>bitwise-exc-OR-expression</i>  ^  <i>bitwise-AND-expression</i>
</pre>

**Defined elsewhere**

* [*bitwise-AND-expression*](#bitwise-and-operator)

**Constraints**

Each of the operands must have scalar-compatible type.

**Semantics**

If either of the operands is an object supporting the operation, the result is
defined by that object's semantics, with the left operand checked first.

If either operand does not have type `int`, its value is first converted to
that type.  If either or both operands were leading-numeric or non-numeric
strings, a non-fatal error MUST be produced for each.

The result of this operator is the bitwise exclusive-OR of the two
operands, and the type of that result is `int`.

However, if both operands are strings, the result is the string composed of the sequence of bytes
that are the result of bitwise XOR operation performed on the bytes of the operand strings
in the matching positions (`result[0] = s1[0] ^ s2[0]`, etc.).
If one of the strings is longer than the other, it is cut to the length of the shorter one.

This operator associates left-to-right.

**Examples**

```PHP
0b101111 ^ 0b101    // 0b101010
$v1 = 1234; $v2 = -987; // swap two integers having different values
$v1 = $v1 ^ $v2;
$v2 = $v1 ^ $v2;
$v1 = $v1 ^ $v2;    // $v1 is now -987, and $v2 is now 1234
```

##Bitwise Inclusive OR Operator

**Syntax**

<pre>
  <i>bitwise-inc-OR-expression:</i>
    <i>bitwise-exc-OR-expression</i>
    <i>bitwise-inc-OR-expression</i>  |  <i>bitwise-exc-OR-expression</i>
</pre>

**Defined elsewhere**

* [*bitwise-exc-OR-expression*](#bitwise-exclusive-or-operator)

**Constraints**

Each of the operands must have scalar-compatible type.

**Semantics**

If either of the operands is an object supporting the operation, the result is
defined by that object's semantics, with the left operand checked first.

If either operand does not have type `int`, its value is first converted to
that type.  If either or both operands were leading-numeric or non-numeric
strings, a non-fatal error MUST be produced for each.

The result of this operator is the bitwise inclusive-OR of the two
operands, and the type of that result is `int`.

However, if both operands are strings, the result is the string composed of the sequence of bytes
that are the result of bitwise OR operation performed on the bytes of the operand strings
in the matching positions (`result[0] = s1[0] | s2[0]`, etc.).
If one of the strings is shorter than the other, it is extended with zero bytes.

This operator associates left-to-right.

**Examples**

```PHP
0b101111 | 0b101      // 0b101111
$uLetter = 0x41;      // letter 'A'
$lLetter = $upCaseLetter | 0x20;  // set the 6th bit to make letter 'a'
```

##Logical AND Operator (form 1)

**Syntax**

<pre>
  <i>logical-AND-expression-1:</i>
    <i>bitwise-incl-OR-expression</i>
    <i>logical-AND-expression-1</i>  &&  <i>bitwise-inc-OR-expression</i>
</pre>

**Defined elsewhere**

* [*bitwise-incl-OR-expression*](#bitwise-inclusive-or-operator)

**Semantics**

Given the expression `e1 && e2`, `e1` is evaluated first. If `e1` [converts to `bool`](08-conversions.md#converting-to-boolean-type) as `FALSE`, `e2` is not evaluated, and the result has type `bool`, value `FALSE`. Otherwise, `e2` is evaluated. If `e2` converts to `bool` as `FALSE`, the result has type `bool`, value `FALSE`; otherwise, it has type `bool`, value `TRUE`. There is a sequence point after the evaluation of `e1`.

This operator associates left-to-right.

Except for the difference in precedence, operator `&&` has exactly the
same semantics as operator [`and`](#logical-and-operator-form-2).

**Examples**

```PHP
if ($month > 1 && $month <= 12) ...
```

##Logical Inclusive OR Operator (form 1)

**Syntax**

<pre>
  <i>logical-inc-OR-expression-1:</i>
    <i>logical-AND-expression-1</i>
    <i>logical-inc-OR-expression-1</i>  ||  <i>logical-AND-expression-1</i>
</pre>

**Defined elsewhere**

* [*logical-exc-OR-expression*](#bitwise-exclusive-or-operator)

**Semantics**

Given the expression `e1 || e2`, `e1` is evaluated first. If `e1` [converts to `bool`](08-conversions.md#converting-to-boolean-type) as `TRUE`, `e2` is not evaluated, and the result has type `bool`, value `TRUE`. Otherwise, `e2` is evaluated. If `e2` converts to `bool` as `TRUE`, the result has type `bool`, value `TRUE`; otherwise, it has type `bool`, value `FALSE`. There is a sequence point after the evaluation of `e1`.

This operator associates left-to-right.

**Examples**

```PHP
if ($month < 1 || $month > 12) ...
```

##Conditional Operator

**Syntax**

<pre>
  <i>conditional-expression:</i>
    <i>logical-inc-OR-expression-1</i>
    <i>logical-inc-OR-expression-1</i>  ?  <i>expression<sub>opt</sub></i>  :  <i>conditional-expression</i>
</pre>

**Defined elsewhere**

* [*logical-OR-expression*](#logical-inclusive-or-operator-form-1)
* [*expression*](#general-6)

**Semantics**
Given the expression `e1 ? e2 : e3`, `e1` is evaluated first and [converted to `bool`](08-conversions.md#converting-to-boolean-type) if it has another type.
If the result is `TRUE`, then and only then is `e2` evaluated, and the result and its type become the result and type of
the whole expression. Otherwise, then and only then is `e3` evaluated, and
the result and its type become the result and type of the whole
expression. There is a sequence point after the evaluation of `e1`. If `e2`
is omitted, the result and type of the whole expression is the value and
type of `e1` (before the conversion to `bool`).

This operator associates left-to-right.

**Examples**

```PHP
for ($i = -5; $i <= 5; ++$i)
  echo "$i is ".(($i & 1 == TRUE) ? "odd\n" : "even\n");
// -----------------------------------------
$a = 10 ? : "Hello";  // result is int with value 10
$a = 0 ? : "Hello";     // result is string with value "Hello"
$i = PHP_INT_MAX;
$a = $i++ ? : "red";  // result is int with value 2147483647 (on a 32-bit
                // system) even though $i is now the float 2147483648.0
// -----------------------------------------
$i++ ? f($i) : f(++$i); // the sequence point makes this well-defined
// -----------------------------------------
function factorial($int)
{
  return ($int > 1) ? $int * factorial($int - 1) : $int;
}
```

##Coalesce Operator

**Syntax**

<pre>
  <i>coalesce-expression:</i>
    <i>logical-inc-OR-expression</i>  ??  <i>expression</i>
</pre>

**Defined elsewhere**

* [*logical-OR-expression*](#logical-inclusive-or-operator-form-1)
* [*expression*](#general-6)

**Semantics**

Given the expression `e1 ?? e2`, if `e1` is set and not `NULL` (i.e. TRUE for
[isset](#isset)), then the result is `e1`. Otherwise, then and only then is `e2`
evaluated, and the result becomes the result of the whole
expression. There is a sequence point after the evaluation of `e1`.

Note that the semantics of `??` is similar to `isset` so that uninitialized variables will not produce
warnings when used in `e1`.

This operator associates right-to-left.

**Examples**

```PHP
$arr = ["foo" => "bar", "qux" => NULL];
$obj = (object)$arr;

$a = $arr["foo"] ?? "bang"; // "bar" as $arr["foo"] is set and not NULL
$a = $arr["qux"] ?? "bang"; // "bang" as $arr["qux"] is NULL
$a = $arr["bing"] ?? "bang"; // "bang" as $arr["bing"] is not set

$a = $obj->foo ?? "bang"; // "bar" as $obj->foo is set and not NULL
$a = $obj->qux ?? "bang"; // "bang" as $obj->qux is NULL
$a = $obj->bing ?? "bang"; // "bang" as $obj->bing is not set

$a = NULL ?? $arr["bing"] ?? 2; // 2 as NULL is NULL, and $arr["bing"] is not set

function foo() {
    echo "executed!", PHP_EOL;
}
var_dump(true ?? foo()); // outputs bool(true), "executed!" does not appear as it short-circuits
```

##Assignment Operators

###General

**Syntax**

<pre>
  <i>assignment-expression:</i>
    <i>conditional-expression</i>
    <i>coalesce-expression</i>
    <i>simple-assignment-expression</i>
    <i>byref-assignment-expression</i>
    <i>compound-assignment-expression</i>
</pre>

**Defined elsewhere**

* [*conditional-expression*](#conditional-operator)
* [*simple-assignment-expression*](#simple-assignment)
* [*byref-assignment-expression*](#byref-assignment)
* [*compound-assignment-expression*](#compound-assignment)
* [*coalesce-expression*](#coalesce-operator)

**Constraints**

The left-hand operand of an assignment operator must be a modifiable
lvalue.

**Semantics**

These operators associate right-to-left.

###Simple Assignment

**Syntax**

<pre>
  <i>simple-assignment-expression:</i>
    <i>unary-expression</i>  =  <i>assignment-expression</i>
</pre>

**Defined elsewhere**

* [*unary-expression*](#general-4)
* [*assignment-expression*](#general-5)

**Constraints**

If the location designated by the left-hand operand is a string element,
the key must not be a negative-valued `int`, and the right-hand operand
must have type `string`.

**Semantics**

If *assignment-expression* designates an expression having value type,
see  [assignment for scalar types](04-basic-concepts.md#byref-assignment-for-scalar-types-with-local-variables)
If *assignment-expression* designates an expression having handle type, see [assignment for object and resource types](04-basic-concepts.md#value-assignment-of-object-and-resource-types-to-a-local-variable).
If *assignment-expression* designates an expression having array type, see
[assignment of array types](04-basic-concepts.md#value-assignment-of-array-types-to-local-variables).

The type and value of the result is the type and value of the left-hand
operand after the store (if any [see below]) has taken place. The result
is not an lvalue.

If the location designated by the left-hand operand is a non-existent
array element, a new element is inserted with the designated key and
with a value being that of the right-hand operand.

If the location designated by the left-hand operand is a string element,
then if the key is a negative-valued `int`, there is no side effect.
Otherwise, if the key is a non-negative-valued `int`, the left-most single
character from the right-hand operand is stored at the designated
location; all other characters in the right-hand operand string are
ignored.  If the designated location is beyond the end of the
destination string, that string is extended to the new length with
spaces (U+0020) added as padding beyond the old end and before the newly
added character. If the right-hand operand is an empty string, the null
character \\0 (U+0000) is stored.

**Examples**

```PHP
$a = $b = 10    // equivalent to $a = ($b = 10)
$v = array(10, 20, 30);
$v[1] = 1.234;    // change the value (and type) of an existing element
$v[-10] = 19;   // insert a new element with int key -10
$v["red"] = TRUE; // insert a new element with string key "red"
$s = "red";
$s[1] = "X";    // OK; "e" -> "X"
$s[-5] = "Y";   // warning; string unchanged
$s[5] = "Z";    // extends string with "Z", padding with spaces in [3]-[5]
$s = "red";
$s[0] = "DEF";    // "r" -> "D"; only 1 char changed; "EF" ignored
$s[0] = "";       // "D" -> "\0"
$s["zz"] = "Q";   // warning; defaults to [0], and "Q" is stored there
// -----------------------------------------
class C { ... }
$a = new C; // make $a point to the allocated object
```

###byRef Assignment

**Syntax**

<pre>
  <i>byref-assignment-expression:</i>
    <i>unary-expression</i>  =  &  <i>assignment-expression</i>
</pre>

**Defined elsewhere**

* [*unary-expression*](#general-4)
* [*assignment-expression*](#general-5)

**Constraints**

*unary-expression* must designate a variable.

*assignment-expression* must be an lvalue or a call to a function that
returns a value byRef.

**Semantics**

*unary-expression* becomes an alias for *assignment-expression*.
If *assignment-expression* designates an expression having value type,
see  [byRef assignment for scalar types](04-basic-concepts.md#byref-assignment-for-scalar-types-with-local-variables)
If *assignment-expression* designates an expression having handle type, see [byRef assignment for non-scalar types](04-basic-concepts.md#byref-assignment-of-non-scalar-types-with-local-variables).
If *assignment-expression* designates an expression having array type, see
[deferred array copying](04-basic-concepts.md#deferred-array-copying).

**Examples**

```PHP
$a = 10;
$b =& $a;   // make $b an alias of $a
++$a;       // increment $a/$b to 11
$b = -12;   // sets $a/$b to -12
$a = "abc";     // sets $a/$b to "abc"
unset($b);      // removes $b's alias to $a
// -----------------------------------------
function &g2() { $t = "xxx"; return $t; } // return byRef
$b =& g2();     // make $b an alias to "xxx"
```

##Compound Assignment

**Syntax**

<pre>
  <i>compound-assignment-expression:</i>
    <i>unary-expression   compound-assignment-operator   assignment-expression</i>

  <i>compound-assignment-operator: one of</i>
    **=  *=  /=  %=  +=  -=  .=  <<=  >>=  &=  ^=  |=
</pre>

**Defined elsewhere**

* [*unary-expression*](#general-4)
* [*assignment-expression*](#general-5)

**Constraints**

Any constraints that apply to the corresponding binary operator apply to the compound-assignment form as well.

**Semantics**

The expression `e1 op= e2` is equivalent to `e1 = e1 op (e2)`, except
that `e1` is evaluated only once.

**Examples**

```PHP
$v = 10;
$v += 20;   // $v = 30
$v -= 5;    // $v = 25
$v .= 123.45  // $v = "25123.45"
$a = [100, 200, 300];
$i = 1;
$a[$i++] += 50; // $a[1] = 250, $i → 2
```

##Logical AND Operator (form 2)

**Syntax**

<pre>
  <i>logical-AND-expression-2:</i>
    <i>assignment-expression</i>
    <i>logical-AND-expression-2</i>  and  <i>assignment-expression</i>
</pre>

**Defined elsewhere**

* [*assignment-expression*](#general-5)

**Semantics**

Except for the difference in precedence, operator and has exactly the
same semantics as [operator `&&`](#logical-and-operator-form-1).

##Logical Exclusive OR Operator

**Syntax**

<pre>
  <i>logical-exc-OR-expression:</i>
    <i>logical-AND-expression-2</i>
    <i>logical-exc-OR-expression</i>  xor  <i>logical-AND-expression-2</i>
</pre>

**Defined elsewhere**

* [*logical-AND-expression*](#logical-and-operator-form-2)

**Semantics**

If either operand does not have type `bool`, its value is first converted
to that type.

Given the expression `e1 xor e2`, `e1` is evaluated first, then `e2`. If
either `e1` or `e2` [converted to `bool`](08-conversions.md#converting-to-boolean-type) as `TRUE`, but not both, the result has type `bool`, value
`TRUE`. Otherwise, the result has type `bool`, value `FALSE`. There is a
sequence point after the evaluation of `e1`.

This operator associates left-to-right.

**Examples**

```PHP
f($i++) xor g($i) // the sequence point makes this well-defined
```

##Logical Inclusive OR Operator (form 2)

**Syntax**

<pre>
  <i>logical-inc-OR-expression-2:</i>
    <i>logical-exc-OR-expression</i>
    <i>logical-inc-OR-expression-2</i>  or  <i>logical-exc-OR-expression</i>
</pre>

**Defined elsewhere**

* [*logical-exc-OR-expression*](#logical-exclusive-or-operator)

**Semantics**

Except for the difference in precedence, operator and has exactly the
same semantics as [operator `||`](#logical-inclusive-or-operator-form-1).

## `yield` Operator

**Syntax**

<pre>
  <i>yield-expression:</i>
    <i>logical-inc-OR-expression-2</i>
    yield  <i>array-element-initializer</i>
    yield from  <i>expression</i>
</pre>

**Defined elsewhere**

* [*logical-inc-OR-expression*](#logical-inclusive-or-operator-form-2)
* [*array-element-initializer*](#array-creation-operator)
* [*expression*](#script-inclusion-operators)

**Semantics**

Any function containing a *yield-expression* is a *generator function*.
A generator function generates a collection of zero or more key/value
pairs where each pair represents the next in some series. For example, a
generator might *yield* random numbers or the series of Fibonacci
numbers. When a generator function is called explicitly, it returns an
object of type [`Generator`](14-classes.md#class-generator), which implements the interface
[`Iterator`](15-interfaces.md#interface-iterator). As such, this allows that object to be iterated over
using the [`foreach` statement](11-statements.md#the-foreach-statement). During each iteration, the Engine
calls the generator function implicitly to get the next key/value pair.
Then the Engine saves the state of the generator for subsequent key/value pair requests.

The `yield` operator produces the result `NULL` unless the method
[`Generator->send`](14-classes.md#class-generator) was called to provide a result value. This
operator has the side effect of generating the next value in the collection.

Before being used, an *element-key* must have, or be converted to, type
`int` or `string`. Keys with `float` or `bool` values, or numeric strings, are
[converted to `int`](08-conversions.md#converting-to-integer-type). Values of all other key types are [converted to
`string`](08-conversions.md#converting-to-string-type).

If *element-key* is omitted from an *array-element-initializer*, an
element key of type `int` is associated with the corresponding
*element-value*. The key associated is one more than the previously
assigned int key for this collection. However, if this is the first
element in this collection with an `int` key, key zero is used. If
*element-key* is provided, it is associated with the corresponding
*element-value*. The resulting key/value pair is made available by
`yield`.

If *array-element-initializer* is omitted, default int-key assignment is
used and each value is `NULL`.

If the generator function definition declares that it returns byRef,
each value in a key/value pair is yielded byRef.

The following applies only to the `yield from` form:

A generator function (referred to as a *delegating generator*) can delegate to another generator function (referred to as a *subgenerator*), a Traversable object, or an array, each of which is designated by *expression*.

Each value yielded by *expression* is passed directly to the delegating generator's caller.

Each value sent to the delegating generator's `send` method is passed to the subgenerator's `send` method. If *expression* is not a generator function, any sent values are ignored.

Exceptions thrown by *expression* are propagated up to the delegating generator.

Upon traversable completion, `NULL` is returned to the delegating generator if the traversable is not a generator. If the traversable is a generator, its return value is sent to the delegating generator as the value of the `yield from` *expression*.

An exception of type `Error` is thrown if *expression* evaluates to a generator that previously terminated with an uncaught exception, or it evaluates to something that is neither Traversable nor an array.

**Examples**

```PHP
function getTextFileLines($filename)
{
  $infile = fopen($filename, 'r');
  if ($infile == FALSE) { /* deal with the file-open failure */ }

  try
  {
    while ($textLine = fgets($infile))  // while not EOF
    {
      $textLine = rtrim($textLine, "\r\n"); // strip off terminator
      yield $textLine;
    }
  }
  finally
  {
    fclose($infile);
  }
}
foreach (getTextFileLines("Testfile.txt") as $line) { /* process each line */ }
// -----------------------------------------
function series($start, $end, $keyPrefix = "")
{
  for ($i = $start; $i <= $end; ++$i)
  {
    yield $keyPrefix . $i => $i;  // generate a key/value pair
  }
}
foreach (series(1, 5, "X") as $key => $val) { /* process each key/val pair */ }
// -----------------------------------------
function gen()
{
    yield 1;
    yield from gen2();
    yield 4;
}
function gen2()
{
    yield 2;
    yield 3;
}
foreach (gen() as $val)
{
    echo $val . "\n";   // Produces the values 1, 2, 3, and 4
}
// -----------------------------------------
function g() {
  yield 1;
  yield from [2, 3];
  yield 4;
}
$g = g();
foreach ($g as $yielded) {
    echo $yielded . "\n";   // Produces the values 1, 2, 3, and 4
}
```

##Script Inclusion Operators

###General

**Syntax**

<pre>
  <i>expression:</i>
    <i>yield-expression</i>
    <i>include-expression</i>
    <i>include-once-expression</i>
    <i>require-expression</i>
    <i>require-once-expression</i>
</pre>

**Defined elsewhere**

* [*yield-expression*](#yield-operator)
* [*include-expression*](#the-include-operator)
* [*include-once-expression*](#the-include_once-operator)
* [*require-expression*](#the-require-operator)
* [*require-once-expression*](#the-require_once-operator)

**Semantics**

When creating large applications or building component libraries, it is
useful to be able to break up the source code into small, manageable
pieces each of which performs some specific task, and which can be
shared somehow, and tested, maintained, and deployed individually. For
example, a programmer might define a series of useful constants and use
them in numerous and possibly unrelated applications. Likewise, a set of
class definitions can be shared among numerous applications needing to
create objects of those types.

An *include file* is a script that is suitable for *inclusion* by
another script. The script doing the including is the *including file*,
while the one being included is the *included file*. A script can be an
including file and an included file, either, or neither.

Using the series-of-constants example, an include file called
`Positions.php` might define the constants `TOP`, `BOTTOM`, `LEFT`, and `RIGHT`,
in their own [namespace](18-namespaces.md#general), Positions. Using the set-of-classes
example, to support two-dimensional geometry applications, an include
file called `Point.php` might define the class `Point`. An include file
called `Line.php` might define the class Line (where a `Line` is represented
as a pair of Points).An include file, called `Circle.php` might define the
class `Circle` (where a `Circle` is represented as a `Point` for the origin,
and a radius).

If a number of the scripts making up an application each use one or more
of the Position constants, they can each include the corresponding
include file via the [`include` operator](#the-include-operator). However, most include
files behave the same way each time they are included, so it is
generally a waste of time including the same include file more than once
into the same scope. In the case of the geometry example, any attempt to
include the same include file more than once will result in a fatal
"attempted class type redefinition" error. However, this can be avoided
by using the [`include_once` operator](#the-include_once-operator) instead.

The [`require` operator](#the-require-operator) is a variant of the `include` operator,
and the [`require_once` operator](#the-require_once-operator) is a variant of the
`include_once` operator.

It is important to understand that unlike the C/C++ (or similar)
preprocessor, script inclusion in PHP is not a text substitution
process. That is, the contents of an included file are not treated as if
they directly replaced the inclusion operation source in the including
file. See examples below for more information.

An inclusion expression can be written to look like a function call;
however, that is not the case, even though an included file can return a
value to its including file.

The name used to specify an include file may contain an absolute or
relative path. In the latter case, an implementation may use the
configuration directive
[`include_path`](http://www.php.net/manual/ini.core.php#ini.include-path)
 to resolve the include file's location.

**Examples:**

As mentioned above, script inclusion in PHP is not a text substitution process (unlike C/C++\'s preprocessor and alike). This allows that one can specify namespaces in the included file even though nested namespaces in a single file only are not permitted:

include.php
````
namespace foo;
$x = 'hello';
foo();
````

index.php
```
namespace bar {
  include 'include.php'; // this is fine does not result in a nested namespace
  echo $x;               // hello
  \foo\foo();            // function foo is still member of the foo namespace

  //namespace baz{}      // would fail, nesting namespaces are not allowed
}
```


Moreover, nested classes in a single file are not permitted whereas classes defined in an included file does not result in a nested class (in a conditionally defined class though) - the same applies for nested interfaces or traits:

include.php
````
namespace foo;
class Foo{}
````

index.php
````
class Bar{
  function bar(){
    include 'include.php'; // this is fine, does not result in a nested class
  }
  //class Foo1{}       // would fail, nested classes are not allowed
  //interface Foo2{}   // would fail as well
  //trait Foo3{}       // and would fail as well
}
new Foo();             // fails, \Foo could not be found
new \foo\Foo();        // fails, definition for class Foo was not loaded yet
$bar = new Bar();
$bar->bar();
new Foo();             // still fails, include != use statement
new \foo\Foo();        // succeeds, definition for class Foo was loaded
````


[c-constants](06-constants.md#general) can not be defined within a function or method (in contrast to [d-constants](06-constants.md#general). As in the other examples above, this is perfectly legal when it happens through a file inclusion in which the constant does not lose its scope. Consider the following example:

include.php
````
namespace foo;
const X = 2;
````

index.php
````
class Bar{
  function bar(){
    include 'include.php';
  }
}
echo X;                // emits a notice: Use of undefined constant X ...
echo \foo\X;           // same as above since the inclusion did not happen yet
$bar = new Bar();
$bar->bar();
echo X;                // still fails, include != use statement
echo \foo\X;           // succeeds, X was defined through the inclusion
````


In contrast to constants, functions, classes, interfaces and traits, variables defined at the top level of a file might change their meaning (being a global variable) when the corresponding file is included by another file. This is the case when the inclusion happens in a local scope. In this case the variables become local variables of the corresponding scope. Following an example as illustration:

include.php
````
namespace foo;
$x = 'hello';
````

index.php
````
function bar(){
  include 'include.php';  // introduces the local variable $x
  $x = 'hi';              // modification is only local
  return $x;
}
echo bar();               // hi
echo $x;                  // emits a notice: Undefined variable: x ...

include 'include.php';    // introduces the global variable $x
echo $x;                  // hello
````


###The `include` Operator

**Syntax**

<pre>
  <i>include-expression:</i>
    include  (  <i>expression</i>  )
    include  <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Constraints**

*expresssion* must be convertable to a string, which designates
a filename.

**Semantics**

Operator `include` results in parsing and executing the designated include
file. If the filename is invalid or does not specify a readable
file, a non-fatal error is produced.

When an included file is opened, parsing begins in HTML mode at the beginning of the file.
After the included file has been parsed, it is immediately executed.

Variables defined in an included file take on scope of the source line
on which the inclusion occurs in the including file. However, functions
and classes defined in the included file are always in global scope.

If inclusion occurs inside a function definition within the including
file, the complete contents of the included file are treated as though
it were defined inside that function.

The result produced by this operator is one of the following:
  1. If the included file [returned any value](11-statements.md#the-return-statement), that value is the result.
  2. If the included file has not returned any value, the result is the integer `1`.
  3. If the inclusion failed for any reason, the result is `FALSE`.

The library function [`get_included_files`](http://php.net/manual/function.get-included-files.php) provides the names of
all files included by any of the four including operators.

**Examples:**

```
$fileName = 'limits' . '.php'; include $fileName;
$inc = include('limits.php');
If ((include 'Positions.php') == 1) ...
```

###The `include_once` Operator

**Syntax**

<pre>
  <i>include-once-expression:</i>
    include_once  (  <i>expression</i>  )
    include_once  <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Semantics**

This operator is identical to operator [`include`](#the-include-operator) except that in
the case of `include_once`, the same include file is included once per
program execution.

Once an include file has been included, a subsequent use of
`include_once` on that include file results in a return value of `TRUE` but nothing else
happens.

The files are identified by the full pathname, so different forms of the filename (such as full
and relative path) still are considered the same file.

**Examples:**

Point.php:

```
\\ Point.php:
<?php ...
class Point { ... }

\\ Circle.php:
<?php ...
include_once 'Point.php';
class Circle { /* uses Point somehow */ }

\\ MyApp.php
include_once 'Point.php';   // Point.php included directly
include_once 'Circle.php';    // Point.php now not included indirectly
$p1 = new Point(10, 20);
$c1 = new Circle(9, 7, 2.4);
```

###The `require` Operator

**Syntax**

<pre>
  <i>require-expression:</i>
    require  (  <i>expression</i>  )
    require  <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Semantics**

This operator is identical to operator [`include`](#the-include-operator) except that in
the case of `require`, failure to find/open the designated include file
produces a fatal error.

###The `require_once` Operator

**Syntax**

<pre>
  <i>require-once-expression:</i>
    require_once  (  <i>expression</i>  )
    require_once  <i>expression</i>
</pre>

**Defined elsewhere**

* [*expression*](#general-6)

**Semantics**

This operator is identical to operator [`require`](#the-require-operator) except that in
the case of `require_once`, the include file is included once per
program execution.

Once an include file has been included, a subsequent use of
`require_once` on that include file results in a return value of `TRUE` but nothing else
happens.

The files are identified by the full pathname, so different forms of the filename (such as full
and relative path) still are considered the same file.

##Constant Expressions

**Syntax**

<pre>
  <i>constant-expression:</i>
    <i>array-creation-expression</i>
    <i>expression</i>
</pre>

**Defined elsewhere**

* [*array-creation-expression*](#array-creation-operator)
* [*expression*](#general-6)

**Constraints**

All of the *element-key* and *element-value* elements in
[*array-creation-expression*](#array-creation-operator) must be literals.

*expression* must have a scalar type, and be a literal or the name of a [c-constant](06-constants.md#general).

**Semantics**

A *constant-expression* is the value of a c-constant. A *constant-expression*
is required in several contexts, such as in initializer values in a
[*const-declaration*](14-classes.md#constants) and default initial values in a [function
definition](13-functions.md#function-definitions).
