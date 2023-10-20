<?php

// This test documents known limitations of the union type parser such as #4814 and #2597.
// It can be turned into a regression test when those limitations are addressed.

namespace NS978;

/**
 * @phan-param '<3' $h
 */
function takesHeart( $h ) {
    '@phan-debug-var $h';
}

/**
 * @phan-return ':-['|':-]'
 */
function returnsEmoticon() {
    return rand() ? ':-[' : ':-]';
}
$emoticon = returnsEmoticon();
'@phan-debug-var $emoticon';

/**
 * @phan-param '>'|'<'|'!='|'='|'>='|'<=' $op
 */
function takesOperator( $op ) {
    '@phan-debug-var $op';
}

/**
 * @phan-param array{0:':-)'|':-]',1:'<3',2:'([{<>}])',3:'foo,bar'} $arr
 */
function takesArrayWithMultipleSpecialChars( $arr ) {
    '@phan-debug-var $arr';
}
