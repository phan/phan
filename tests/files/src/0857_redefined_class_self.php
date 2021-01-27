<?php
class X {
    const DEFAULT_VALUE = 'x';
    public function f1() {
        return self::DEFAULT_VALUE;
    }
}

class X {
    const DEFAULT_VALUE2 = 'x';
    public function f2() {
        return self::DEFAULT_VALUE2;  // TODO mitigate if possible
    }
}
