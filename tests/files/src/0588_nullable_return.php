<?php
declare(strict_types=1);

interface MyArrInterface {
    public function methodReturningArrayOrNull(): ?iterable;
}

class MyArrImplementation implements MyArrInterface {
    public function methodReturningArrayOrNull(): array {
        return [0.0];
    }
}

class MyBadArrImplementation implements MyArrInterface {
    public function methodReturningArrayOrNull(): string {
        return 'x';
    }
}

(new MyArrImplementation())->methodReturningArrayOrNull();
