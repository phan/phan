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

    /** @suppress PhanCompatibleConstructorPropertyPromotion suppressions should work on the method */
    public function __construct(public int $value) {
    }
}
