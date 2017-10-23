<?php

function expect_string_300(string $x) { }
function expect_int_300(int $x) { }

function test300() {
    $intVar = 42;
    $strVar = 'x';
    // Every one of these statements should warn about incorrect types, unless commented otherwise
    expect_string_300(++$intVar);
    expect_string_300($intVar++);
    expect_string_300(--$intVar);
    expect_string_300($intVar--);
    expect_int_300($strVar++);
    expect_int_300(++$strVar);
    expect_int_300($strVar--);
    expect_int_300(--$strVar);

    clone(42);  // TODO: Warn
    expect_string_300(clone(new stdClass()));
    expect_string_300($refIntVar &= $intVar);  // should warn
    if (false) {
        expect_int_300(`ls`);
    }
    expect_string_300((object)['key' => 'val']);
    expect_string_300(null ? 'string' : 3);  // null is always falsey, so Phan infers the type as `int`
    expect_string_300(null ?: 3);  // null is always falsey, so Phan infers the type as `int`
    expect_default_int_300('x');

    $definedIntVar = 33;
    expect_string_300($definedIntVar &= $intVar);
    expect_int_300($definedIntVar &= $intVar);
    $definedStrVar = 'y';
    expect_int_300($definedStrVar &= $strVar);
    expect_string_300($definedStrVar |= $strVar);
}
self::missingAndNotInClassScope();
