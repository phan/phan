<?php

namespace Issue4881;

/**
 * @param array<array{key1:string,key2:string,key3:string}> $myarray
 */
function test_generic_array_of_shapes(array $myarray): void
{
    $myarray[0]['key2'] = 'anewvalue';
    echo $myarray[0]['key1'];
    echo $myarray[0]['key2'];
    echo $myarray[0]['key3'];
    echo $myarray[0]['bogus'];

    $myarray[0]['key1'] = 42;
    '@phan-debug-var $myarray';

    $myarray[1]['key2'] = 'other';
    echo $myarray[1]['key1'];
    echo $myarray[1]['bogus'];
}

/**
 * @param list<array{x:string,y:string}> $barArr
 */
function test_list_of_shapes(array $barArr): void
{
    if (count($barArr) > 0) {
        $barArr[0]['x'] = strlen($barArr[0]['x']);
        $barArr[0]['y'] = strlen($barArr[0]['y']);
        echo $barArr[0]['x'], $barArr[0]['y'];
    }
}

/**
 * @param array<int,array{a:int,b:string}> $intKeyed
 */
function test_mismatched_key_type(array $intKeyed): void
{
    $intKeyed['str']['a'] = 1;
    echo $intKeyed['str']['a'];
    echo $intKeyed['str']['b'];
}

/**
 * @param array<array<array{a:int,b:string}>> $deep
 */
function test_3d_nested(array $deep): void
{
    $deep[0][1]['a'] = 99;
    echo $deep[0][1]['a'];
    echo $deep[0][1]['b'];
    echo $deep[0][1]['nope'];
}

class Holder
{
    /** @var array<array{key1:string,key2:string}> */
    public array $items = [];

    public function update(): void
    {
        $this->items[0]['key2'] = 'updated';
        echo $this->items[0]['key1'];
        echo $this->items[0]['key2'];
        echo $this->items[0]['missing'];
    }
}

/**
 * The field is a shape or an object: the shape gets y:'str' and keeps z, the object is kept,
 * and no stale array{y:int,z:string} is left next to them.
 * @param array{x:array{y:int,z:string}|\ArrayObject} $a
 */
function test_shape_or_object_field(array $a): void
{
    $a['x']['y'] = 'str';
    '@phan-debug-var $a';
}

/**
 * The field is an ArrayAccess object, which is still an object after the assignment: no PhanNonClassMethodCall.
 * @param array{x:\ArrayObject<string,int>} $a
 */
function test_array_access_field(array $a): int
{
    $a['x']['y'] = 1;
    return $a['x']->count();
}

/**
 * The field is a template type, which is kept: no PhanTypeMismatchReturn.
 * @template T
 * @param array{x:T} $a
 * @return T
 */
function test_template_field(array $a)
{
    $a['x']['y'] = 1;
    return $a['x'];
}

/**
 * The field is mixed, so it may have other keys: no PhanTypeInvalidDimOffset for z.
 * @param array{x:mixed} $a
 */
function test_mixed_field(array $a): void
{
    $a['x']['y'] = 1;
    echo $a['x']['z'];
}

/**
 * A condition on one key of an element keeps the other keys of the element: no PhanTypeInvalidDimOffset for b.
 * @param array<array{a:?int,b:int}> $x
 */
function test_condition_on_element(array $x): void
{
    if ($x[0]['a'] !== null) {
        echo $x[0]['b'];
    }
}

/**
 * Static properties keep the sibling keys too: no PhanTypeInvalidDimOffset for key1.
 */
class StaticHolder
{
    /** @var array<array{key1:string,key2:string}> */
    public static array $items = [];

    public function update(): void
    {
        self::$items[0]['key2'] = 'updated';
        echo self::$items[0]['key1'];
        static::$items[1]['key2'] = 'updated';
        echo static::$items[1]['key1'];
    }
}

/**
 * The field is a nullable ArrayAccess object. PHP creates an array for a field that was null,
 * so the field is not null after the assignment: \ArrayObject<string,int>|array{y:1}.
 * @param array{x:?\ArrayObject<string,int>} $a
 */
function test_nullable_array_access_field(array $a): int
{
    $a['x']['y'] = 1;
    '@phan-debug-var $a';
    return $a['x']->count();
}

/**
 * When the generic array element is nullable, PHP creates an empty array if the element was null,
 * so untouched sibling fields are possibly undefined rather than required.
 * @param array<?array{a:int,b:string}> $a
 */
