<?php

namespace TestLazy;

function myFunc(): void {}

// header_register_callback takes a callable param but is lazy-loaded.
// Our plugin should detect the casing mismatch even for lazy-loaded functions.
function test_lazy_loaded_callable(): void {
    header_register_callback('TestLazy\MYFUNC');
}
