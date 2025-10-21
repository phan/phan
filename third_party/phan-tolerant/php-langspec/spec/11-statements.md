#Statements

##General

**Syntax**

<pre>

  <i>statement:</i>
    <i>compound-statement</i>
    <i>labeled-statement</i>
    <i>expression-statement</i>
    <i>selection-statement</i>
    <i>iteration-statement</i>
    <i>jump-statement</i>
    <i>try-statement</i>
    <i>declare-statement</i>
    <i>const-declaration</i>
    <i>function-definition</i>
    <i>class-declaration</i>
    <i>interface-declaration</i>
    <i>trait-declaration</i>
    <i>namespace-definition</i>
    <i>namespace-use-declaration</i>
    <i>global-declaration</i>
    <i>function-static-declaration</i>
</pre>

**Defined elsewhere**

* [*compound-statement*](#compound-statements)
* [*labeled-statement*](#labeled-statements)
* [*expression-statement*](#expression-statements)
* [*selection-statement*](#general-1)
* [*iteration-statement*](#general-2)
* [*jump-statement*](#general-3)
* [*declare-statement*](#the-declare-statement)
* [*const-declaration*](14-classes.md#constants)
* [*function-definition*](13-functions.md#function-definitions)
* [*class-declaration*](14-classes.md#class-declarations)
* [*interface-declaration*](15-interfaces.md#interface-declarations)
* [*trait-declaration*](16-traits.md#trait-declarations)
* [*namespace-definition*](18-namespaces.md#defining-namespaces)
* [*namespace-use-declaration*](18-namespaces.md#namespace-use-declarations)
* [*global-declaration*](07-variables.md#global-variables)
* [*function-static-declaration*](07-variables.md#function-statics)

##Compound Statements

**Syntax**

<pre>
  <i>compound-statement:</i>
    {   <i>statement-list<sub>opt</sub></i>  }

  <i>statement-list:</i>
    <i>statement</i>
    <i>statement-list   statement</i>
</pre>

**Defined elsewhere**

* [*statement*](#general)

**Semantics**

A *compound statement* allows a group of zero or more statements to be
treated syntactically as a single statement. A compound statement is
often referred to as a *block*.

**Examples**

```PHP
if (condition)
{ // braces are needed as the true path has more than one statement
  // statement-1
  // statement-2
}
else
{ // braces are optional as the false path has only one statement
  // statement-3
}
// -----------------------------------------
while (condition)
{ // the empty block is equivalent to a null statement
}
```

##Labeled Statements

**Syntax**

<pre>
  <i>labeled-statement:</i>
    <i>named-label-statement</i>
    <i>case-statement</i>
    <i>default-statement</i>

  <i>named-label-statement:</i>
    <i>name</i>  :  <i>statement</i>

  <i>case-statement:</i>
    case   <i>expression   case-default-label-terminator   statement</i>

  <i>default-statement:</i>
    default  <i>case-default-label-terminator   statement</i>

  <i>case-default-label-terminator:</i>
    :
    ;
</pre>

**Defined elsewhere**

* [*name*](09-lexical-structure.md#names)
* [*statement*](#general)
* [*expression*](10-expressions.md#general-6)

**Constraints**

A named label can be used as the target of a [`goto` statement](#the-goto-statement).

Named labels must be unique within a function.

A case and default labeled statements must only occur inside a [`switch` statement](#the-switch-statement).

**Semantics**

Any statement may be preceded by a token sequence that declares a name
as a label name. The presence of a label does not alter the ﬂow of
execution.

##Expression Statements

**Syntax**

<pre>
   <i>expression-statement:</i>
     <i>expression<sub>opt</sub></i>  ;
</pre>

**Defined elsewhere**

* [*expression*](10-expressions.md#general-6)

**Semantics**

If present, *expression* is evaluated for its side effects, if any, and
any resulting value is discarded. If *expression* is omitted, the
statement is a *null statement*, which has no effect on execution.

**Examples**

```PHP
$i = 10;  // $i is assigned the value 10; result (10) is discarded
++$i; // $i is incremented; result (11) is discarded
$i++; // $i is incremented; result (11) is discarded
DoIt(); // function DoIt is called; result (return value) is discarded
// -----------------------------------------
$i;   // no side effects, result is discarded. Vacuous but permitted
123;  // likewise for this one and the two statements following
34.5 * 12.6 + 11.987;
TRUE;
// -----------------------------------------
function findValue($table, $value)  // where $table is 2x3 array
{
  for ($row = 0; $row <= 1; ++$row)
  {
    for ($colm = 0; $colm <= 2; ++$colm)
    {
      if ($table[$row][$colm] == $value)
      {
        // ...
        goto done;
      }
    }
  }
  // ...
done:
  ;     // null statement needed as a label must precede a statement
}
```

##Selection Statements

###General

**Syntax**

<pre>
  <i>selection-statement:</i>
    <i>if-statement</i>
    <i>switch-statement</i>
</pre>

**Defined elsewhere**

* [*if-statement*](#the-if-statement)
* [*switch-statement*](#the-switch-statement)

**Semantics**

Based on the value of a controlling expression, a selection statement
selects among a set of statements.

###The `if` Statement

**Syntax**

<pre>
  <i>if-statement:</i>
    if   (   <i>expression</i>   )   <i>statement   elseif-clauses-1<sub>opt</sub>   else-clause-1<sub>opt</sub></i>
    if   (   <i>expression</i>   )   :   <i>statement-list   elseif-clauses-2<sub>opt</sub>   else-clause-2<sub>opt</sub></i>   endif   ;

  <i>elseif-clauses-1:</i>
    <i>elseif-clause-1</i>
    <i>elseif-clauses-1   elseif-clause-1</i>

  <i>elseif-clause-1:</i>
    elseif   (   <i>expression</i>   )   <i>statement</i>

  <i>else-clause-1:</i>
    else   <i>statement</i>

  <i>elseif-clauses-2:</i>
    <i>elseif-clause-2</i>
    <i>elseif-clauses-2   elseif-clause-2</i>

  <i>elseif-clause-2:</i>
    elseif   (   <i>expression</i>   )   :   <i>statement-list</i>

  <i>else-clause-2:</i>
    else   :   <i>statement-list</i>
</pre>

**Defined elsewhere**

* [*expression*](10-expressions.md#general-6)
* [*statement*](#general)
* [*statement-list*](#compound-statements)

**Semantics**

The two forms of the `if` statement are equivalent; they simply provide
alternate styles.

The result of the controlling expression *expression* will be [converted to type `bool`](08-conversions.md#converting-to-boolean-type)
if it does not have this type.

If *expression* is `TRUE`, the *statement* that follows immediately is
executed. Otherwise, if an `elseif` clause is present its *expression* is evaluated
in turn, and if it is `TRUE`, the *statement* immediately following the `elseif` is executed.
This repeats for every `elseif` clause in turn. If none of those tests `TRUE`, if an
`else` clause is present the *statement* immediately following the `else` is
executed.

An `else` clause is associated with the lexically nearest preceding `if` or
`elseif` that is permitted by the syntax.

**Examples**
```PHP
if ($count > 0)
{
  ...
  ...
  ...
}
// -----------------------------------------
goto label1;
echo "Unreachable code\n";

if ($a)
{
label1:
  ...
}
else
{
  ...
}
// -----------------------------------------
if (1)
  ...
  if (0)
    ...
else  // this else does NOT go with the outer if
  ...

if (1)
{
  ...
  if (0)
    ...
}
else  // this else does go with the outer if
    ...
```

###The `switch` Statement

**Syntax**

<pre>
  <i>switch-statement:</i>
    switch  (  <i>expression</i>  )  { <i>case-statements<sub>opt</sub></i> }
    switch  (  <i>expression</i>  )  :   <i>case-statements<sub>opt</sub></i>  endswitch;

  <i>case-statements:</i>
    <i>case-statement</i> <i>statement-list<sub>opt</sub></i> <i>case-statements<sub>opt</sub></i>
    <i>default-statement</i> <i>statement-list<sub>opt</sub></i> <i>case-statements<sub>opt</sub></i>
</pre>

**Defined elsewhere**

* [*expression*](10-expressions.md#general-6)
* [*case-statement*](#labeled-statements)
* [*default-statement*](#labeled-statements)
* [*compound-statement*](#compound-statements)
* [*statement-list*](#compound-statements)

**Constraints**

There must be at most one default label.

**Semantics**

The two forms of the `switch` statement are equivalent; they simply
provide alternate styles.

Based on the value of its *expression*, a `switch` statement transfers
control to a [case label](#labeled-statements), to a [default label](#labeled-statements), if one
exists; or to the statement immediately following the end of the `switch`
statement. A case or default label is only reachable directly within its
closest enclosing `switch` statement.

On entry to the `switch` statement, the controlling expression is
evaluated and then compared with the value of the case label *expression*
values, in lexical order, using the same semantics as `==`.
If one matches, control transfers to the
statement following the corresponding case label. If there is no match,
then if there is a default label, control transfers to the statement
following that; otherwise, control transfers to the statement
immediately following the end of the `switch` statement. If a `switch`
contains more than one case label whose values compare equal to the
controlling expression, the first in lexical order is considered the
match.

An arbitrary number of statements can be associated with any case or
default label. In the absence of a [`break` statement](#the-break-statement) at the end
of a set of such statements, the execution continues into any following
statements, ignoring the associated labels. If all cases and the default end in `break`
and there are no duplicate-valued case labels, the order of case and
default labels is insignificant.

Case-label values can be runtime expressions, and the types of sibling
case-label values need not be the same.

Switches may be nested, in which case, each `switch` has its own set of
`switch` clauses.

**Examples**

```PHP
$v = 10;
switch ($v)
{
default:
  echo "default case: \$v is $v\n";
  break;    // break ends "group" of default statements
case 20:
  echo "case 20\n";
  break;    // break ends "group" of case 20 statements
case 10:
  echo "case 10\n"; // no break, so execution continues to the next label's "group"
case 30:
  echo "case 30\n"; // no break, but then none is really needed either
}
// -----------------------------------------
$v = 30;
switch ($v)
{
case 30.0:  // <===== this case matches with 30
  echo "case 30.0\n";
  break;
default:
  echo "default case: \$v is $v\n";
  break;
case 30:    // <===== rather than this case matching with 30
  echo "case 30\n";
  break;
}
// -----------------------------------------
switch ($v)
{
case 10 + $b: // non-constant expression
  // ...
case $v < $a:   // non-constant expression
  // ...
// ...
}
```

##Iteration Statements

###General

**Syntax**

<pre>
  <i>iteration-statement:</i>
    <i>while-statement</i>
    <i>do-statement</i>
    <i>for-statement</i>
    <i>foreach-statement</i>
</pre>

**Defined elsewhere**

* [*while-statement*](#the-while-statement)
* [*do-statement*](#the-do-statement)
* [*for-statement*](#the-for-statement)
* [*foreach-statement*](#the-foreach-statement)

##The `while` Statement

**Syntax**

<pre>
  <i>while-statement:</i>
    while  (  <i>expression</i>  )  <i>statement</i>
    while  (  <i>expression</i>  )  :   <i>statement-list</i>  endwhile ;
</pre>

**Defined elsewhere**

* [*expression*](10-expressions.md#general-6)
* [*statement*](#general)
* [*statement-list*](#compound-statements)

**Semantics**

The two forms of the `while` statement are equivalent; they simply provide
alternate styles.

The result of the controlling expression *expression* is [converted to type `bool`](08-conversions.md#converting-to-boolean-type)
if it does not have this type.

If *expression* tests `TRUE`, the *statement* that follows immediately is
executed, and the process is repeated. If *expression* tests `FALSE`,
control transfers to the point immediately following the end of the
`while` statement. The loop body is executed zero or more
times.

**Examples**

```PHP
$i = 1;
while ($i <= 10):
  echo "$i\t".($i * $i)."\n"; // output a table of squares
  ++$i;
endwhile;
// -----------------------------------------
while (TRUE)
{
  // ...
  if ($done)
    break;  // break out of the while loop
  // ...
}
```

##The `do` Statement

**Syntax**

<pre>
  <i>do-statement:</i>
    do  <i>statement</i>  while  (  <i>expression</i>  )  ;
</pre>

(Note: There is no alternate syntax).

**Defined elsewhere**

* [*statement*](#general)
* [*expression*](10-expressions.md#general-6)

**Constraints**

The controlling expression *expression* must have type `bool` or be
implicitly convertible to that type.

**Semantics**

First, *statement* is executed and then *expression* is evaluated.

The result of the controlling expression *expression* is [converted to type `bool`](08-conversions.md#converting-to-boolean-type)
if it does not have this type.

If the value tests `TRUE`, the process is repeated. If *expression* tests `FALSE`,
control transfers to the point immediately following the end of the `do`
statement. The loop body, *statement*, is executed one or more times.

**Examples**

```PHP
$i = 1;
do
{
  echo "$i\t".($i * $i)."\n"; // output a table of squares
  ++$i;
}
while ($i <= 10);
```

##The `for` Statement

**Syntax**

<pre>
  <i>for-statement:</i>
    for   (   <i>for-initializer<sub>opt</sub></i>   ;   <i>for-control<sub>opt</sub></i>   ;   <i>for-end-of-loop<sub>opt</sub></i>   )   <i>statement</i>
    for   (   <i>for-initializer<sub>opt</sub></i>   ;   <i>for-control<sub>opt</sub></i>   ;   <i>for-end-of-loop<sub>opt</sub></i>   )   :   <i>statement-list</i>   endfor   ;

  <i>for-initializer:</i>
    <i>for-expression-group</i>

  <i>for-control:</i>
    <i>for-expression-group</i>

  <i>for-end-of-loop:</i>
    <i>for-expression-group</i>

  <i>for-expression-group:</i>
    <i>expression</i>
    <i>for-expression-group</i>   ,   <i>expression</i>
</pre>

Note: Unlike C/C++, PHP does not support a comma operator, per se.
However, the syntax for the `for` statement has been extended from that of
C/C++ to achieve the same results in this context.

**Defined elsewhere**

* [*statement*](#general)
* [*statement-list*](#compound-statements)
* [*expression*](10-expressions.md#general-6)

**Semantics**

The two forms of the `for` statement are equivalent; they simply provide
alternate styles.

The group of expressions in *for-initializer* is evaluated once,
left-to-right, for their side effects. Then the group of expressions in
*for-control* is evaluated left-to-right (with all but the right-most
one for their side effects only), with the right-most expression's value
being [converted to type `bool`](08-conversions.md#converting-to-boolean-type).
If the result is `TRUE`, *statement* is executed, and the group
of expressions in *for-end-of-loop* is evaluated left-to-right, for
their side effects only. Then the process is repeated starting with
*for-control*. Once the right-most expression in *for-control* is
`FALSE`, control transfers to the point immediately following the end of
the `for` statement. The loop body, *statement*, is executed zero or more
times.

If *for-initializer* is omitted, no action is taken at the start of the
loop processing. If *for-control* is omitted, this is treated as if
*for-control* was an expression with the value `TRUE`. If
*for-end-of-loop* is omitted, no action is taken at the end of each
iteration.

**Examples**

```PHP
for ($i = 1; $i <= 10; ++$i)
{
  echo "$i\t".($i * $i)."\n"; // output a table of squares
}
// -----------------------------------------
// omit 1st and 3rd expressions

$i = 1;
for (; $i <= 10;):
  echo "$i\t".($i * $i)."\n"; // output a table of squares
  ++$i;
endfor;
// -----------------------------------------
// omit all 3 expressions

$i = 1;
for (;;)
{
  if ($i > 10)
    break;
  echo "$i\t".($i * $i)."\n"; // output a table of squares
  ++$i;
}
// -----------------------------------------
//  use groups of expressions

for ($a = 100, $i = 1; ++$i, $i <= 10; ++$i, $a -= 10)
{
  echo "$i\t$a\n";
}
```

##The `foreach` Statement

**Syntax**

<pre>
  <i>foreach-statement:</i>
    foreach  (  <i>foreach-collection-name</i>  as  <i>foreach-key<sub>opt</sub>  foreach-value</i>  )   statement
    foreach  (  <i>foreach-collection-name</i>  as  <i>foreach-key<sub>opt</sub>  foreach-value</i>  )  :  <i>statement-list</i>  endforeach  ;

  <i>foreach-collection-name</i>:
    <i>expression</i>

  <i>foreach-key:</i>
    <i>expression</i>  =>

  <i>foreach-value:</i>
    &<sub>opt</sub>   <i>expression</i>
    <i>list-intrinsic</i>
</pre>

**Defined elsewhere**

* [*statement*](#general)
* [*statement-list*](#compound-statements)
* [*list-intrinsic*](10-expressions.md#list)
* [*expression*](10-expressions.md#general-6)

**Constraints**

The result of the expression *foreach-collection-name* must be a
collection, i.e. either array or object implementing [Traversable](http://php.net/Traversable).

*expression* in *foreach-value* and *foreach-key* should designate a variable.

**Semantics**

The two forms of the `foreach` statement are equivalent; they simply
provide alternate styles.

The *foreach* statement iterates over the set of elements in the
collection designated by *foreach-collection-name*, starting at the
beginning, executing *statement* each iteration. On each iteration, if
the `&` is present in *foreach-value*, the variable designated by the
corresponding *expression* is made an alias to the current element. If
the `&` is omitted, the value of the current element is assigned to the
corresponding variable. The loop body, *statement*, is executed zero or
more times. After the loop terminates, the variable designated by *expression*
in *foreach-value* has the same value as it had after the final iteration, if any.

If *foreach-key* is present, the variable designated by its *expression*
is assigned the current element's key value.

In the *list-intrinsic* case, a value that is an array is split into
individual elements.

**Examples**

```PHP
$colors = array("red", "white", "blue");
foreach ($colors as $color):
    // ...
endforeach;
// -----------------------------------------
foreach ($colors as $key => $color)
{
    // ...
}
// -----------------------------------------
// Modify the local copy of an element's value

foreach ($colors as $color)
{
  $color = "black";
}
// -----------------------------------------
// Modify the the actual element itself

foreach ($colors as &$color)  // note the &
{
  $color = "black";
}
```

##Jump Statements

###General

**Syntax**

<pre>
  <i>jump-statement:</i>
    <i>goto-statement</i>
    <i>continue-statement</i>
    <i>break-statement</i>
    <i>return-statement</i>
    <i>throw-statement</i>
</pre>

**Defined elsewhere**

* [*goto-statement*](#the-goto-statement)
* [*continue-statement*](#the-continue-statement)
* [*break-statement*](#the-break-statement)
* [*return-statement*](#the-return-statement)
* [*throw-statement*](#the-throw-statement)

###The `goto` Statement

**Syntax**

<pre>
  <i>goto-statement:</i>
    goto  <i>name</i>  ;
</pre>

**Defined elsewhere**

* [*name*](09-lexical-structure.md#names)

**Constraints**

The name in a `goto` statement must be that of a [named label](#labeled-statements) located
somewhere in the current script. Control must not be transferred into or
out of a function, or into an [iteration statement](#iteration-statements) or a [`switch`
statement](#the-switch-statement).

A `goto` statement must not attempt to transfer control out of a
[finally-block](#the-try-statement).

**Semantics**

A `goto` statement transfers control unconditionally to the [named label](#labeled-statements).

A `goto` statement may break out of a construct that is fully contained
within a [finally-block](#the-try-statement).

**Examples**

```PHP
function findValue($table, $v)  // where $table is 2x3 array
{
  for ($row = 0; $row <= 1; ++$row)
  {
    for ($colm = 0; $colm <= 2; ++$colm)
    {
      if ($table[$row][$colm] == $v)
      {
        echo "$v was found at row $row, column $colm\n";
        goto done; // not quite the same as break 2!
      }
    }
  }
  echo "$v was not found\n";
done:
  ; // note that a label must always precede a statement
}
```

###The `continue` Statement

**Syntax**

<pre>
  <i>continue-statement:</i>
    continue   <i>breakout-level<sub>opt</sub></i>  ;

  <i>breakout-level:</i>
    <i>integer-literal</i>
</pre>

**Defined elsewhere**

* [*integer-literal*](09-lexical-structure.md#integer-literals)

**Constraints**

The breakout level must be greater than zero, and it must not exceed the level of
actual enclosing iteration and/or `switch` statements.

A `continue` statement must not attempt to break out of a [finally-block](#the-try-statement).

**Semantics**

A `continue` statement terminates the execution of the innermost enclosing
[iteration](#iteration-statements) or [`switch`](#the-switch-statement) statement.
*breakout-level* specifies which of these statements is targeted, with innermost being assigned
number `1` and containing statements having levels increasing by 1.

A `continue` statement terminates the execution of one or more enclosing
[iteration](#iteration-statements) or [`switch`](#the-switch-statement) statements,
up to the specified level. If the statement at the *breakout-level* is an iteration statement,
the next iteration (if any) of the next innermost enclosing iteration or switch statement is started.
If that statement is a `for` statement and it has a *for-end-of-loop*, its
end-of-loop expression group for the current iteration is evaluated first. If
*breakout-level* is omitted, a level of 1 is assumed.

A `continue` statement may break out of a construct that is fully
contained within a finally-block.

**Examples**

```PHP
for ($i = 1; $i <= 5; ++$i)
{
  if (($i % 2) == 0)
    continue;
  echo "$i is odd\n";
}
```

###The `break` Statement

**Syntax**

<pre>
  <i>break-statement:</i>
    break  <i>breakout-level<sub>opt</sub></i>  ;
</pre>

**Defined elsewhere**

* [*breakout-level*](#the-continue-statement)

**Constraints**

The breakout level must be greater than zero, and it must not exceed the level of
actual enclosing iteration and/or `switch` statements.

A `break` statement must not attempt to break out of a [finally-block](#the-try-statement).

**Semantics**

A `break` statement terminates the execution of one or more enclosing
[iteration](#iteration-statements) or []`switch`](#the-switch-statement) statements. The number of levels
broken out is specified by *breakout-level*. If *breakout-level* is
omitted, a level of 1 is assumed.

A `break` statement may break out of a construct that is fully contained
within a finally-block.

**Examples**

```PHP
$i = 1;
for (;;)
{
  if ($i > 10)
    break;
  // ...
  ++$i;
}
// -----------------------------------------
for ($row = 0; $row <= 1; ++$row)
{
  for ($colm = 0; $colm <= 2; ++$colm)
  {
    if (some-condition-set)
    {
      break 2;
    }
    // ...
  }
}
// -----------------------------------------
for ($i = 10; $i <= 40; $i +=10)
{
        switch($i)
        {
        case 10: /* ... */; break;    // breaks to the end of the switch
        case 20: /* ... */; break 2;  // breaks to the end of the for
        case 30: /* ... */; break;    // breaks to the end of the switch
        }
}
```

###The `return` Statement

**Syntax**

<pre>
  <i>return-statement:</i>
    return  <i>expression<sub>opt</sub></i>  ;
</pre>

**Defined elsewhere**

* [*expression*](10-expressions.md#general-6)

**Semantics**

A `return` statement from within a function terminates the execution of
that function normally, and depending on how the [function was defined](13-functions.md#function-calls),
it returns the value of *expression* to the function's caller
by value or byRef. If *expression* is omitted the value `NULL` is used.

If execution flows into the closing brace (`}`) of a function, `return
NULL;` is implied.

Explicit `return` statements with *expression* given are not permitted within a
function with a `void` [return type](13-functions.md#return-typing) and cause
a fatal error.

A function may have any number of `return` statements, whose returned
values may have different types.

If an undefined variable is returned byRef, that variable becomes
defined, with a value of `NULL`.

A `return` statement is permitted in a [try-block](#the-try-statement)
and a [catch-block](#the-try-statement) and in [finally-block](#the-try-statement).

Using a `return` statement inside a finally-block will override any other
`return` statement or thrown exception from the try-block and all its
catch-blocks. Code execution in the parent stack will continue as if
the exception was never thrown.

If an uncaught exception exists when a finally-block is executed, if
that finally-block executes a `return` statement, the uncaught exception
is discarded.

A `return` statement may occur in a script outside any function. In an [included file](10-expressions.md#general-6),
such statement terminates processing of that script file and
returns control to the including file. If *expression* is present, that
is the value returned; otherwise, the value `NULL` is returned. If
execution flows to the end of the script, `return 1;` is implied. However,
if execution flows to the end of the top level of a script, `return 0;` is
implied. Likewise, if *expression* is omitted at the top level. (See also
[`exit`](10-expressions.md#exitdie)).

Returning from a constructor or destructor behaves just like returning
from a function.

A `return` statement inside a [generator function](10-expressions.md#yield-operator) causes the generator to
terminate.

A generator function can contain a statement of the form `return` *expression* `;`. The value this returns
can be fetched using the method `Generator::getReturn`, which can only be called once the generator
has finishing yielding values. The value cannot be returned byRef.

Return statements can also be used in the body of anonymous functions.

`return` also terminates the execution of source code given to the intrinsic
[`eval`](10-expressions.md#eval).

**Examples**

```PHP
function f() { return 100; }  // f explicitly returns a value
function g() { return; }   // g explicitly returns an implicit NULL
function h() { }      // h implicitly returns NULL
// -----------------------------------------
// j returns one of three dissimilarly-typed values
function j($x)
{
  if ($x > 0)
  {
    return "Positive";
  }
  else if ($x < 0)
  {
    return -1;
  }
  // for zero, implied return NULL
}
function &compute() { ...; return $value; } // returns $value byRef
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

**Implementation Notes**

Although *expression* is a [full expression](10-expressions.md#general), and there is a
[sequence point](10-expressions.md#general) at the end of that expression,
a side effect need not be executed if it can be determined that
no other program code relies on its having happened. (For example, in
the cases of `return $a++;` and `return ++$a;`, it is obvious what value
must be returned in each case, but if `$a` is a variable local to the
enclosing function, `$a` need not actually be incremented.

###The `throw` Statement

**Syntax**

<pre>
  <i>throw-statement:</i>
    throw  <i>expression</i>  ;
</pre>

**Defined elsewhere**

* [*expression*](10-expressions.md#general-6)

**Constraints**

The type of *expression* must be [Exception](17-exception-handling.md#class-exception) or a subclass of that
class.

*expression* must be such that an alias to it can be created.

**Semantics**

A `throw` statement throws an exception immediately and unconditionally.
Control never reaches the statement immediately following the throw. See
[exception handling](17-exception-handling.md#general) and [try-statement](#the-try-statement)
for more details of throwing and catching exceptions, and how uncaught exceptions are dealt with.

Rather than handle an exception, a catch-block may (re-)throw the same
exception that it caught, or it can throw an exception of a different
type.

**Examples**

```PHP
throw new Exception;
throw new Exception("Some message", 123);
class MyException extends Exception { ... }
throw new MyException;
```

##The `try` Statement

**Syntax**

<pre>
  <i>try-statement:</i>
    try  <i>compound-statement   catch-clauses</i>
    try  <i>compound-statement   finally-clause</i>
    try  <i>compound-statement   catch-clauses   finally-clause</i>

  <i>catch-clauses:</i>
    <i>catch-clause</i>
    <i>catch-clauses   catch-clause</i>

  <i>catch-clause:</i>
    catch  (  <i>qualified-name</i> <i>variable-name</i> )  <i>compound-statement</i>

  <i>finally-clause:</i>
    finally   <i>compound-statement</i>
</pre>

**Defined elsewhere**

* [*compound-statement*](#compound-statements)
* [*variable-name*](09-lexical-structure.md#names)
* [*qualified-name*](09-lexical-structure.md#names)

**Constraints**

In a *catch-clause*, *parameter-declaration-list* must contain only one
parameter, and its type must be [`Exception`](17-exception-handling.md#class-exception) or a type derived from
that class, and that parameter must not be passed byRef.

**Semantics**

In a *catch-clause*, *variable-name* designates an *exception variable*
passed in by value. This variable corresponds to a local variable with a
scope that extends over the catch-block. During execution of the
catch-block, the exception variable represents the exception currently
being handled.

Once an exception is thrown, the Engine searches for the nearest
catch-block that can handle the exception. The process begins at the
current function level with a search for a try-block that lexically
encloses the throw point. All catch-blocks associated with that
try-block are considered in lexical order. If no catch-block is found
that can handle the run-time type of the exception, the function that
called the current function is searched for a lexically enclosing
try-block that encloses the call to the current function. This process
continues until a catch-block is found that can handle the current
exception.

The matching is done by considering the class specified by *qualified-name*
and comparing it to the type of the exception. If the exception is an
[instance of](10-expressions.md#instanceof-operator) this class then the clause matches.

If a matching catch-block is located, the Engine prepares to transfer
control to the first statement of that catch-block. However, before
execution of that catch-block can start, the Engine first executes, in
order, any finally-blocks associated with try-blocks nested more deeply
than the one that caught the exception.

If no matching catch-block is found, the exception is uncaught and the behavior is
implementation-defined.

**Examples**

```PHP
function getTextLines($filename)
{
  $infile = fopen($filename, 'r');
  if ($infile == FALSE) { /* deal with an file-open failure */ }
  try
  {
    while ($textLine = fgets($infile))  // while not EOF
    {
      yield $textLine;  // leave line terminator attached
    }
  }
  finally
  {
    fclose($infile);
  }
}
// -----------------------------------------
class DeviceException extends Exception { ... }
class DiskException extends DeviceException { ... }
class RemovableDiskException extends DiskException { ... }
class FloppyDiskException extends RemovableDiskException { ... }

try
{
  process(); // call a function that might generate a disk-related exception
}
catch (FloppyDiskException $fde) { ... }
catch (RemovableDiskException $rde) { ... }
catch (DiskException $de) { ... }
catch (DeviceException $dve) { ... }
finally { ... }
```

##The `declare` Statement

**Syntax**

<pre>
  <i>declare-statement:</i>
    declare  (  <i>declare-directive</i>  )  <i>statement</i>
    declare  (  <i>declare-directive</i>  )  :  <i>statement-list</i>  enddeclare  ;
    declare  (  <i>declare-directive</i>  )  ;

  <i>declare-directive:</i>
    ticks  =  <i>literal</i>
    encoding  =  <i>literal</i>
    strict_types  =  <i>literal</i>

</pre>

**Defined elsewhere**

* [*statement*](#general)
* [*statement-list*](#compound-statements)
* [*literal*](09-lexical-structure.md#literals)

**Constraints**

The literal for *ticks* must designate a value that is, or can be converted, to an
integer having a non-negative value.

The literal for *encoding* must designate a string whose value names an
8-bit-[character
encoding](http://en.wikipedia.org/wiki/Character_encoding).

Except for white space, a *declare-statement* in a script that specifies
*character-encoding* must be the first thing in that script.

The literal for *strict_types* should be either `0` or `1`. Only the statement-less
form can be used for *strict_types* declare. The *strict_types* declare should be the first statement
in the script, excepting other declare statements.

**Semantics**

The first two forms of the `declare` statement are equivalent; they simply
provide alternate styles.

The `declare` statement sets an *execution directive* for its *statement*
body, or for the `;`-form, for the remainder of the script or until the
statement is overridden by another *declare-statement*, whichever comes
first.

*ticks*: as the parser is executing, certain statements are considered
*tickable*. For every *tick-count* ticks, an event occurs, which can be
serviced by the function previously registered by the library function
[`register_tick_function`](http://php.net/manual/function.register-tick-function.php).
Tick event monitoring can be disabled by calling the library function
[`unregister_tick_function`](http://php.net/manual/function.unregister-tick-function.php).
This facility allows a profiling mechanism to be developed.

*encoding*: character encoding can be specified on a script-by-script basis using
the encoding directive. The joint ISO and IEC standard [ISO/IEC
8859 standard series](http://en.wikipedia.org/wiki/ISO/IEC_8859)
specifies a number of 8-bit-[character
encodings](http://en.wikipedia.org/wiki/Character_encoding) whose names
can be used with this directive.
This directive applies only to the file it appears in, and does not affect the included files.

*strict_types*: if set to `1`, scalar type checking for function calls will be
checked using [strict mode](13-functions.md#type-check-modes). If set to `0`, the coercive mode (default) is used.
This directive applies only to the file it appears in, and does not affect the included files.

**Examples**

```PHP
declare(ticks = 1) { ... }
declare(encoding = 'ISO-8859-1'); // Latin-1 Western European
declare(encoding = 'ISO-8859-5'); // Latin/Cyrillic
```
