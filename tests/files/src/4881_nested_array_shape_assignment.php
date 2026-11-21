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
