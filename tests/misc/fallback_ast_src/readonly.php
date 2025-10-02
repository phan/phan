<?php
class ICS {
    public readonly $var;
    public function __construct(public readonly $other) {
        $this->var = $other;
    }
}
