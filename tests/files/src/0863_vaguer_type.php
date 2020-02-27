<?php
/**
 * @param object $dom
 */
function vaguer_comment(DOMDocument $dom) {
    $dom->missingMethod('test');
}
/**
 * @param ArrayAccess $ao
 */
function base_class(ArrayObject $ao) {
    return $ao->count('unexpected');
}
/**
 * @param stdClass $dom
 */
function wrong_comment(DOMDocument $dom) {
    $dom->missingMethod('test');
}
