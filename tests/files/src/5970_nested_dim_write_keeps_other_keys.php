<?php
// Writing to a key of an array element ($x['a']['b'] = 1) must not narrow $x['a'] to only that key
// when $x['a'] already existed with unknown keys. Reading $x['a']['c'] afterwards is not an invalid offset.

function writePlainArray(array $rows): void {
    $rows['0']['enabled'] = true;
    echo $rows['0']['label'];
}

/** @param array<int,array<string,mixed>> $rows */
function writeGenericArray(array $rows): void {
    $rows[0]['enabled'] = true;
    echo $rows[0]['label'];
}

function writeUntyped($values): void {
    $values['a']['b'] = 1;
    echo $values['a']['c'];
    $values['d']['e']['f'] = 1;
    echo $values['d']['other'], $values['d']['e']['other'];
}

/** Based on https://github.com/phan/phan/issues/4926 */
function writeDecodedJson(string $json): void {
    $rows = json_decode($json, true);
    if (count($rows) > 0) {
        $rows[0]['x'] = strlen($rows[0]['x']);
        $rows[0]['y'] = strlen($rows[0]['y']);
    }
}

/** The types of the keys of $values['a']['b'] say nothing about the other keys of $values['a']. */
function writeSiblingKeys(array $values): void {
    $values['a']['b']['c'] = 'text';
    echo array_sum($values['a']['d']['c']);
    echo array_sum($values['a']['d']);
}

/**
 * True positives: the write creates the array element, or its keys are declared.
 * @param array{a:array{b:int}} $shape
 */
function writeTruePositives(array $shape): void {
    $new = [];
    $new['a']['b'] = 1;
    echo $new['a']['c'];

    $undefined['a']['b'] = 1;
    echo $undefined['a']['c'];

    $nullElement = ['a' => null];
    $nullElement['a']['b'] = 1;
    echo $nullElement['a']['c'];

    $shape['a']['b'] = 2;
    echo $shape['a']['c'];
}

/**
 * True positives: the array element was assigned before the write, so its keys are known,
 * also when the variable itself has unknown keys.
 * @param array<string,mixed> $generic
 * @param array<int,array<string,mixed>> $intKeyed
 */
function writeAfterAssignment(array $config, $untyped, array $generic, array $intKeyed, array $deep, array $in): void {
    $config['empty'] = [];
    $config['empty']['x'] = 1;
    echo $config['empty']['y'];

    $config['null'] = null;
    $config['null']['x'] = 1;
    echo $config['null']['y'];

    $config['false'] = false;
    $config['false']['x'] = 1;
    echo $config['false']['y'];

    $untyped['opts'] = [];
    $untyped['opts']['x'] = 1;
    echo $untyped['opts']['y'];

    $generic['opts'] = ['a' => 1];
    $generic['opts']['x'] = 1;
    echo $generic['opts']['y'];

    $deep['a'] = ['b' => []];
    $deep['a']['b']['c'] = 1;
    echo $deep['a']['b']['d'];

    $out = $in;
    $out['meta'] = ['id' => 1];
    $out['meta']['name'] = 'n';
    echo $out['meta']['missing'];

    // A string key cannot be a key of an array<int,T>, so the write creates the element.
    $intKeyed['foo']['x'] = 1;
    echo $intKeyed['foo']['y'];
}
