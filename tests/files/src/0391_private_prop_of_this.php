<?php

class Base391 {
    private $_prop;
    private $_prop2;
}

// Note that traits can't define constants.
trait Trait391 {
    // Trait inheritance of properties is different from class inheritance.
    // The class using this trait can access this private property.
    private $_propFromTrait;

    public function accessTraitProp() {
        echo "value = " . $this->_propFromTrait . "\n";
    }
}

class Subclass391 extends Base391 {
    use Trait391;

    public function __construct($value) {
        $this->_propFromTrait = $value;
        $this->_prop = $value;
        $this->_propOther = $value;
    }

    public function getter() {
        return $this->_prop2 + $this->_prop2Other;
    }
}
$c = new Subclass391('v');
$c->accessTraitProp();
