<?php
// Checking a key of an array element (if ($x['a']['b'])) must not narrow $x['a'] to only that key
// when the other keys of $x['a'] are unknown. Reading $x['a']['c'] afterwards is not an invalid offset.

/** A plain array, read after a truthiness check. */
function readPlainArray(array $rows): string {
    $result = '';
    if ($rows['0']['enabled']) {
        $result .= 'enabled';
    }
    if ($rows['0']['child']['enabled']) {
        $result .= $rows['0']['child']['label'];
    }
    return $result . $rows['0']['other']['label'] . $rows['1']['enabled'];
}

/**
 * A generic array of generic arrays.
 * @param array<int,array<string,mixed>> $rows
 */
function readGenericArray(array $rows): string {
    $flag = $rows[0]['flag'] ? 'yes' : 'no';
    return $flag . $rows[0]['name'];
}

/** $_FILES is array<string,array<string,...>>. */
function readSuperglobal(): string {
    if (!$_FILES['upload']['tmp_name']) {
        return 'missing';
    }
    return basename((string)$_FILES['upload']['name']);
}

function otherChecks(array $values, $untyped): void {
    if ($values['a']['b'] !== null) {
        echo $values['a']['c'];
    }
    if (is_string($values['d']['e'])) {
        echo strlen($values['d']['e']), $values['d']['f'];
    }
    if ($values['g']['h']['i']) {
        echo $values['g']['other'], $values['g']['h']['other'];
    }
    if ($untyped['a']['b']) {
        echo $untyped['a']['c'];
    }
}

/**
 * True positives: the keys of declared array shapes are still checked.
 * @param array{0:array{key:?int,other:string}} $shape
 * @param list<array{key:?int,other:string}> $list
 */
function declaredShapes(array $shape, array $list): void {
    if ($shape[0]['key']) {
        echo $shape[0]['other'];
        echo $shape[0]['bad'];
        echo $shape[1];
    }
    if ($list[0]['key']) {
        echo $list[0]['other'];
        echo $list[0]['bad'];
    }
}

/**
 * True positive: the elements of a callable-array are known to be strings or objects, not arrays.
 * @param array{a:callable-array} $values
 */
function callableArrayElements(array $values): void {
    if ($values['a'][0]) {
        echo array_sum($values['a'][1]);
    }
}
