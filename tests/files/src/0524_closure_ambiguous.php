<?php

namespace Bar524 {
    class Closure {}
}

namespace Foo524 {

    use Closure;
    /** @param Closure():void $closure */
    function test524a($closure) {}
    /** @param \Closure():void $closure */
    function test524b($closure) {}
}

namespace Baz524 {
    /** @param Closure():void $closure */
    function test524a($closure) {}
    /** @param \Closure():void $closure */
    function test524b($closure) {}
}

namespace {

    use Bar524\Closure;
    /** @param Closure():void $closure */
    function test524a($closure) {}
    /** @param \Closure():void $closure */
    function test524b($closure) {}
}
