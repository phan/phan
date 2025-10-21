#Basic Concepts
##Program Structure
A PHP *program* consists of one or more source files, known formally as
*scripts*.

<pre>
<i>script:</i>
<i> script-section</i>
<i> script   script-section</i>

<i>script-section:</i>
  <i> text<sub>opt</sub></i> <i>start-tag</i> <i>statement-list<sub>opt</sub></i> <i>end-tag</i><sub>opt</sub> <i>text<sub>opt</sub></i>

<i>start-tag:</i>
  &lt;?php
  &lt;?=

<i>end-tag:</i>
  ?&gt;

<i>text:</i>
  arbitrary text not containing any of <i>start-tag</i> sequences
</pre>

All of the sections in a script are treated as though they belonged to
one continuous section, except that any intervening text is treated as
though it were a string literal given to the [intrinsic `echo`](10-expressions.md#echo).

A script can import another script via a [script inclusion operator](10-expressions.md#script-inclusion-operators).

*statement-list* is defined in [statements section](11-statements.md#compound-statements).

The top level of a script is simply referred to as the *top level*.

If `<?=` is used as the *start-tag*, the Engine proceeds as if the *statement-list* started with [echo](10-expressions.md#echo)
statement.

##Program Start-Up
A program begins execution at the start of a [script](#program-structure) designated in
some unspecified manner. This script is called the *start-up script*.

Once a program is executing, it has access to certain [environmental
information](07-variables.md#predefined-variables), which may include:

-   The number of *command-line arguments*, via the predefined variable
    `$argc`.
-   A series of one or more command-line arguments as strings, via the
    predefined variable `$argv`.
-   A series of *environment variable* names and their definitions.

The exact set of the environment variables available is implementation-defined
and can vary depending on the type and build of the Engine and the environment
in which it executes.

When a [top level](#program-structure) is the main entry point for a script, it gets
the global variable [scope](#scope). When a top level is invoked via
[`include/require`](10-expressions.md#general-6), it inherits the variable scope of its caller. Thus,
when looking at one script's top level in isolation, it's not
possible to tell statically whether it will have the global
variable scope or some local variable scope. It depends on how the
script is invoked and it depends on the runtime state of the program
when it's invoked.

The implementation may accept more than one start-up script, in which case they
are executed in implementation-defined order and share the global environment.

##Program Termination
A program may terminate normally in the following ways:

-   Execution reaches the end of the [start-up script](#program-start-up).
    In case of the multiple start-up scripts, the execution reaches the end
    of the last of them.
-   A [`return` statement](11-statements.md#the-return-statement) in the top level of the
    last start-up script is executed.
-   The intrinsic [`exit`](10-expressions.md#exitdie) is called explicitly.

The behavior of the first two cases is equivalent to corresponding calls
to exit.

A program may terminate abnormally under various circumstances, such as
the detection of an uncaught exception, or the lack of memory or other
critical resource. If execution reaches the end of the start-up script
via a fatal error, or via an uncaught exception and there is no uncaught
exception handler registered by `set_exception_handler`, that is
equivalent to `exit(255)`. If execution reaches the end of the start-up
script via an uncaught exception and an uncaught exception handler was
registered by `set_exception_handler`, that is equivalent to exit(0). It
is unspecified whether [object destructors](14-classes.md#destructors) are run.
In all other cases, the behavior is unspecified.

##__halt_compiler

PHP script files can incorporate data which is to be ignored by the Engine when
compiling the script. An example of such files are [PHAR](http://www.php.net/phar) files.

In order to make the Engine ignore all the data in the script file starting
from certain point, `__halt_compiler();` construct is used. This construct
is not case-sensitive.

The `__halt_compiler();` construct can only appear on the [top level](#program-structure)
of the script. The Engine will ignore all text following this construct.

The value of the `__COMPILER_HALT_OFFSET__` [constant](06-constants.md#context-dependent-constants) is set to the byte offset
immediately following the `;` character in the construct.

**Example**

```PHP
// open this file
$fp = fopen(__FILE__, 'r');

// seek file pointer to data
fseek($fp, __COMPILER_HALT_OFFSET__);

// and output it
var_dump(stream_get_contents($fp));

// the end of the script execution
__halt_compiler(); the file data which will be ignored by the Engine
```

##The Memory Model
###General
This section and those immediately following it describe the abstract
memory model used by PHP for storing variables. A conforming
implementation may use whatever approach is desired as long as from any
testable viewpoint it appears to behave as if it follows this abstract
model. The abstract model makes no explicit or implied restrictions or
claims about performance, memory consumption, and machine resource
usage.

The abstract model presented here defines three kinds of abstract memory
locations:

-   A *variable slot* (VSlot) is used to represent a variable named by
    the programmer in the source code, such as a local variable, an
    array element, an instance property of an object, or a static
    property of a class. A VSlot comes into being based on explicit
    usage of a variable in the source code. A VSlot contains a pointer
    to a VStore.
-   A *value storage location* (VStore) is used to represent a program
    value, and is created by the Engine as needed. A VStore can contain
    a scalar value such as an integer or a Boolean, or it can contain a
    handle pointing to an HStore.
-   A *heap storage location* (HStore) is used to represent the contents
    of a [composite value](05-types.md#types), and is created by the Engine as needed.
    HStore is a container which contains VSlots.

Each existing variable has its own VSlot, which at any time points to a VStore.
A VSlot can be changed to point to different VStores over time.
Multiple VSlots may simultaneously point to the same VStore.
When a new VSlot is created, a new VStore is also created and the VSlot is
initially set to point to the new VStore.

A VStore can be changed to contain different values over time.
Multiple VStores may simultaneously contain handles that point to the same HStore.
When a VStore is created it initially contains
the value `NULL` unless specified otherwise. In addition to
containing a value, VStores also carry a *type tag* that indicates the
[type](05-types.md#types) of the VStore's value.
A VStore's type tag can be changed over time. The tags for the values include
types matching the Engine types, and may include other tags defined by
the implementation, provided that these tags are not exposed to the user.

An HStore represents the contents of a composite value, and it may
contain zero or more VSlots. At run time, the Engine may add new VSlots
and it may remove and destroy existing VSlots as needed to support
adding/removing array elements (for arrays) and to support
adding/removing instance properties (for objects). HStores support access
to VSlots contained in them by integer or case-sensitive string keys.
The exact manner of how VSlots are stored and managed within
the HStore is unspecified.

HStore may contain other information besides VSlots. For example, HStore
for objects also contains information about object's class. The implementation
may also add other information to HStore as needed.

An HStore's VSlots (i.e., the VSlots contained within the HStore) point
to VStores, and each VStore contains a scalar value or a handle to an
HStore, and so on through arbitrary levels, allowing arbitrarily complex
data structures to be represented. For example, a singly linked list
might consist of a variable called `$root`, which is represented by a
VSlot pointing to a VStore containing a handle to the first node. Each
node is represented by an HStore that contains the data for that node in
one or more VSlots, as well as a VSlot pointing to VStore containing a
handle to the next node. Similarly, a binary tree might consist of a
variable called `$root`, which is represented by a VSlot pointing to a
VStore containing a handle to the root node. Each node is represented by
an HStore that contains the data for that node in one or more VSlots, as
well as a pair of VSlots pointing to VStores containing the handles to
the left and right branch nodes. The leaves of the tree would be VStores
or HStores, as needed.

VSlots cannot contain pointers to VSlots or handles to HStores. VStores
cannot contain pointers to VSlots or VStores. HStores cannot directly
contain any pointers or handles to any abstract memory location; HStores
can only directly contain VSlots.

Here is an example demonstrating one possible arrangement of VSlots,
VStores, and HStores:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *]]
                                                           |            |
                                                           V            V
                                                      [VStore int 1]  [VStore int 3]
```

In this picture the VSlot in the upper left corner represents the
variable `$a`, and it points to a VStore that represents `$a`'s current
value, which is a object. This VStore contains a handle pointing to an
HStore which represents the contents of an object of type Point with two
instance properties `$x` and `$y`. The HStore contains two VSlots representing instance
properties `$x` and `$y`, and each of these VSlots points to a distinct
VStore which contains an integer value.

Even though [resources](05-types.md#resources) are not classified as scalar values, for the purposes
of the memory model they are assumed to behave like scalar values, while the scalar value
is assumed to be the the resource descriptor.

***Implementation Notes:*** php.net's implementation can be mapped roughly
onto the abstract memory model as follows: `zval pointer => VSlot, zval
=> VStore, HashTable => HStore`, and
`zend_object/zend_object_handlers => HStore`. Note, however, that the
abstract memory model is not intended to exactly match the php.net
implementation's model, and for generality and simplicity there are some
superficial differences between the two models.

For most operations, the mapping between VSlots and VStores remains the
same. Only the following program constructs can change a VSlot to point
to different VStore, all of which are *byRef-aware* operations and all
of which (except `unset`) use the & punctuator:

-   [byRef assignment](10-expressions.md#byref-assignment).
-   [byRef parameter declaration](13-functions.md#function-definitions).
-   [byRef function return](11-statements.md#the-return-statement).
-   [byRef value in a foreach statement](11-statements.md#the-foreach-statement).
-   [byRef initializer for an array element](10-expressions.md#array-creation-operator).
-   [byRef variable-use list in an anonymous function](10-expressions.md#anonymous-function-creation).
-   [unset](10-expressions.md#unset).

###Reclamation and Automatic Memory Management
The Engine is required to manage the lifetimes of VStores and HStores
using some form of automatic memory management.
In particular, when a VStore or HStore is created, memory is allocated for it.

Later, if a VStore or HStore becomes unreachable through any existing
VSlot, they become eligible for reclamation to release the memory
they occupy. The engine may reclaim a VStore or HStore at any time
between when it becomes eligible for reclamation and the end of the script execution.

Before reclaiming an HStore that represents an [object](05-types.md#object-types),
the Engine should invoke the object's [destructor](14-classes.md#constructors) if one is defined.

The Engine must reclaim each VSlot when the [storage duration](#storage-duration) of its
corresponding variable ends, when the variable is explicitly [unset](10-expressions.md#unset) by the
programmer, or when the script exits, whichever comes first. In the case where
a VSlot is contained within an HStore, the engine must immediately reclaim the VSlot when it is
explicitly unset by the programmer, when the containing HStore is reclaimed,
or when the script exits, whichever comes first.

The precise form of automatic memory management used by the Engine is
unspecified, which means that the time and order of the reclamation of
VStores and HStores is unspecified.

A VStore's *refcount* is defined as the number of unreclaimed VSlots that point
to that VStore. Because the precise form of automatic memory management is not
specified, a VStore's refcount at a given time may differ between
conforming implementations due to VSlots, VStores, and HStores being
reclaimed at different times. Despite the use of the term refcount,
conforming implementations are not required to use a reference
counting-based implementation for automatic memory management.

In some pictures below, storage-location boxes are shown as **(dead)**.
For a VStore or an HStore this indicates that the VStore or HStore is no
longer reachable through any variable and is eligible for reclamation. For
a VSlot, this indicates that the VSlot has been reclaimed or, in the case
of a VSlot contained with an HStore, that the containing HStore has been
reclaimed or is eligible for reclamation.

###Assignment
####General
This section and those immediately following it describe the abstract
model's implementation of *value assignment* and *byRef assignment*.
Value assignment of non-array types to local variables is described
first, followed by byRef assignment with local variables, followed by
value assignment of array types to local variables, and ending with
value assignment with complex left-hand side expressions, and byRef
assignment with complex expressions on the left- or right-hand side.

Value assignment and byRef assignment are core to the PHP language, and
many other operations in this specification are described in terms of
value assignment and byRef assignment.

####Value Assignment of Scalar Types to a Local Variable
Value assignment is the primary means by which the programmer can create
local variables. If a local variable that appears on the left-hand side
of value assignment does not exist, the engine will bring a new local
variable into existence and create a VSlot and initial VStore for
storing the local variable's value.

Consider the following example of value [assignment](10-expressions.md#simple-assignment) of scalar
values to local variables:

```PHP
$a = 123;

$b = false;
```

```
[VSlot $a *]-->[VStore int 123]

[VSlot $b *]-->[VStore bool false]
```

Variable `$a` comes into existence and is represented by a newly created
VSlot pointing to a newly created VStore. Then the integer value 123 is
written to the VStore. Next, `$b` comes into existence represented by a
VSlot and corresponding VStore, and the Boolean value false is written
to the VStore.

Next consider the value assignment `$b = $a`:

```
[VSlot $a *]-->[VStore int 123]

[VSlot $b *]-->[VStore int 123]
```

The integer value 123 is read from `$a`'s VStore and is written into
`$b`'s VStore, overwriting its previous contents. As we can see, the two
variables are completely independent, each has its own VStore
containing the integer value 123. Value assignment
reads the contents of one VStore and overwrites the contents of the
other VStore, but the relationship of VSlots to VStores remains
unchanged. Changing the value of `$b` has no effect on `$a`, and vice
versa.

Using literals or arbitrarily complex expressions on the right hand side
of value assignment value works the same as it does for variables,
except that the literals or expressions don't have their own VSlots or
VStores. The scalar value or handle produced by the literal or
expression is written into the VStore of the left hand side, overwriting
its previous contents.

***Implementation Notes:*** For simplicity, the abstract model's
definition of value assignment never changes the mapping from VSlots to
VStores. However, the conforming implementation is not required to actually
keep separate memory allocations for both variables, it is only required
to behave as if they were independent, e.g. writes to one VStore should
not change the content of another.

For example, the php.net implementation's model, which in some cases will set
two variable slots to point to the same zval when performing value
assignment, produces the same observable behavior as the abstract
model presented here.

To illustrate the semantics of value assignment further, consider `++$b`:

```
[VSlot $a *]-->[VStore int 123]

[VSlot $b *]-->[VStore int 124 (123 was overwritten)]
```

Now consider `$a = 99`:

```
[VSlot $a *]-->[VStore int 99 (123 was overwritten)]

[VSlot $b *]-->[VStore int 124]
```

In both of these examples, one variable's value is changed without
affecting the other variable's value. While the above examples only
demonstrate value assignment for integer and Boolean values, the same
mechanics apply for all scalar types.

Note that as string values are scalar values, the model assumes the whole string
representation, including string characters and its length, is contained within the VStore.
This means that the model assumes whole string data is copied when the string is assigned.

```PHP
$a = 'gg';

$b = $a;
```

```
[VSlot $a *]-->[VStore string 'gg']

[VSlot $b *]-->[VStore string 'gg']
```

`$a`'s string value and `$b`'s string values are distinct from each other,
and mutating `$a`'s string will not affect `$b`. Consider `++$b`, for
example:

```
[VSlot $a *]-->[VStore string 'gg']

[VSlot $b *]-->[VStore string 'gh']
```

***Implementation Notes:***
The conforming implementation may use an actual representation where string
characters are stored outside the structure representing the VStore and
are not copied immediately on assignment, for performance reasons.
Applications in PHP are often written to assume that value assignment of strings
is a rather inexpensive operation.
Thus, it is common for an implementation to use a deferred copy
mechanism to reduce the cost of value assignment for strings. Deferred
copy mechanisms work by not copying a string during value assignment and
instead allowing multiple variables to share the string's contents
indefinitely until a mutating operation (such as the increment operator)
is about to be executed on the string, at which time some or all of the
string's contents are copied. A conforming implementation may choose to
defer copying a string's contents for value assignment so long as it has
no observable effect on behavior from any testable viewpoint (excluding
performance and resource consumption).

####Value Assignment of Objects to a Local Variable

To demonstrate value assignment of objects to local variables, consider
the case in which we have a Point class that supports a two-dimensional
Cartesian system. An instance of Point contains two instance properties,
`$x` and `$y`, that store the x- and y-coordinates, respectively. A
[constructor call](14-classes.md#constructors) of the form `Point(x, y)`
used with operator [`new`](10-expressions.md#the-new-operator)
creates a new point at the given location, and a method call
of the form `move(newX, newY)` moves a `Point` to the new location.

With the `Point` class, let us consider the value assignment `$a = new
Point(1, 3)`:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *]]
                                                           |            |
                                                           V            V
                                                      [VStore int 1]  [VStore int 3]
```

Variable `$a` is given its own VSlot, which points to a VStore that
contains a handle pointing to an HStore allocated by [`new`](10-expressions.md#the-new-operator) and
that is initialized by `Point`'s constructor.

Now consider the value assignment `$b = $a`:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *]]
                                     ^                     |            |
                                     |                     V            V
[VSlot $b *]-->[VStore object *]-----+             [VStore int 1] [VStore int 3]
```

`$b`'s VStore contains a handle that points to the same object as does
`$a`'s VStore's handle. Note that the Point object itself was not copied,
and note that `$a`'s and `$b`'s VSlots point to distinct VStores.

Let's modify the value of the Point whose handle is stored in `$b` using
`$b->move(4, 6)`:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *]]
                                     ^                     |            |
                                     |                     V            V
[VSlot $b *]-->[VStore object *]-----+            [VStore int 4] [VStore int 6]
                                       (1 was overwritten) (3 was overwritten)
```

As we can see, changing `$b`'s Point changes `$a`'s as well.

Now, let's make `$a` point to a different object using `$a = new Point(2,
1)`:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *]]
                                                           |            |
[VSlot $b *]-->[VStore object *]-----+                     V            V
                                     |             [VStore int 2] [VStore int 1]
                                     V
                                   [HStore Point [VSlot $x *] [VSlot $y *]]
                                                           |            |
                                                           V            V
                                                   [VStore int 4] [VStore int 6]
```

Before `$a` can take on the handle of the new `Point`, its handle to the
old `Point` must be removed, which leaves the handles of `$a` and `$b`
pointing to different Points.

We can remove all these handles using `$a = NULL` and `$b = NULL`:

```
[VSlot $a *]-->[VStore null]    [HStore Point [VSlot $x *] [VSlot $y *] (dead)]
                                                        |            |
[VSlot $b *]-->[VStore null]    [VStore int 2 (dead)]&lt;--+            V
                                                          [VStore int 1 (dead)]

                                [HStore Point [VSlot $x *] [VSlot $y *] (dead)]
                                                        |            |
                                [VStore int 4 (dead)]&lt;--+            V
                                                        [VStore int 6 (dead)]
```

By assigning null to `$a`, we remove the only handle to `Point(2,1)` which makes
that object eligible for destruction. A similar thing happens with `$b`,
as it too is the only handle to its Point.

Although the examples above only show with only two instance properties,
the same mechanics apply for value assignment of all object types, even
though they can have an arbitrarily large number of instance properties
of arbitrary type. Likewise, the same mechanics apply to value
assignment of all resource types.

####ByRef Assignment for Scalar Types with Local Variables
Let's begin with the same value [assignment](10-expressions.md#simple-assignment) as in the previous
section, `$a = 123` and `$b = false`:

```
[VSlot $a *]-->[VStore int 123]

[VSlot $b *]-->[VStore bool false]
```

Now consider the [byRef assignment](10-expressions.md#byref-assignment) `$b =& $a`, which has byRef
semantics:

```
[VSlot $a *]-->[VStore int 123]
                 ^
                 |
[VSlot $b *]-----+     [VStore bool false (dead)]
```

In this example, byRef assignment changes `$b`'s VSlot point to the same
VStore that `$a`'s VSlot points to. The old VStore that `$b`'s VSlot used
to point to is now unreachable.

When multiple variables' VSlots point to the same VStore,
the variables are said to be *aliases* of each other or they are said to
have an *alias relationship*. In the example above, after the byRef
assignment executes the variables `$a` and `$b` will be aliases of each
other.

Note that even though in the assignment `$b =& $a` the variable `$b` is on the left and `$a` is on the right,
after becoming aliases they are absolutely symmetrical and equal in their relation to the VStore.

When we change the value of `$b` using `++$b` the result is:

```
[VSlot $a *]-->[VStore int 124 (123 was overwritten)]
                 ^
                 |
[VSlot $b *]-----+
```

`$b`'s value, which is stored in the VStore that `$b`'s VSlot points, is
changed to 124. And as that VStore is also aliased by `$a`'s VSlot, the
value of `$a` is also 124. Indeed, any variable's VSlot that is aliased
to that VStore will have the value 124.

Now consider the value assignment `$a = 99`:

```
[VSlot $a *]-->[VStore int 99 (124 was overwritten)]
                 ^
                 |
[VSlot $b *]-----+
```

The alias relationship between `$a` and `$b` can be broken explicitly by
using `unset` on variable `$a` or variable `$b`. For example, consider
`unset($a)`:

```
[VSlot $a (dead)]      [VStore int 99]
                         ^
                         |
[VSlot $b *]-------------+
```

Unsetting `$a` causes variable `$a` to be destroyed and its link
to the VStore to be removed, leaving `$b`'s VSlot as the only
pointer remaining to the VStore.

Other operations can also break an alias relationship between two or
more variables. For example, `$a = 123` and `$b =& $a`, and `$c = 'hi'`:

```
[VSlot $a *]-->[VStore int 123]
                 ^
                 |
[VSlot $b *]-----+

[VSlot $c *]-->[VStore string 'hi']
```

After the byRef assignment, `$a` and `$b` now have an alias relationship.
Next, let's observe what happens for `$b =& $c`:

```
[VSlot $a *]-->[VStore int 123]

[VSlot $b *]-----+
                 |
                 V
[VSlot $c *]-->[VStore string 'hi']
```

As we can see, the byRef assignment above breaks the alias relationship
between `$a` and `$b`, and now `$b` and `$c` are aliases of each other. When
byRef assignment changes a VSlot to point to a different VStore, it
breaks any existing alias relationship the left hand side variable had
before the assignment operation.

It is also possible to use byRef assignment to make three or more VSlots
point to the same VStore. Consider the following example:

```PHP
$b =& $a;
$c =& $b;
$a = 123;
```

```
[VSlot $a *]-->[VStore int 123]
                 ^   ^
                 |   |
[VSlot $b *]-----+   |
                     |
[VSlot $c *]---------+
```

Like value assignment, byRef assignment provides a means for the
programmer to create variables. If the local variables that appear on
the left- or right-hand side of byRef assignment do not exist, the
engine will bring new local variables into existence and create a VSlot
and initial VStore for storing the local variable's value.

Note that literals, constants, and other expressions that don't
designate a modifiable lvalue cannot be used on the left- or right-hand
side of byRef assignment.

####ByRef Assignment of Non-Scalar Types with Local Variables
ByRef assignment of non-scalar types works using the same mechanism as
byRef assignment for scalar types. Nevertheless, it is worthwhile to
describe a few examples to clarify the semantics of byRef assignment.
Recall the [example using the `Point` class](#value-assignment-of-object-and-resource-types-to-a-local-variable):

`$a = new Point(1, 3);`

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *]]
                                                           |            |
                                                           V            V
                                                  [VStore int 1]  [VStore int 3]
```

Now consider the [byRef assignment](10-expressions.md#byref-assignment) `$b =& $a`, which has byRef
semantics:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *][VSlot $y *]]
                 ^                                         |           |
                 |                                         V           V
[VSlot $b *]-----+                                  [VStore int 1] [VStore int 3]
```

`$a` and `$b` now aliases of each other. Note that byRef assignment
produces a different result than `$b = $a` where `$a` and `$b` would point
to distinct VStores pointing to the same HStore.

Let's modify the value of the `Point` aliased by `$a` using `$a->move(4,
6)`:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] VSlot $y *]]
                 ^                                         |           |
                 |                                         V           V
[VSlot $b *]-----+                              [VStore int 4] [VStore int 6]
                                        (1 was overwritten) (3 was overwritten)
```

Now, let's change `$a` itself using the value assignment `$a = new
Point(2, 1)`:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *][VSlot $y *]]
                 ^                                         |           |
                 |                                         V           V
[VSlot $b *]-----+                                [VStore int 2] [VStore int 1]

                               [HStore Point [VSlot $x *]   [VSlot $y *] (dead)]
                                                       |              |
                                                       V              V
                                     [VStore int 4 (dead)] [VStore int 6 (dead)]
```

As we can see, `$b` continues to have an alias relationship with `$a`.
Here's what's involved in that assignment: `$a` and `$b`'s VStore's handle
pointing to `Point(4,6)` is removed, `Point(2,1)` is created, and `$a` and
`$b`'s VStore is overwritten to contain a handle pointing to that new
`Point`. As there are now no VStores pointing to `Point(4,6)`, it can be destroyed.

We can remove these aliases using `unset($a, $b)`:

```
[VSlot $a (dead)]       [HStore Point [VSlot $x *] [VSlot $y *] (dead)]
                                                |            |
                                                V            V
[VSlot $b (dead)]             [VStore int 2 (dead)]  [VStore int 1 (dead)]
```

Once all the aliases to the VStores are gone, the VStores can be
destroyed, in which case, there are no more pointers to the HStore, and
it can be destoyed too.

####Value Assignment of Array Types to Local Variables
The semantics of value assignment of array types is different from value
assignment of other types. Recall the `Point` class from [the examples](#value-assignment-of-object-and-resource-types-to-a-local-variable), and consider the following [value assignments](10-expressions.md#simple-assignment) and their abstract implementation:

`$a = array(10, 'B' => new Point(1, 3));`

```
[VSlot $a *]-->[VStore array *]-->[HStore Array [VSlot 0 *] [VSlot 'B' *]]
                                                         |             |
                                                         V             V
                                               [VStore int 10]   [VStore Obj *]
                                                                             |
                                [HStore Point [VSlot $x *] [VSlot $y *]]&lt;----+
                                                        |            |
                                                        V            V
                                            [VStore int 1]  [VStore int 3]
```

In the example above, `$a`'s VStore is initialized to contain a handle to
an HStore for an array containing two elements, where one element is an
integer and the other is a handle to an HStore for an object.

Now consider the following value assignment `$b = $a`. A conforming
implementation must implement value assignment of arrays in one of the
following ways: (1) eager copying, where the implementation makes a copy
of `$a`'s array during value assignment and changes `$b`'s VSlot to point
to the copy; or (2) deferred copying, where the implementation uses a
deferred copy mechanism that meets certain requirements. This section
describes eager copying, and the section that immediately follows
describes [deferred copying](#deferred-array-copying).

To describe the semantics of eager copying, let's begin by considering
the value assignment `$b = $a`:

```
[VSlot $a *]-->[VStore array *]-->[HStore Array [VSlot 0 *] [VSlot 'B' *]]
                                                         |             |
[VSlot $b *]-->[VStore array *]                          V             V
                             |                  [VStore int 10]  [VStore object *]
                             V                                                  |
[HStore Array [VSlot 0 *] [VSlot 'B' *]]                                        |
                       |             |                                          |
             +---------+   +---------+                                          |
             V             V                                                    |
[VStore int 10] [VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *]]&lt;---+
                                                            |            |
                                                            V            V
                                                 [VStore int 1]  [VStore int 3]
```

The value assignment `$b = $a` made a copy of `$a`'s array. Note how
`$b`'s VSlot points to a different VStore than `$a`'s VSlot, and `$b`'s
VStore points to a different HStore than `$a`'s VStore. Each source array
element is copied using *member-copy assignment* `=*`, which is defined
as follows:

```
   $destination =* $source
```
-   If `$source`'s VStore has a refcount equal to 1, the Engine copies the
    array element using  value assignment (`destination = $source`).
-   If `$source`'s VStore has a refcount that is greater than 1, the Engine
    uses an implementation-defined algorithm to decide whether to copy the element
    using value assignment (`$destination = $source`) or byRef
    assignment (`$destination =& $source`).

Note the member-copy assignment `=*` is **not** an operator or a language
construct in the PHP language, but instead it is used in this text to
describe behavior for the engine for array copying and other operations.

For the particular example above, member-copy assignment exhibits the
same semantics as value assignment for all conforming implementations
because all of the array elements' VStores have a refcount equal to 1.
The first element VSlots in `$a`'s array and `$b`'s array point
to distinct VStores, each of which contain a distinct copy of the
integer value 10. The second element VSlots in `$a`'s array and `$b`'s
array point to distinct VStores, each of which contain a handle to the
same object HStore.

Let's consider another example:
```PHP
$x = 123;
$a = array(array(&$x, 'hi'));
$b = $a;
```

Eager copying can produce two possible outcomes depending on the
implementation. Here is the first possible outcome:

```
[VSlot $a *]---->[VStore array *]---->[HStore Array [VSlot 0 *]]
                                                             |
[VSlot $x *]-------------------------+   [VStore array *]&lt;---+
                                     |                 |
[VSlot $b *]-->[VStore array *]      |                 V
                             |       |  [HStore Array [VSlot 0 *][VSlot 1 *]]
                             V       |                         |          |
         [HStore Array [VSlot 0 *]]  |                         V          |
                                |    +---------------->[VStore int 123]   |
                                V                          ^              V
                     [VStore array *]                      |   [VStore string 'hi']
                                   |        +--------------+
                                   V        |
                     [HStore Array [VSlot 0 *] [VSlot 1 *]]
                                                        |
                                                        V
                                                     [VStore string 'hi']
```

Here is the second possible outcome:

```
[VSlot $a *]---->[VStore array *]---->[HStore Array [VSlot 0 *]]
                                                             |
[VSlot $x *]-------------------------+  [VStore array *]&lt;----+
                                     |                |
[VSlot $b *]-->[VStore array *]      |                V
                             |       |  [HStore Array [VSlot 0 *] [VSlot 1 *]]
                             V       |                         |           |
         [HStore Array [VSlot 0 *]]  |                         V           |
                                |    +---------------->[VStore int 123]    |
                                V                                          V
                     [VStore array *]                            [VStore string 'hi']
                                   |
                                   V
                    [HStore Array [VSlot 0 *] [VSlot 1 *]]
                                           |           |
                                           V           V
                                  [VStore int 123]  [VStore string 'hi']
```

In both possible outcomes, value assignment with eager copying makes a
copy of `$a`'s array, copying the array's single element using
member-copy assignment (which in this case will exhibit the same
semantics of value assignment for all implementations), which in turn
makes a copy of the inner array inside `$a`'s array, copying the inner
array's elements using member-copy assignment. The inner array's first
element VSlot points to a VStore that has a refcount that is greater than 1,
so an implementation-defined algorithm is used to decide whether to use value
assignment or byRef assignment. The first possible outcome shown above
demonstrates what happens if the implementation chooses to do byRef
assignment, and the second possible outcome shown above demonstrates
what happens if the implementation chooses to do value assignment. The
inner array's second element VSlot points to a VStore that has a refcount
equal to 1, so value assignment is used to copy the inner array's second
element for all conforming implementations that use eager copying.

Although the examples in this section only use arrays with one
element or two elements, the model works equally well for all
arrays even though they can have an arbitrarily large number
of elements. As to how an HStore accommodates all of them, is
unspecified and unimportant to the abstract model.

####Deferred Array Copying
As mentioned in the [previous section](#value-assignment-of-array-types-to-local-variables), an implementation may
choose to use a deferred copy mechanism instead of eagerly making a copy
for value assignment of arrays. An implementation may use any deferred
copy mechanism desired so long as it conforms to the abstract model's
description of deferred array copy mechanisms presented in this
section.

Because an array's contents can be arbitrarily large, eagerly copying an
array's entire contents for value assignment can be expensive. In
practice an application written in PHP may rely on value assignment of
arrays being relatively inexpensive for the common case (in order to deliver
acceptable performance), and as such it is common for an implementation
to use a deferred array copy mechanism in order to reduce the cost of
value assignment for arrays.

Unlike conforming deferred string copy mechanisms discussed [before](#value-assignment-of-scalar-types-to-a-local-variable)
that must produce the same observable behavior as eager string copying,
deferred array copy mechanisms are allowed in some cases to exhibit
observably different behavior than eager array copying. Thus, for
completeness this section describes how deferred array copies can be
modeled in the abstract memory model and how conforming deferred array
copy mechanisms must behave.

Conforming deferred array copy mechanisms work by not making an array
copy during value assignment, by allowing the destination VStore to
share an array HStore with the source VStore, and by making a copy of
the array HStore at a later time if or when it is necessary. The
abstract model represents a deferred array copy relationship by marking
the destination VStore with a special “Arr-D” type tag and by sharing
the same array HStore between the source and destination VStores. Note
that the source VStore's type tag remains unchanged. For the purposes of
this abstract model, the “Arr-D” type tag is considered identical to the
`array` type in all respects except when specified otherwise.

To illustrate this, let's see how the previous example would be
represented under the abstract model assuming the implementation defers
the copying the array:

```PHP
$x = 123;
$a = array(array(&$x, 'hi'));
$b = $a;
```

```
[VSlot $a *]--->[VStore array *]--->[HStore Array [VSlot 0 *]]
                                      ^                    |
                                      | [VStore array *]&lt;--+
[VSlot $b *]--->[VStore Arr-D *]------+               |
                                                      V
                                        [HStore Array [VSlot 0 *] [VSlot 1 *]]
                                                               |           |
                                                               V           |
[VSlot $x *]------------------------------------------>[VStore int 123]    |
                                                                           V
                                                               [VStore string 'hi']
```

As we can see, both `$a`'s VStore (the source VStore) and `$b`'s VStore
(the destination VStore) point to the same array HStore. Note the
asymmetric nature of how deferred array copies are represented in the
abstract model. In the above example the source VStore's type tag
remains unchanged after value assignment, whereas the destination
VStore's type tag was changed to “Arr-D”.

When the engine is about to perform an array-mutating operation on a
VStore tagged “Arr” that participates in a deferred array copy
relationship or on a VStore tagged “Arr-D”, the engine must first take
certain actions that involve making a copy of the array (described in
the next paragraph) before performing the array-mutating operation. An
array-mutating operation is any operation can add or remove array
elements, overwrite existing array elements, change the state of the
array's internal cursor, or cause the refcount of one or more of the
array's element VStores or subelement VStores to increase from 1 to
a value greater than 1. This requirement to take certain actions before
performing an array-mutation operation on a VStore participating in a
deferred array copy relationship is commonly referred to as the
copy-on-write requirement.

When an array-mutating operation is about to be performed on a given
VStore X with an “array” type tag that participates in a deferred array
copy relationship, the engine must find all of the VStores tagged
“Arr-D” that point to the same array HStore that VStore X points to,
make a copy of the array (using [member-copy assignment to copy the
array's elements](#value-assignment-of-array-types-to-local-variables), and update all of these
VStores tagged “Arr-D” to point to the newly created copy (note that
VStore X remains unchanged). When an array-mutation operation is about
to be performed on a given VStore X with an “Arr-D” type tag, the engine
must [make a copy of the array](#value-assignment-of-array-types-to-local-variables), update VStore
X to point to the newly created copy, and change VStore X's type tag to
“array”. These specific actions that the engine must perform on VStore at
certain times to satisfy the copy-on-write requirement are collectively
referred to as *array-separation* or *array-separating the VStore*. An
array-mutation operation is said to *trigger* an array-separation.

Note that for any VStore with an “array” type tag that participates in a
deferred array copy relationship, or for any VStore with an “Arr-D” type
tag, a conforming implementation may choose to array-separate the VStore
at any time for any reason as long as the copy-on-write requirement is
upheld.

Continuing with the previous example, consider the array-mutating
operation `$b[1]++`. Depending on the implementation, this can produce
one of three possible outcomes. Here is the one of the possible
outcomes:

```
[VSlot $a *]---->[VStore array *]---->[HStore Array [VSlot 0 *]]
                                                             |
[VSlot $b *]-->[VStore array *]            [VStore Arr *]&lt;---+
                             |                         |
      +----------------------+              +----------+
      V                                     V
  [HStore Array [VSlot 0 *] [VSlot 1 *]]  [HStore Array [VSlot 0 *] [VSlot 1 *]]
                         |           |       ^                   |           |
                         |           V       |                   V           |
                         |   [VStore int 1]  |            [VStore int 123]   |
                         V                   |             ^                 V
                       [VStore Arr-D *]------+             |   [VStore string 'hi']
                                                           |
 [VSlot $x *]----------------------------------------------+
```

As we can see in the outcome shown above, `$b`'s VStore was
array-separated and now `$a`'s VStore and `$b`'s VStore point to distinct
array HStores. Performing array-separation on `$b`'s VStore was necessary
to satisfy the copy-on-write requirement. `$a`'s array remains unchanged
and that `$x` and `$a[0][0]` still have an alias relationship with each
other. For this particular example, conforming implementations are
required to preserve `$a`'s array's contents and to preserve the alias
relationship between `$x` and `$a[0][0]`. Finally, note that `$a[0]` and
`$b[0]` have a deferred copy relationship with each other in the outcome
shown above. For this particular example, a conforming implementation is
not required to array-separate `$b[0]`'s VStore, and the outcome shown
above demonstrates what happens when `$b[0]`'s VStore is not
array-separated. However, an implementation can choose to array-separate
`$b[0]`'s VStore at any time if desired. The other two possible outcomes
shown below demonstrate what can possibly happen if the implementation
choose to array-separate `$b[0]`'s VStore as well. Here is the second
possible outcome:

```
[VSlot $a *]---->[VStore array *]---->[HStore Array [VSlot 0 *]]
                                                             |
[VSlot $b *]-->[VStore array *]          [VStore array *]&lt;---+
                             |                         |
                             V                         V
  [HStore Array [VSlot 0 *] [VSlot 1 *]]  [HStore Array [VSlot 0 *] [VSlot 1 *]]
                         |           |                           |           |
       +-----------------+           V                           |           |
       |                     [VStore int 1]                 +----+           |
       V                                                    |                V
  [VStore Arr-D *]-->[HStore Array [VSlot 0 *] [VSlot 1 *]] | [VStore string 'hi']
                                            |           |   |
                                    +-------+           |   |
                                    |                   V   |
                                    | [VStore string 'hi']  |
                                    V                       |
 [VSlot $x *]--------------------->[VStore int 123]&lt;--------+
```

Here is the third possible outcome:

```
[VSlot $a *]---->[VStore array *-]---->[HStore Array [VSlot 0 *]]
                                                            |
[VSlot $b *]-->[VStore array *]           [VStore array *]&lt;---+
                             |                          |
                             V                          V
 [HStore Array [VSlot 0 *] [VSlot 1 *]]  [HStore Array [VSlot 0 *] [VSlot 1 *]]
                        |           |                           |           |
       +----------------+           V                           |           |
       |                     [VStore int 1]                  +--+           |
       V                                                     |              V
   [VStore Arr-D *]-->[HStore Array [VSlot 0 *] [VSlot 1 *]] | [VStore string 'hi']
                                             |           |   |
                     [VStore int 123]&lt;-------+           |   |
                                                         V   |
                                       [VStore string 'hi']  |
                                                             |
 [VSlot $x *]--------------------->[VStore int 123]&lt;---------+
```

The second and third possible outcomes show what can possibly happen if
the implementation chooses to array-separate `$b[0]`'s VStore. In the
second outcome, `$b[0][0]` has an alias relationship with `$x` and
`$a[0][0]`. In the third outcome, `$b[0][0]` does not have an alias
relationship, though `$x` and `$a[0][0]` still have an alias relationship
with each other. The differences between the second and third outcome
are reflect that different possibilities when the engine uses
member-copy assignment to copy `$a[0]`'s arrays's elements into `$b[0]`'s
array.

Finally, let's briefly consider one more example:
```PHP
$x = 0;
$a = array(&$x);
$b = $a;
$x = 2;
unset($x);
$b[1]++;
$b[0]++;
echo $a[0], ' ', $b[0];
```

For the example above, a conforming implementation could output “2 1”,
“2 3”, or “3 3” depending on how it implements value assignment for
arrays.

For portability, it is generally recommended that programs written in
PHP should avoid performing value assignment with a right-hand side that
is an array with one or more elements or sub-elements that have an alias
relationship.

***Implementation Notes:*** For generality and for simplicity, the
abstract model represents deferred array copy mechanisms in a manner
that is more open-ended and superficially different than the php.net
implementation's model, which uses a symmetric deferred copy mechanism
where a single zval contains the sole pointer to a given Hashtable and
deferred array copies are represented as multiple slots pointing to the
same single zval that holds the array. Despite this superficial
difference, php.net's implementation produces behavior that is
compatible with the abstract model's definition of deferred array copy
mechanisms.

####General Value Assignment
The sections above thus far have described the mechanics of value assignment
to a local variable. The assignment to a modifiable lvalue that is not a variable, such as array element or
object property, works like the local variable assignment, except that the VSlot which represented
a variable is replaced by a VSlot that represents the target lvalue.
If necessary, such VSlot is created.

For example, assuming `Point` definition as in previous sections and further assuming all
instance properties are public, this code:

```PHP
$a = new Point(1, 3);
$b = 123;
$a->x = $b;
```
Will result in:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *]]
                                                           |            |
                                                           V            V
                                                  [VStore int 123] [VStore int 3]
[VSlot $b *]-->[VStore int 123]
```

If needed, new VSlots are created as part of the containing VStore, for example:
```PHP
$a = new Point(1, 3);
$b = 123;
$a->z = $b;
```
Will result in:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *] [VSlot $z *]]
                                                           |            |            |
                                                           V            V            V
                                                  [VStore int 1] [VStore int 3] [VStore int 123]
[VSlot $b *]-->[VStore int 123]
```

The same holds for array elements:
```PHP
$a = array('hello', 'world');
$b = 'php';
$a[1] = $b;
$a[2] = 'World!';
```
Will result in:

```
[VSlot $a *]-->[VStore array *]-->[HStore Array [VSlot 0 *]  [VSlot 1 *]  [VSlot 2 *]]
                                                         |            |            |
                                                         V            V            V
                                    [VStore string 'hello'] [VStore string 'php'] [VStore string 'World!']
[VSlot $b *]-->[VStore string 'php']
```

Where the third VSlot with index 2 was created by the assignment.

Note that any array element and instance property, including a designation of non-existing ones,
is considered a modifiable lvalue, and the VSlot will be created by the engine and added
to the appropriate HStore automatically. Static class properties are considered modifiable lvalues too,
though new ones would not be created automatically.

####General ByRef Assignment
The sections above thus far have described the mechanics of byref assignment
with local variables. The byRef assignment to a modifiable lvalue that is not a variable,
such as array element or object property, works like the local variable assignment,
except that the VSlot which represented a variable is replaced by a VSlot
that represents the target lvalue.  If necessary, such VSlot is created and added to
the corresponding HStore.

For example:
```PHP
$a = new Point(1, 3);
$b = 123;
$a->z =& $b;
```

Will result in:

```
[VSlot $a *]-->[VStore object *]-->[HStore Point [VSlot $x *] [VSlot $y *] [VSlot $z *]]
                                                           |            |            |
                                                           V            V            |
                                                  [VStore int 1] [VStore int 3]      |
[VSlot $b *]---------------->[VStore int 123]&lt;---------------------------------------+
```

###Argument Passing
Argument passing is defined in terms of [simple assignment](#assignment) or [byRef assignment](#byref-assignment-for-scalar-types-with-local-variables), depending on how the parameter is declared.
That is, passing an argument to a function having a corresponding
parameter is like assigning that argument to that parameter. The
function call situations involving missing arguments or
undefined variable arguments are discussed in section describing
[the function call operator](10-expressions.md#function-call-operator).

###Value Returning
Returning a value from a function is defined in terms of [simple assignment](#assignment) or [byRef assignment](#byref-assignment-for-scalar-types-with-local-variables), depending on how the function is declared.
That is, returning a value from a function to its
caller is like assigning that value to the user of the caller's return
value. The function-return situations involving a missing return value
are discussed in section describing [the function call operator](10-expressions.md#function-call-operator).

Note that to achieve byRef assignment semantics, both function return and
assignment of the return value should be byRef. For example:

```PHP
function &counter()
{
  static $c = 0;
  $c++;
  echo $c." ";
  return $c;
}

$cnt1 = counter();
$cnt1++; // this does not influence counter
$cnt2 =& counter();
$cnt2++; // this does influence counter
counter();
```

This example prints `1 2 4 `, since the first assignment does not produce
byRef semantics even though the function return is declared byRef.
If the function is not declared to return byRef, its return never produces
byRef semantics, regardles of how it is assigned.

Passing function's return to another function is considered the same as assigning
the value to the corresponding function's parameter, with byRef parameters
treated as byRef assignments.

###Cloning objects
When an object instance is allocated, operator [`new`](10-expressions.md#the-new-operator) returns a handle
that points to that object. As described [above](#value-assignment-of-object-and-resource-types-to-a-local-variable),
value assignment of a handle to an object does not copy the object HStore itself. Instead, it creates a copy of the handle.
The copying of the HStore itself is performed via [operator `clone`](10-expressions.md#the-clone-operator).

To demonstrate how the `clone` operator works, consider the case in which
an instance of class `Widget` contains two instance properties: `$p1` has
the integer value 10, and `$p2` is a handle to an array of elements of
some type(s) or to an instance of some other type.

```
[VSlot $a *]-->[VStore object *]-->[HStore Widget [VSlot $p1 *][VSlot $p2 *]]
                                                             |            |
                                                             V            V
                                               [VStore int 10] [VStore object *]
                                                                              |
                                                        [HStore ...]&lt;---------+
```

Let us consider the result of `$b = clone $a`:

```
[VSlot $a *]-->[VStore object *]-->[HStore Widget [VSlot $p1 *][VSlot $p2 *]]
                                                             |            |
[VSlot $b *]-->[VStore object *]                             V            V
                             |                  [VStore int 10] [VStore object *]
     +-----------------------+                                                 |
     V                                                                         |
   [HStore Widget [VSlot $p1 *] [VSlot $p2 *]]              +--->[HStore ...]&lt;-+
                             |             |                |
                             V             V                |
                 [VStore int 10] [VStore object *]----------+
```

The clone operator will create another object HStore of the same class
as the original and copy `$a`'s object's instance properties using
[member-copy assignment](#value-assignment-of-array-types-to-local-variables). For the example shown above, the
handle to the newly created HStore stored into `$b` using value
assignment. Note that the clone operator will not recursively clone
objects held in `$a`'s instance properties; hence the object copying
performed by the clone operator is often referred to as a *shallow
copy*. If a *deep copy* of an object is desired, the programmer must
achieve this manually by using the [method `__clone`](14-classes.md#method-__clone) which
is called after the initial shallow copy has been performed.

##Scope

The same name can designate different things at different places in a
program. For each different thing that a name designates, that name is
visible only within a part of the program called that name's *scope*.

There are a number of scope types that exist in PHP:

-   Variable scope - the scope which defined what unqualified variables (like `$foo`) are referring to.
    Variables defined in one variable scope are not visible in another variable scope.
-   Class scope - the scope that defines visibility of the methods and properties, and resolution of keywords like
    `self`, `parent`, etc. Class scope encompasses [the body of that class](14-classes.md#class-declarations) and any classes derived
    from it.
-   Namespace scope - the scope that defines what unqualified and not-fully-qualified class and function names (e.g. `foo()` or `new Bar()`)
    refer to. Namespace scoping rules are defined in the [Namespaces chapter](18-namespaces.md#namespaces).

For variable scopes, the following scopes can be distinguished:

-   *Global scope* is the topmost scope of the script, which contains global variables, including pre-defined ones
    and ones defined outside of any other scope.
-   *Function scope*, which means from the point of declaration/first
    initialization through to the end of that [function's body](13-functions.md#function-definitions).

[Start-up scripts](#program-start-up) have the global variable scope.
[Included](10-expressions.md#script-inclusion-operators) scripts have the variable scope matching the scope in
the place where the inclusion operator was executed.

A variable declared or first initialized inside a function, has function scope;
otherwise, the variable has the same variable scope as the enclosing script.

[Global variables](07-variables.md#global-variables) can be brought into the current scope by using `global` keyword.
[Superglobals](07-variables.md#general) exist in the global variable scope, however they can be also accessed in any scope;
they never need explicit declaration.

Each function has its own function scope. An [anonymous function](13-functions.md#anonymous-functions)
has its own scope separate from that of any function inside which that anonymous function is defined.

The variable scope of a parameter is the body of the function in which the parameter is declared.

The scope of a []*named-label*](11-statements.md#labeled-statements) is the body of the function in
which the label is defined.

The class scope of a [class member m](14-classes.md#class-members) that is declared in, or inherited by, a
class type C is the body of C.

The class scope of an [interface member m](14-classes.md#class-members) that is declared in, or inherited by,
an interface type I is the body of I.

When a [trait](16-traits.md#general) is used by a class or an interface, the [trait's
members](16-traits.md#trait-members) take on the class scope of a member of that class or
interface.

##Storage Duration

The lifetime of a variable is the time during program execution that
storage for that variable is guaranteed to exist. This lifetime is
referred to as the variable's *storage duration*, of which there are
three kinds: automatic, static, and allocated.

A variable having *automatic storage duration* comes into being and is
initialized at its declaration or on its first use, if it has no
declaration. Its lifetime is delimited by an enclosing [scope](#scope). The
automatic variable's lifetime ends at the end of that scope. Automatic
variables lend themselves to being stored on a stack where they can help
support argument passing and recursion. [Local variables](07-variables.md#local-variables), which
include [function parameters](13-functions.md#function-definitions), have automatic storage duration.

A variable having *static storage duration* comes into being and is
initialized before its first use, and lives until program shutdown. The
following kinds of variables have static storage duration: [constants](07-variables.md#constants),
[function statics](07-variables.md#function-statics), [global variables](07-variables.md#global-variables),
[static properties](07-variables.md#static-properties),
and class and interface [constants](07-variables.md#class-and-interface-constants).

A variable having *allocated storage duration* comes into being based on
program logic by use of the [new operator](10-expressions.md#the-new-operator) or a factory function.
Ordinarily, once such storage is no longer needed, it is reclaimed automatically by the
Engine via its garbage-collection process and the use of
[destructors](14-classes.md#destructors). The following kinds of variables have allocated
storage duration: [array elements](07-variables.md#array-elements) and [instance properties](07-variables.md#instance-properties).

Although all three storage durations have default ends-of-life, their
lives can be shortened by calling the intrinsic [`unset`](10-expressions.md#unset),
which destroys any given set of variables.

The following example demonstrates the three storage durations:

```PHP
class Point { ... }

$av1 = new Point(0, 1);       // auto variable $av1 created and initialized
static $sv1 = ...;          // static variable $sv1 created and initialized

function doit($p1)
{
  $av2 = ...;           // auto variable $av2 created and initialized
  static $sv2 = ...;        // static variable $sv2 created and initialized
  if ($p1)
  {
    $av3 = ...;         // auto variable $av3 created and initialized
    static $sv3 = ...;    // static variable $sv3 created and initialized
    ...
  }
  global $av1;
  $av1 = new Point(2, 3);   // Point(0,1) is eligible for destruction
  ...
}                   // $av2 and $av3 are eligible for destruction

doit(TRUE);

// At end of script, $av1, $sv1, $sv2, and $sv3 are eligible for destruction
```

The comments indicate the beginning and end of lifetimes for each
variable. In the case of the initial allocated Point variable whose
handle is stored in `$av1`, its life ends when `$av1` is made to point to
a different Point.

If function `doit` is called multiple times, each time it is called, its
automatic variables are created and initialized, whereas its static
variables retain their values from previous calls.

Consider the following recursive function:

```PHP
function factorial($i)
{
  if ($i > 1) return $i * factorial($i - 1);
  else if ($i == 1) return $i;
  else return 0;
}
```

When `factorial` is first called, the local variable parameter `$i` is
created and initialized with the value of the argument in the call.
Then, if this function calls itself, the same process is repeated each
call. Specifically, each time `factorial` calls itself, a new local
variable parameter `$i` is created and initialized with the value of the
argument in the call.

The lifetime of any VStore or HStore can be extended by
the Engine as long as needed. Conceptually, the lifetime of a VStore ends
when it is no longer pointed to by any VSlots. Conceptually, the
lifetime of an HStore ends when no VStores have a handle to it.
