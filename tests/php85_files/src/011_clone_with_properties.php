<?php

namespace CloneWithProperties;

#[\AllowDynamicProperties] class Dyn {}
class O {
    public int $a = 1;
    public readonly int $r;
    private int $p = 1;
    protected int $q = 1;
    public private(set) int $s = 1;
    public int $h { get => 1; }
    public function __construct() { $this->r = 1; }
    public function inside(): static { return clone($this, ['r' => 5, 'p' => 2, 's' => 3]); }
}
final class F { public function __construct(public readonly int $x) {} public function with(int $x): static { return clone($this, ['x' => $x]); } }
function t(O $o, string $str, array $arr): void {
    $c1 = clone($o, ['a' => 2]);
    $c2 = clone($o, ['a' => 'x']);
    $c3 = clone($o, ['r' => 2]);
    $c4 = clone($o, ['p' => 2]);
    $c5 = clone($o, ['q' => 2]);
    $c6 = clone($o, ['s' => 2]);
    $c7 = clone($o, ['nope' => 2]);
    $c8 = clone(new Dyn, ['nope' => 2]);
    $c9 = clone($o, ['h' => 2]);
    $c10 = clone($o, $str);
    $c11 = clone($o, null);
    $c12 = clone($o, $arr);
    $c13 = clone(new \stdClass, ['z' => 1]);
    $c14 = clone(withProperties: ['a' => $q = 9], object: $o);
    echo $q;
    $c15 = clone($o, ['a' => clone $o]);
    var_dump($c1, $c2, $c3, $c4, $c5, $c6, $c7, $c8, $c9, $c10, $c11, $c12, $c13, $c14, $c15);
}
class ReadonlyParent {
    public function __construct(
        public readonly int $x = 1,
        public readonly int $y = 1,
        public private(set) readonly int $priv = 1,
        public public(set) readonly int $pub = 1,
    ) {}
}
class ReadonlyChild extends ReadonlyParent {
    public function withX(int $x): static { return clone($this, ['x' => $x]); }
    public function withPriv(int $v): static { return clone($this, ['priv' => $v]); }
    public function setDirect(): void { $this->y = 5; }
}
class Unrelated {
    public function f(ReadonlyParent $p): ReadonlyParent { return clone($p, ['x' => 3]); }
    public function g(ReadonlyParent $p): ReadonlyParent { return clone($p, ['pub' => 3]); }
}
