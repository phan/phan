<?php

// Should resolve 'globals_type_map' relative to root namespace, no matter where it's used
namespace Foo {
    echo intdiv($test_global_exception, 2);
    echo $test_global_exception->getMessage();
    echo $test_global_error->getMessage();
    echo intdiv($test_global_error, 2);
}

namespace {
    echo intdiv($test_global_exception, 2);
    echo $test_global_exception->getMessage();
    echo $test_global_error->getMessage();
    echo intdiv($test_global_error, 2);
}
