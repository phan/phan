<?php
class A {
   public function __construct(
       public int $a,
       public ?int $b,
       protected string $c,
       protected ?string $d,
       private bool $e,
       private ?bool $f
    ) {}
}

class B extends A {
    public function __construct(
        public int $a,
        public int $b,
        protected string $c,
        protected string $d,
        private stdClass $e,
        private stdClass $f
    ) {}
}
