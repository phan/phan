<?php

interface I {
    public function f(array $a, array $b);
}

trait T {
    public function f(array $a, array $b) { }
}

class C implements I {
    use T;
    public function f(array $a) { }
}

class C2 implements I {
    use T;
}
