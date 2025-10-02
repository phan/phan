<?php

namespace NS37;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class OnParameter {}

#[Attribute(Attribute::TARGET_PROPERTY)]
class OnProperty {}

#[Attribute(Attribute::TARGET_PARAMETER|Attribute::TARGET_PROPERTY)]
class OnBoth {}

class Example {
    public function __construct(
        #[OnParameter, OnProperty]
        public int $value,
        #[OnProperty]
        int $other
    ) {
        $this->value += $other;
    }
}
