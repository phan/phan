#Interfaces

##General

A class can implement a set of capabilities — herein called a
*contract* — through what is called an interface. An *interface* is a set
of method declarations and constants.  Note that the methods are only
declared, not defined; that is, an interface defines a type consisting
of abstract methods, where those methods are implemented by client
classes as they see fit. An interface allows unrelated classes to
implement the same facilities with the same names and types without
requiring those classes to share a common base class.

An interface can extend one or more other interfaces, in which case, it
inherits all members from its *base interface(s)*.

##Interface Declarations

**Syntax**

<pre>
  <i>interface-declaration:</i>
    interface   <i>name   interface-base-clause<sub>opt</sub></i> {  <i>interface-member-declarations<sub>opt</sub></i>  }

  <i>interface-base-clause:</i>
    extends   <i>qualified-name</i>
    <i>interface-base-clause</i>  ,  <i>qualified-name</i>
</pre>

**Defined elsewhere**

* [*name*](09-lexical-structure.md#names)
* [*interface-member-declarations*](#interface-members)

**Constraints**

An interface must not be derived directly or indirectly from itself.

Every *qualified-name* must name an interface type.

**Semantics**

An interface-declaration defines a contract that one or more classes can
implement.

Interface names are case-insensitive.

The optional *interface-base-clause* specifies the base interfaces from
which the interface being defined is derived. In such a case, the
derived interface inherits all the members from the base interfaces.

**Examples**

```PHP
interface MyCollection
{
  const MAX_NUMBER_ITEMS = 1000;
  function put($item);
  function get();
}
class MyList implements MyCollection
{
  public function put($item)  { /* implement method */ }
  public function get()   { /* implement method */ }
  ...
}
class MyQueue implements MyCollection
{
  public function put($item)  { /* implement method */ }
  public function get()   { /* implement method */ }
  ...
}
function processCollection(MyCollection $p1)
{
  ... /* can process any object whose class implements MyCollection */
}
processCollection(new MyList(...));
processCollection(new MyQueue(...));
```

##Interface Members

**Syntax**

<pre>
  <i>interface-member-declarations:</i>
    <i>interface-member-declaration</i>
    <i>interface-member-declarations   interface-member-declaration</i>

  <i>interface-member-declaration:</i>
    <i>class-const-declaration</i>
    <i>method-declaration</i>
</pre>

* [*const-declaration*](14-classes.md#constants)
* [*method-declaration*](14-classes.md#methods)

**Semantics**

The members of an interface are those specified by its
*interface-member-declaration*, and the members inherited from its base
interfaces.

An interface may contain the following members:

-   [Constants](#constants) – the constant values associated with the interface.
-   [Methods](#methods) – placeholders for the computations and actions that can be
    performed by implementers of the interface.

##Constants

**Constraints**

All constants declared in an interface must be implicitly or explicitly public.

**Semantics**

An interface constant is just like a class [constant](14-classes.md#constants), except that
an interface constant cannot be overridden by a class that implements it
nor by an interface that extends it.

**Examples**

```PHP
interface MyCollection
{
  const MAX_NUMBER_ITEMS = 1000;
  function put($item);
  function get();
}
```

##Methods

**Constraints**

All methods declared in an interface must be implicitly or explicitly
public, and they must not be declared `abstract`.

**Semantics**

An interface method is just like an [abstract method](14-classes.md#methods).

**Examples**

```PHP
interface MyCollection
{
  const MAX_NUMBER_ITEMS = 1000;
  function put($item);
  function get();
}
```

##Predefined Interfaces

###Interface `ArrayAccess`

This interface allows an instance of an implementing class to be
accessed using array-like notation. This interface is defined, as
follows:

```PHP
interface ArrayAccess
{
  function offsetExists($offset);
  function offsetGet($offset);
  function offsetSet($offset, $value);
  function offsetUnset($offset);
}
```

The interface members are defined below:

Name  |   Purpose
----    |   -------
`offsetExists`  | This instance method returns `TRUE` if the instance contains an element with key `$offset`, otherwise, `FALSE`.
`offsetGet` |  This instance method gets the value having key `$offset`. It may return by value or byRef. (Ordinarily, this wouldn't be allowed because a class implementing an interface needs to match the interface's method signatures; however, the Engine gives special treatment to `ArrayAccess` and allows this). This method is called when an instance of a class that implements this interface is [subscripted](10-expressions.md#subscript-operator) in a non-lvalue context.
`offsetSet` | This instance method sets the value having key `$offset` to $value. It returns no value. This method is called when an instance of a class that implements this interface is [subscripted](10-expressions.md#subscript-operator) in a modifiable-lvalue context.
`offsetUnset` | This instance method unsets the value having key `$offset`. It returns no value.

###Interface `Iterator`

This interface allows instances of an implementing class to be treated
as a collection. This interface is defined, as follows:

```PHP
interface Iterator extends Traversable
{
  function current();
  function key();
  function next();
  function rewind();
  function valid();
}

```

The interface members are defined below:

Name | Purpose
---- | -------
`current` | This instance method returns the element at the current position.
`key` |This instance method returns the key of the current element. On failure, it returns `NULL`; otherwise, it returns the scalar value of the key.
`next` | This instance method moves the current position forward to the next element. It returns no value. From within a `foreach` statement, this method is called after each loop.
`rewind` |  This instance method resets the current position to the first element. It returns no value. From within a `foreach` statement, this method is called once, at the beginning.
`valid` | This instance method checks if the current position is valid. It takes no arguments. It returns a bool value of `TRUE` to indicate the current position is valid; `FALSE`, otherwise. This method is called after each call to [`Iterator::rewind()`](http://php.net/manual/iterator.rewind.php) and [`Iterator::next()`](http://php.net/manual/iterator.next.php).

###Interface `IteratorAggregate`

This interface allows the creation of an external iterator. This
interface is defined, as follows:

```PHP
interface IteratorAggregate extends Traversable
{
  function getIterator();
}
```
The interface members are defined below:


Name  |   Purpose
----    |   -------
`getIterator` | This instance method retrieves an iterator, which implements `Iterator` or `Traversable`. It throws an `Exception` on failure.

###Interface `Throwable`

This type is the base interface for the type of any object that can be thrown via a
[throw statement](11-statements.md#the-throw-statement). A user-written class cannot
implement `Throwable` directly. Instead, it must extend `Error` or `Exception`.

This type is defined, as follows:

```PHP
interface Throwable {
  function __toString(): string;
  function getCode(): int;
  function getFile(): string;
  function getLine(): int;
  function getMessage(): string;
  function getPrevious(): Throwable;
  function getTrace(): array;
  function getTraceAsString(): string;
}
```
The interface members are defined below:


Name  |   Purpose
----    |   -------
`__toString`	| `string`; retrieves a string representation of the exception in some unspecified format
`getCode`	| `int`; retrieves the exception code
`getFile`	| `string`; retrieves the name of the script where the exception was generated
`getLine`	| `int`; retrieves the source line number in the script where the exception was generated
`getMessage`	| `string`; retrieves the exception message
`getPrevious`	| `Throwable`; retrieves the previous exception, if one exists; otherwise returns `NULL`
`getTrace`	| `array`; retrieves the function stack [trace information](17-exception-handling.md#tracing-exceptions) as an array
`getTraceAsString`	| `string`; retrieves the function stack trace information formatted as a single string in some unspecified format

###Interface `Traversable`

This interface is intended as the base interface for all traversable
classes. This interface is defined, as follows:

```PHP
interface Traversable
{
}
```

This interface has no members.

###Interface  `Serializable`

This interface provides support for custom serialization. It is defined,
as follows:

```PHP
interface Serializable
{
  function serialize();
  function unserialize ($serialized);

}
```

The interface members are defined below:

Name |  Purpose
-----|  -------
`serialize` | This instance method returns a string representation of the current instance. On failure, it returns `NULL`.
`unserialize` | This instance method constructs an object from its string form designated by `$serialized`. It does not return a value.
