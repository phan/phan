<?php
namespace {
// All 3 of these should be detected
use const PHP_VERSION_ID;
use stdClass;
use function count;


var_export(new stdClass());
var_export([new stdClass(), count(['value']), PHP_VERSION_ID]);
}
namespace Foo553 {
    function myMethod553() {
    }
    class Bar {}
    const NS_CONSTANT = 2;
}

namespace Foo553 {
    use const PHP_VERSION_ID;
    use stdClass;  // should not warn
    use function count;

    // Should not warn, and this is referenced.
    // This prevents the interpreter from falling back to the global namespace
    use const Foo553\NS_CONSTANT;
    // Should warn
    use Foo553\Bar;
    // This is referenced,
    // and prevents the interpreter from falling back to the global namespace
    use function Foo553\myMethod553;

    new Bar();
    var_export(NS_CONSTANT);
    myMethod553();
    var_export(new stdClass());
    var_export([new stdClass(), count(['value']), PHP_VERSION_ID]);
}
