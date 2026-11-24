<?php
$a = new readonly class {
    public function __construct(public int $x = 1) {}
};
$b = new #[Attr] readonly class(1) {
    public function __construct(public int $x) {}
};
$c = new readonly class(2) extends ArrayObject implements Countable {
};
$d = new readonly class(3, 4) {
    public int $y = 4;

    public function g(): int {
        return $this->y;
    }
};
$e = new #[Attr] readonly class(5) extends ArrayObject {
    public function f() {}
};
