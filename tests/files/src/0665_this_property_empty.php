<?php

class EmptyCheckOnThis {
    /** @var bool */
    private $prop;

    public function __construct(bool $v) {
        $this->prop = $v;
        if (empty($this->prop)) {
            // should infer false
            echo count($this->prop);
        } else {
            // should infer true
            echo count($this->prop);
        }
    }
}
