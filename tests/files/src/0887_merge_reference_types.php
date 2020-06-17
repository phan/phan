<?php

function maybeModifyReferenceType(int &$arg): void {
    if ( rand() ) {
        $arg = [];
    } elseif ( rand() ) { // @phan-suppress-current-line PhanPluginDuplicateIfCondition
        $arg = true;
    }
}

function alwaysModifyReferenceType(int &$arg): void {
    if ( rand() ) {
        $arg = [];
    } else {
        $arg = true;
    }
}

function neverModifyReferenceType(int &$arg) {
    if ( rand() ) {
        $foo = true;
    } else {
        $foo = $arg;
    }
    return $foo;
}

function test887_1() { // @phan-suppress-current-line PhanUnreferencedFunction 
    $x = 42;
    maybeModifyReferenceType($x);
    '@phan-debug-var $x';
}

function test887_2() { // @phan-suppress-current-line PhanUnreferencedFunction 
    $x = 42;
    alwaysModifyReferenceType($x);
    '@phan-debug-var $x';
}

function test887_3() { // @phan-suppress-current-line PhanUnreferencedFunction 
    $x = 42;
    neverModifyReferenceType($x);
    '@phan-debug-var $x';
}