function test_nullable_element_shape(array $a): string
{
    $a[0]['a'] = 1;
    '@phan-debug-var $a';
    echo $a[0]['a'];
    return $a[0]['b'];
}

/**
 * A 3D generic array with a nullable middle element propagates nullability so inner sibling fields
 * remain possibly undefined.
 * @param array<?array<int,array{a:int,b:string}>> $a
 */
function test_3d_nullable_element_shape(array $a): string
{
    $a[0][1]['a'] = 1;
    echo $a[0][1]['a'];
    return $a[0][1]['b'];
}

/**
 * Appending to a nested generic array element does not flatten the element shape into the new list.
 * @param array<array{a:\stdClass}> $a
 */
function test_nested_append_does_not_flatten_shape(array $a): string
{
    $a[0][] = 'x';
    $v = $a[0][0];
    '@phan-debug-var $v';
    return $v;
}

/**
 * Assigning a dynamic key on a nested generic array element does not flatten the element shape.
 * @param array<array{a:\stdClass}> $a
 */
function test_nested_dynamic_key_does_not_flatten_shape(array $a, string $k): string
{
    $a[0][$k] = 'x';
    $v = $a[0][$k];
    '@phan-debug-var $v';
    return $v;
}

/**
 * When an element can be either a shape or a generic array, untouched sibling fields are widened
 * with compatible generic alternative element types and marked possibly undefined.
 * @param array<array{a:int,b:string}|array<string,\stdClass>> $a
 */
function test_shape_or_generic_alternative(array $a): \stdClass|string
{
    $a[0]['a'] = 1;
    $b = $a[0]['b'];
    '@phan-debug-var $b';
    return $b;
}

/**
 * When an element can be one of multiple shapes with different keys, branch-specific sibling
 * fields remain possibly undefined rather than being promoted to required.
 * @param array<array{a:int,b:string}|array{a:int,c:bool}> $a
 */
function test_union_of_multiple_shapes(array $a): string
{
    $a[0]['a'] = 1;
    echo $a[0]['a'];
    return $a[0]['b'];
}

/**
 * Optional intermediate fields carry their possible absence through recursive merges so untouched
 * sibling fields at deeper levels stay possibly undefined.
 * @param array<array{x?:array{y:array{a:int,b:string},other:int}}> $a
 */
function test_optional_intermediate_field_recursive(array $a): string
{
    $a[0]['x']['y']['a'] = 1;
    echo $a[0]['x']['y']['a'];
    echo $a[0]['x']['other'];
    return $a[0]['x']['y']['b'];
}

/**
 * A nullable outer container (?array<array{a:int,b:string}>) may be null, in which case PHP
 * auto-vivifies an empty array and untouched sibling fields are possibly undefined.
 * @param ?array<array{a:int,b:string}> $a
 */
function test_nullable_outer_container(?array $a): string
{
    $a[0]['a'] = 1;
    echo $a[0]['a'];
    return $a[0]['b'];
}

/**
 * An ArrayAccess alternative in a generic array element is preserved alongside the shape and
 * widens untouched sibling fields with its value type.
 * @param array<array{a:int,b:string}|\ArrayObject<string,int|\stdClass>> $a
 */
function test_generic_array_object_alternative(array $a): \stdClass|int|string
{
    $a[0]['a'] = 1;
    $b = $a[0]['b'];
    '@phan-debug-var $b';
    return $b;
}

/**
 * A generic class implementing bare ArrayAccess with unrelated template parameters must derive
 * its offset value type from offsetGet() rather than guessing from positional template arguments.
 * @template T
 * @template U
 */
class GenericArrayBox implements \ArrayAccess
{
    public function offsetExists(mixed $offset): bool { return true; }
    public function offsetGet(mixed $offset): \stdClass { return new \stdClass(); }
    public function offsetSet(mixed $offset, mixed $value): void {}
    public function offsetUnset(mixed $offset): void {}
}

/**
 * @param array<array{a:int,b:string}|GenericArrayBox<int,string>> $a
 */
function test_generic_box_offset_get_contract(array $a): \stdClass|string
{
    $a[0]['a'] = 1;
    $b = $a[0]['b'];
    '@phan-debug-var $b';
    return $b;
}

/**
 * A false element auto-vivifies an empty array in PHP 8.x, so untouched sibling fields remain
 * possibly undefined.
 * @param array<array{a:int,b:string}|false> $a
 */
function test_false_element_auto_vivifies(array $a): string
{
    $a[0]['a'] = 1;
    echo $a[0]['a'];
    return $a[0]['b'];
}
