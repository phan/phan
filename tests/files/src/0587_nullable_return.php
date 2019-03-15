<?php
declare(strict_types=1);

interface MyInterface {
    public function methodReturningFloatOrNull(): ?float;
}

class MyImplementation implements MyInterface {
    public function methodReturningFloatOrNull(): float {
        return 0.0;
    }
}

class MyBadImplementation implements MyInterface {
    public function methodReturningFloatOrNull(): string {
        return 'str';
    }
}

(new MyImplementation())->methodReturningFloatOrNull();
