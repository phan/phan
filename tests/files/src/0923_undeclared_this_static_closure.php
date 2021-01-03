<?php
class X923 {
    public function foo() {
        var_export($this);
        return function () {
            return static function () {
                $this->bar();
            };
        };
    }
}
(new X923())->foo()();
