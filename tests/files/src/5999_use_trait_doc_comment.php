<?php

namespace NS5999;

// `@use Trait<T>` in a doc comment above a `use Trait;` statement gives the template parameters of the trait.
// The comment is not the comment of the member that follows the statement.

/** @template V */
trait A { /** @return V */ public function a() { throw new \Exception(); } }
/** @template V */
trait B { /** @return V */ public function b() { throw new \Exception(); } }
class C {
    /** @use A<bool> */
    use A;

    /** @use B<int> */
    use B;

    public function m(): void {}
}
class D {
    /** @use A<bool> */
    use A;
    /** Own comment */
    public function m(): void {}
}
class E {
    public function m(): void {}
    /** @use A<bool> */
    use A;
}
function test(C $c, D $d, E $e) {
    $ca = $c->a(); $cb = $c->b(); $da = $d->a(); $ea = $e->a();
    '@phan-debug-var $ca, $cb, $da, $ea';
}
/** @template X */
class G {
    /** @phpstan-use A<X> */ use A;
    /**
     * @template-use B<list<X>>
     */
    use B;
    /** @var int */
    public $p;
    public function m(): void { $x = $this->a(); $y = $this->b(); '@phan-debug-var $x, $y'; }
}
class H {
    /** @use A<string> */
    use A;
    public $noDoc = 1;
    const K = 1;
}
