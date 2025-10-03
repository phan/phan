<?php
// Still not finished
class ConstructorPromotion21 {
    public function __construct(
        public int $value, private MissingClass $other = null
    ) {
        echo $value[0];
    }
}
$x = new ConstructorPromotion21('invalid');
echo strlen($x->value);

class DuplicatePromotedProperty {
    public int $value;


    public function __construct(public int $value) {
    }
}
