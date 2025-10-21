#Types

##General

The meaning of a value is determined by its *type*. PHP's types are
categorized as *scalar types* and *composite types*. The scalar types
are [Boolean](#the-boolean-type), [integer](#the-integer-type), [floating point](#the-floating-point-type), [string](#the-string-type), and [null](#the-null-type). The composite types are [array](#the-array-type),
and [object](#objects). The [resource](#resources) is an opaque type whose internal structure is not specified and depends
on the implementation.

The scalar types are *value types*. That is, a variable of scalar type
behaves as though it contains its own value.

The composite types can contain other variables, besides the variable itself, e.g.
array contains its elements and object contains its properties.

The objects and resources are *handle types*. The type contains information — in a *handle* —
that leads to the value. The differences between value and handle types become apparent
when it comes to understanding the [semantics](04-basic-concepts.md#the-memory-model) of assignment, and passing
arguments to, and returning values from, functions.

Variables are not declared to have a particular type. Instead, a
variable's type is determined at runtime by the value it contains.
The same variable can contain values of different types at different times.

Useful library functions for interrogating and using type information
include [`gettype`](http://www.php.net/gettype), [`is_type`](http://www.php.net/is_type), [`settype`](http://www.php.net/settype), and [`var_dump`](http://www.php.net/var_dump).

##Scalar Types

###General

The integer and floating-point types are collectively known as
*arithmetic types*. The library function [`is_numeric`](http://www.php.net/is_numeric) indicates if
a given value is a number or a numeric [string](#the-string-type).

The library function [`is_scalar`](http://www.php.net/is_scalar) indicates if a given value has a
scalar type. However, that function does not consider `NULL` to be scalar.
To test for `NULL`, use [`is_null`](http://www.php.net/is_null).

Some objects may support arithmetic and other scalar operations and/or be
convertible to scalar types (this is currently available only to internal classes).
Such object types together with scalar types are called *scalar-compatible types*.
Note that the same object type may be scalar-compatible for one operation but not for another.

###The Boolean Type

The Boolean type is `bool`, for which the name `boolean` is a synonym. This
type is capable of storing two distinct values, which correspond to the
Boolean values [`true` and `false`](06-constants.md#core-predefined-constants), respectively.
The internal representation of this type and its values is unspecified.

The library function [`is_bool`](http://www.php.net/is_bool) indicates if a given value has type
`bool`.

###The Integer Type

There is one integer type, `int`, for which the name `integer` is a synonym.
This type is binary, signed, and uses twos-complement representation for
negative values. The range of values that can be stored is
implementation-defined; however, the range [-2147483648, 2147483647],
must be supported. This range must be finite.

Certain operations on integer values produce a mathematical result that
cannot be represented as an integer. Examples include the following:

-   Incrementing the largest value or decrementing the smallest value.
-   Applying the unary minus to the smallest value.
-   Multiplying, adding, or subtracting two values.

In such cases, the computation is done as though the types of the values were
`float` with the result having that type.

The constants [`PHP_INT_SIZE`, `PHP_INT_MIN` and `PHP_INT_MAX`](06-constants.md#core-predefined-constants) define certain
characteristics about type `int`.

The library function [`is_int`](http://www.php.net/is_int) indicates if a given value has type
`int`.

###The Floating-Point Type

There is one floating-point type, `float`, for which the names `double` and
`real` are synonyms. The `float` type must support at least the range and
precision of IEEE 754 64-bit double-precision representation.

The library function [`is_float`](http://www.php.net/is_float) indicates if a given value has type
`float`. The library function [`is_finite`](http://www.php.net/is_finite) indicates if a given
floating-point value is finite. The library function [`is_infinite`](http://www.php.net/is_infinite)
indicates if a given floating-point value is infinite. The library
function [`is_nan`](http://www.php.net/is_nan) indicates if a given floating-point value is a
`NaN`.

###The String Type

A string is a set of contiguous bytes that represents a sequence of zero
or more characters.

Conceptually, a string can be considered as an [array](#array-types) of
bytes—the *elements*—whose keys are the `int` values starting at zero. The
type of each element is `string`. However, a string is *not* considered a
collection, so it cannot be iterated over.

A string whose length is zero is an *empty string*.

As to how the bytes in a string translate into characters is
unspecified.

Although a user of a string might choose to ascribe special semantics to
bytes having the value `\0`, from PHP's perspective, such *null bytes*
have no special meaning. PHP does not assume strings contain any specific
data or assign special values to any bytes or sequences. However, many
library functions assume the strings they receive as arguments are UTF-8
encoded, often without explicitly mentioning that fact.

A *numeric string* is a string whose content exactly matches the pattern
defined by the *str-numeric* production below.
A *leading-numeric string* is a string whose initial characters follow
the requirements of a numeric string, and whose trailing characters are
non-numeric. A *non-numeric string* is a string that is not a numeric
string.

<pre>
  <i>str-numeric::</i>
    <i>str-whitespace<sub>opt</sub>   sign<sub>opt</sub>   str-number</i>

  <i>str-whitespace::</i>
    <i>str-whitespace<sub>opt</sub>   str-whitespace-char</i>

  <i>str-whitespace-char::</i>
    <i>new-line</i>
    Space character (U+0020)
    Horizontal-tab character (U+0009)
    Vertical-tab character (U+000B)
    Form-feed character (U+000C)

  <i>str-number::</i>
    <i>digit-sequence</i>
    <i>floating-literal</i>
</pre>

**Defined elsewhere**

* [*digit-sequence*](09-lexical-structure.md#floating-point-literals)
* [*floating-literal*](09-lexical-structure.md#floating-point-literals)
* [*new-line*](09-lexical-structure.md#comments)
* [*sign*](09-lexical-structure.md#floating-point-literals)

Note that *digit-sequence* is interpreted as having base-10 (so `"0377"` is treated as 377 decimal with a redundant
leading zero, rather than as octal 377).

Only one mutation operation may be performed on a string, offset
assignment, which involves the simple assignment [operator =](10-expressions.md#simple-assignment).

The library function [`is_string`](http://www.php.net/is_string) indicates if a given value has
type string.

###The Null Type

The null type has only one possible value, [`NULL`](06-constants.md#core-predefined-constants). The representation
of this type and its value is unspecified.

The library function [`is_null`](http://www.php.net/is_null) indicates if a given value is `NULL`.

##Composite Types

###The Array Type

An array is a data structure that contains a collection of zero or more
elements whose values are accessed through keys that are of type `int` or
`string`. See more details in [arrays chapter](12-arrays.md#arrays).

The library function [`is_array`](http://www.php.net/is_array) indicates if a given value is an
array.

###Objects

An *object* is an instance of a [class](14-classes.md#classes). Each distinct
[]*class-declaration*](14-classes.md#class-declarations) defines a new class type, and each class
type is an object type. The representation of object types is unspecified.

The library function [`is_object`](http://www.php.net/is_object) indicates if a given value is an
object, and the library function
[`get_class`](http://php.net/manual/function.get-class.php) indicates the name of an object's class.

###Resources

A [*resource*](http://php.net/manual/language.types.resource.php)
is a descriptor to some sort of external entity. Examples include
files, databases, and network sockets.

A resource is an abstract entity whose representation is unspecified.
Resources are only created or consumed by the implementation; they are
never created or consumed by PHP code.

Each distinct resource has a unique identity of some unspecified form.

The library function [`is_resource`](http://www.php.net/is_resource) indicates if a given value is a
resource, and the library function
[`get_resource_type`](http://php.net/manual/function.get-resource-type.php) indicates the type of a resource.



