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
 * Nullable or mixed non-shape generic array elements do not synthesize an overriding shape
 * (falling back to array{a:1}), so missing sibling fields still emit diagnostics.
 * @param array<?array{a:int,b:string}> $a
 */
function test_nullable_element_shape(array $a): string
{
    $a[0]['a'] = 1;
    echo $a[0]['a'];
    return $a[0]['b'];
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
 * Generic/object/string/false alternatives alongside a shape bail out of generic-array fallback
 * so the synthesized shape does not hide those alternatives.
 * @param array<array{a:int,b:string}|array<string,\stdClass>> $a
 * @param array<array{a:int,b:string}|\ArrayObject<string,int|\stdClass>> $b
 * @param array<array{0:int,1:\stdClass}|string> $c
 * @param array<array{a:int,b:string}|false> $d
 */
function test_non_shape_alternatives_bail_out(array $a, array $b, array $c, array $d): void
{
    $a[0]['a'] = 1;
    echo $a[0]['b'];
    $b[0]['a'] = 1;
    echo $b[0]['b'];
    $c[0][0] = 'x';
    var_dump($c[0][1]);
    $d[0]['a'] = 1;
    echo $d[0]['b'];
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
 * A nullable outer container (?array<array{a:int,b:string}>) does not preserve sibling fields as required.
 * @param ?array<array{a:int,b:string}> $a
 */
function test_nullable_outer_container(?array $a): string
{
    $a[0]['a'] = 1;
    echo $a[0]['a'];
    return $a[0]['b'];
}

/**
 * A nested write through a nullable intermediate ArrayObject drops nullability from the written container.
 * @param array<array{x:?\ArrayObject<string,array<string,int>>}> $a
 */
function test_recursive_nullable_intermediate_object(array $a): int
{
    $a[0]['x']['y']['z'] = 1;
    $v = $a[0]['x']['y']['z'];
    '@phan-debug-var $v';
    return $v;
}

/**
 * PHP replaces an intermediate field that is null or false with a new array on a nested write
 * (false with a deprecation notice), and throws an Error if it is true.
 * x is array{y:array{z:1}} afterwards: no null, false, bool or non-null-mixed.
 * @param array<array{x:null}> $a
 * @param array<array{x:bool}> $b
 */
function test_null_or_bool_intermediate_field(array $a, array $b): void
{
    $a[0]['x']['y']['z'] = 1;
    '@phan-debug-var $a';
    $b[0]['x']['y']['z'] = 1;
    '@phan-debug-var $b';
}

/**
 * A field that is false or an ArrayAccess object is an array or that object after the assignment, not false.
 * @param array{x:false|\ArrayObject<string,int>} $a
 */
function test_false_or_array_access_field(array $a): void
{
    $a['x']['y'] = 1;
    '@phan-debug-var $a';
}

/**
 * The container may be an ArrayObject whose elements have b:\stdClass (PHP throws a TypeError on the return),
 * so the element shape of the generic array alternative is not used: $a[0] is array{a:1}.
 * @param array<array{a:int,b:string}>|\ArrayObject<int,array{a:int,b:\stdClass}> $a
 */
function test_outer_generic_array_or_object($a): string
{
    $a[0]['a'] = 1;
    return $a[0]['b'];
}

/**
 * The same holds for an intermediate field that may be an ArrayObject: $a[0]['x']['y'] is array{a:1}.
 * @param array<array{x:array<array{a:int,b:string}>|\ArrayObject<string,array{a:int,b:\stdClass}>}> $a
 */
function test_intermediate_generic_array_or_object(array $a): string
{
    $a[0]['x']['y']['a'] = 1;
    return $a[0]['x']['y']['b'];
}

/**
 * isset($this->items['x']['a']) is true only if x exists and is not null or false,
 * so the other keys of x keep their types: no PhanTypePossiblyInvalidDimOffset for b.
 */
class IssetHolder
{
    /** @var array{x?:array{a:int,b:string}} */
    public array $items = [];

    public function read(): string
    {
        if (isset($this->items['x']['a'])) {
            return $this->items['x']['b'];
        }
        return '';
    }
}

/**
 * $a['x']['a'] !== null is true only if x exists and is not null or false,
 * so the other keys of x keep their types: no PhanTypePossiblyInvalidDimOffset for b.
 * Reading $a['x'] in the condition may still warn because x is optional.
 * @param array{x?:array{a:?int,b:string}} $a
 */
function test_not_null_condition_on_optional_field(array $a): string
{
    if ($a['x']['a'] !== null) {
        return $a['x']['b'];
    }
    return '';
}
