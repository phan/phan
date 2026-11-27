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
