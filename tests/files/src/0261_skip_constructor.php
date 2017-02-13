<?php
// TODO: Change from undeclared static to undeclared constructor in a future release
class A261 {
    public function __construct(string $c) { }
}

class B261 extends A261 {
    public function __construct(string $c) {
        A261::__construct($c);
    }
}

class C261 extends B261 {
    public function __construct(string $c) {
        A261::__construct('str');
    }
}

class C261B extends B261 {
    public function __construct(string $c) {
        D261B::__construct($c);  // calling an incorrect constructor: subclass but should be ancestor
    }
}
class C261C extends B261 {
    public function __construct(string $c) {
        Missing261::__construct($c);  // calling an incorrect constructor: doesn't exist
    }
}

class Other261 {
    public function __construct(string $c) {
    }
}

class C261D extends B261 {
    public function __construct(string $c) {
        Other261::__construct($c);  // calling an incorrect constructor: different class hierarchy
    }
}

class D261B extends C261B {
    public function __construct(string $c) {
        parent::__construct($c);  // would be a stack overflow, but the problem is in the parent.
    }
}

class Self261 {
    public function __construct(string $c) {
        self::__construct($c);  // stack overflow
    }
}

class Self261B {
    public function __construct(string $c) {
        static::__construct($c);  // stack overflow
    }
}

class Self261C {
    public function __construct(string $c) {
        Self261C::__construct($c);  // stack overflow
    }
}
