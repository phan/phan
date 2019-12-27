<?php

class TestBranch {
    private $arr;

    private function arr() {
        $v = $this->arr;
        '@phan-debug-var $v';
        if (isset($this->arr)) {
            $v = $this->arr;
            '@phan-debug-var $v';
        } else {
            $this->arr = [];
            $v = $this->arr;
            '@phan-debug-var $v';
        }
        $v = $this->arr;
        '@phan-debug-var $v';

        return $this->arr;
    }
}
