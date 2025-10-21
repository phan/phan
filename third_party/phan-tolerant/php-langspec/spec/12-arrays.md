#Arrays

##General

An [*array*](http://php.net/manual/language.types.array.php) is a data structure that contains a collection of zero or
more *elements*. An array element can have any type (which allows for arrays of arrays)
and the elements of an array need not have the same type. The type of an array element can change over its lifetime.
Multidimensional arrays can be implemented as arrays of arrays.

An array is represented as an ordered map in which each entry is a key/value pair
that represents an element. An element key can be an expression of type
`int` or `string`. Duplicate keys are not permitted. The order of the
elements in the map is the order in which the elements were *inserted*
into the array. An element is said to *exist* once it has been inserted
into the array with a corresponding key. An array is *extended* by
initializing a previously non-existent element using a new key. Elements
can be *removed* from an array via the intrinsic [`unset`](10-expressions.md#unset).

The [`foreach` statement](11-statements.md#the-foreach-statement) can be used to iterate over the
collection of elements in an array in order. This statement also provides a way to access the key and value
for each element.

Each array has its own *current element pointer* that designates the
*current array element*. When an array is created, the current element
is the first element inserted into the array.

Numerous [library functions](http://php.net/manual/en/ref.array.php) are available to create and/or manipulate
arrays.

Note: Arrays in PHP are different from arrays in several other languages.
Specifically, in PHP, array elements need not have the same type, the subscript index need not be an integer,
and there is no concept of consecutive elements of the array occupying physically adjacent memory locations).

##Array Creation and Initialization

An array is created and initialized by one of two equivalent ways: via
the array-creation operator [`[]`](10-expressions.md#array-creation-operator) or the [intrinsic `array`](10-expressions.md#array).

##Element Access and Insertion

The value (and possibly the type) of an existing element is changed, and
new elements are inserted, using the subscript operator [`[]`](10-expressions.md#subscript-operator).


