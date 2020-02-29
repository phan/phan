<?php

namespace NS3;

use ArrayObject;

class Y extends X {

}
class Z extends Y {
    public function test() {
        // Phan should infer that $this->val is of type ArrayObject when --analyze-twice is used,
        // despite the assignment occurring later in analysis.
        $this->val->missingMethod();
    }
}
class X {
    protected $val;
    public function __construct(ArrayObject $val) {
        $this->val = $val;
    }
}
