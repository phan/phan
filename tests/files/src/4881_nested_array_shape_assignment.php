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
