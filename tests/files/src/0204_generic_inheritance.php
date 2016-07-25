<?php

function f(bool $p) {}

/**
 * @template T1
 */
class C1 {
    /** @var T1 */
    public $p;

    /** @param T1 $p */
    public function __construct($p) {
        $this->p = $p;
    }
}

/**
 * @extends C1<int>
 */
class C2 extends C1 {
    /** @param int $p */
    public function __construct($p) {
        parent::__construct($p);
    }
}

f((new C2('string'))->p);

class C3 extends C1 {
    public function __construct($p) {
        parent::__construct($p);
    }
}

f((new C3(false))->p);

/**
 * @template T2
 * @extends C1<T2>
 */
class C4 extends C1 {
    /** @param T2 $p */
    public function __construct($p) {
        parent::__construct($p);
    }
}

f((new C4('string'))->p);
f((new C4(42))->p);

/**
 * @extends NotFound<string>
 */
class C7 extends C1 {
    /** @param string $p */
    public function __construct($p) {
        parent::__construct($p);
    }
}

/**
 * @extends C1<NotFound>
 */
class C8 extends C1 {
    /** @param NotFound $p */
    public function __construct($p) {
        parent::__construct($p);
    }
}
