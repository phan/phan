<?php
class HasUntypedProperty {
    public $x;
    public function __construct($x) {
        $this->x = $x;
    }
}
class HasUntypedPropertyPromotion {
    public function __construct(public $x) {
        echo strlen($this->x);
    }
}
$c = new HasUntypedProperty(new stdClass());
echo strlen($c->x);
$c = new HasUntypedPropertyPromotion([]);
echo strlen($c->x);
