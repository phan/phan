<?php
var_export([
    +1 === 0,
    -1 === 0,
    ~1 === 0,
    ~1e100 === 0,
    ~INF === 0,
    ~NAN === 0,
    // Phan does not bother representing infinite numbers in its type system.
    // This is a sanity check this doesn't crash.
    +INF === 0.0,
    +NAN === 0.0,
    -INF === 0.0,
    -NAN === 0.0,
    ~'1' === 0,
    ~~'0' === 0,
]);
