<?php
// Phan warns when a property is unset and tracks the value, for both declared and undeclared properties.
class DynamicProperty742 {
    /** @var string */
    public $declared_prop = 'value';
    public function main() {
        unset($this->prop);
        echo intdiv($this->prop, 2);
        unset($this->declared_prop);
        echo intdiv($this->declared_prop, 2);
        $this->prop = [];
        echo intdiv($this->prop, 2);
        $this->prop = new stdClass();
        echo intdiv($this->prop, 2);
    }
}
