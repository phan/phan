<?php
class X {
    public $f;
    public function test(array $values) {
        $this->f = [];
        foreach ($values as $k => $v) {
            if (!isset($this->f[$v]) ||
                $this->f[$v] < $k) { // Should not emit PhanTypeInvalidDimOffset.
                $this->f[$v] = $k;
            }
        }
    }
}
