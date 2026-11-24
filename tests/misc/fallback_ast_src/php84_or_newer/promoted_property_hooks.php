<?php
class PromotedHooks {
    public function __construct(
        public string $y { get => "a"; },
        public int $x = 1 { get => $this->x; set => $value; },
        private(set) array $z = [] {
            &get => $this->z;
        },
        public array $v = [] {
            set(array $value) {
                $this->v = $value;
            }
        },
        int $u = 5 { get => 6; },
        protected int $w = 2
        {
            get
            =>
            $this->w;
        },
    ) {}
}
class WithObjectDefault {
    public function __construct(
        public \stdClass $x = new \stdClass() { get => $this->x; },
        public \stdClass $y = new \stdClass { get => $this->y; },
    ) {}
}
