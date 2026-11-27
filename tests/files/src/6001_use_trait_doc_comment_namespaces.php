<?php
namespace NS6001 { /** @template V */ trait A { /** @return V */ public function a() { throw new \Exception(); } } }
namespace NS6001\X { use NS6001\A; class C { /** @use A<int> */ use A; } } namespace NS6001\Y { use NS6001\A; class C { /** @use A<string> */ use A; } }
namespace NS6001\Z {
    use NS6001\A;
    function combined(): void {
        // An anonymous class in the arguments of another one, and one in a method of it: the one in the method is visited first.
        $outer = new class(
            new class {
                /** @use A<int> */
                use A;
            }
        ) {
            public $x;
            public function __construct(object $x) { $this->x = $x; }
            public function f() {
                return new class {
                    /** @use A<string> */
                    use A;
                };
            }
            /** @use A<bool> */
            use A;
        };
        $in_method = $outer->f()->a(); $own = $outer->a();
        '@phan-debug-var $in_method, $own';
    }
    function test(\NS6001\X\C $x, \NS6001\Y\C $y): void {
        $rx = $x->a(); $ry = $y->a();
        '@phan-debug-var $rx, $ry';
    }
}
