<?php

class A236 {
    /** @var string */
    public $badDoc;

    /** @var A236 */
    public $goodDoc;
    public function foo() {
        $this->badDoc = &$this;
        $this->goodDoc = &$this;
        $this->badDoc = $this;
        $this->goodDoc = $this;
    }
}
