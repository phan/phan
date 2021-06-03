<?php
class A952 {
    public function main(): Closure {
        return static fn () => $this;
    }
}
(new A952())->main()();
