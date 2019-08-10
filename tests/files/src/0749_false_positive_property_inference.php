<?php

class HasPHPDocProp {
    /** @var ?int */
    public $bar;

    public function setBar(int $i) : void {
        $this->bar = $i;
    }

    public function getBar() : int {
        $x = $this->bar;
        if (is_int($x)) {
            // Should emit PhanRedundantCondition and set a real type.
            if (is_int($x)) {
                echo "$x is definitely an int\n";
            }
        }
        if ($this->bar) {
            return (int)$this->bar;  // should not warn
        }
        return 0;
    }
}
