<?php

class FIXME_IMPLEMENT_CONSTRUCTOR_PROMOTION21 {
    public function __construct(
        public int $value
    ) {
        echo $value[0];
    }
}
$x = new FIXME_IMPLEMENT_CONSTRUCTOR_PROMOTION21('invalid');
echo strlen($x->value);
