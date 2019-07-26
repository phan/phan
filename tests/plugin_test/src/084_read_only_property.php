<?php

class X84 {
    private $arr = ['default'];
    protected $count = 0;
    public function main() {
        return [$this->arr, $this->count];
    }
}
(new X84())->main();
