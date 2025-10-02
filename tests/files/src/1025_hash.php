<?php

function expect_hash_context(HashContext $c) {
}
function hash_test() : string {
    $ctx = hash_init('md5');
    hash_update($ctx, 'The quick brown fox ');
    hash_update($ctx, 'jumped over the lazy dog.');
    intdiv($ctx, 2);  // invalid.
    expect_hash_context($ctx);
    $result = hash_final($ctx);
    intdiv($result, 2);  // invalid
    return $result;
}
