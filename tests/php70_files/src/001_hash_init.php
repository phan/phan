<?php

// hash_init started to return a resource in php 7.2.
// Because this file is analyzed as if the codebase is php 7.0, this should warn.
function testHash() {
    $context = hash_init('md5');
    echo spl_object_hash($context);  // should warn
    hash_update($context, 'Example text');
    echo hash_final($context);
}
testHash();
