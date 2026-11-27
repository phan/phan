<?php
namespace NS6000; use ArrayObject; class First { /** @use A<int> */ use A; } class Second { /** @use A<string> */ use A; }
/** @template V */
trait A { /** @return V */ public function a() { throw new \Exception(); } }
class Multi {
    /**
     * @use A<
     *     array{x: int}
     * >
     */
    use A;
}
class PhanFirst {
    /**
     * @phan-use A<int>
     * @use A<string>
     */
    use A;
    public function m(): void { $f = function () use (&$f) { return new class { /** @use A<float> */ use A; }; }; }
}
function test(First $f, Second $s, Multi $m, PhanFirst $p) {
    $fa = $f->a(); $sa = $s->a(); $ma = $m->a(); $pa = $p->a();
    '@phan-debug-var $fa, $sa, $ma, $pa';
}

class Later {
    public function text(string $x): string {
        // A `}` that is text of a string is not a brace of the code.
        return "{$x}}";
    }

    /**
     * @template-use A<
     *     list<int>
     * >
     */
    use A;

    public function make(): object {
        // The body of an anonymous class is the brace after its arguments.
        return new class(function () {}) {
            /** @use A<float> */
            use A;
            public function __construct(callable $c) {}
            public function check(): void { $inner = $this->a(); '@phan-debug-var $inner'; }
        };
    }
}
class Prose {
    /** Example: @use A<string> is not an annotation */
    use A;
    public function m(): void {}
}
function testMore(Later $l, Prose $p) {
    $la = $l->a(); $pa = $p->a();
    '@phan-debug-var $la, $pa';
}

function anonymous(): void {
    // Two anonymous classes in one line, and an anonymous class as an argument of another one.
    $a = new class { /** @use A<int> */ use A; }; $b = new class { /** @use A<string> */ use A; };
    $c = new class(new class {}) {
        /** @use A<bool> */
        use A;
        public function __construct(object $x) {}
    };
    $ra = $a->a(); $rb = $b->a(); $rc = $c->a();
    '@phan-debug-var $ra, $rb, $rc';
}
