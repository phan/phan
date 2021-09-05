<?php
/** @phan-file-suppress PhanPluginUseReturnValueNoopVoid, PhanUnreferencedFunction, PhanPluginDescriptionlessCommentOnFunction */
/** @phan-file-suppress PhanPluginCanUsePHP71Void, PhanPluginNoCommentOnFunction */

function getPossiblyEmptyArray(): array {
    return $_GET['foo'];
}

function test1(): void {
    $array = getPossiblyEmptyArray();
    if( count($array) === 0 ){
        '@phan-debug-var $array';
        return;
    }
    echo "done\n";
    '@phan-debug-var $array';
}

function test1b(): void {
    $array = getPossiblyEmptyArray();
    if( count($array) == 0 ){
        '@phan-debug-var $array';
        return;
    }
    echo "done\n";
    '@phan-debug-var $array';
}

function test2(): void {
    $array = getPossiblyEmptyArray();
    if( count($array) < 1 ){
        '@phan-debug-var $array';
        return;
    }
    echo "done\n";
    '@phan-debug-var $array';
}

function test3(): void {
    $array = getPossiblyEmptyArray();
    if( !count($array) ){
        '@phan-debug-var $array';
        return;
    }
    echo "done\n";
    '@phan-debug-var $array';
}

function test4(): void {
    $array = getPossiblyEmptyArray();
    if( !$array ){
        '@phan-debug-var $array';
        return;
    }
    echo "done\n";
    '@phan-debug-var $array';
}

function test5(): void {
    $array = getPossiblyEmptyArray();
    if( $array === [] ){
        '@phan-debug-var $array';
        return;
    }
    echo "done\n";
    '@phan-debug-var $array';
}
function test6(): void {
    $array = getPossiblyEmptyArray();
    if( $array == [] ){
        '@phan-debug-var $array';
        return;
    }
    echo "done\n";
    '@phan-debug-var $array';
}
