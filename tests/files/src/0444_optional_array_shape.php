<?php

/**
 * @param array{strKey:string=,intKey:int=} $options
 */
function acceptsOptionArray(array $options) {
    echo strlen($options);  // should warn
    if (isset($options['intKey'])) {
        echo count($options['intKey']);
    }
    if (isset($options['strKey'])) {
        echo count($options['strKey']);
    }
}

acceptsOptionArray([]);
acceptsOptionArray(['strKey' => 2]);  // should warn
acceptsOptionArray(['strKey' => 'value']);
acceptsOptionArray(['strKey' => null]);  // should warn
acceptsOptionArray(['intKey' => 2]);
acceptsOptionArray(['intKey' => 'value']);  // should warn
acceptsOptionArray(['intKey' => null]);  // should warn
acceptsOptionArray(['intKey' => 'value', 'strKey' => 'value']);  // should warn

/**
 * @param array{nullableStrKey:?string=} $options
 */
function acceptsOptionArrayB(array $options = []) {
    if (isset($options['nullableStrKey'])) {
        echo count($options['nullableStrKey']);
    }
}

acceptsOptionArrayB([]);
acceptsOptionArrayB(['nullableStrKey' => 'value']);
acceptsOptionArrayB(['nullableStrKey' => null]);
acceptsOptionArrayB(['nullableStrKey' => 2]);  // should warn

// Mixed, so we don't know
acceptsOptionArray($GLOBALS);
