<?php

class C752 {
    /** @var array|string */
    public $prop;

    public function check() {
        if (is_array($this->prop)) {
            // should not warn
            return reset($this->prop);
        } elseif (is_string($this->prop)) {
            // should warn
            return reset($this->prop);
        }
    }
}
