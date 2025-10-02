<?php
/** @deprecated */
#[Attribute]
class DeprecatedAttribute35 {
    private function __construct() {}
}

#[Attribute(Attribute::TARGET_ALL|Attribute::IS_REPEATABLE)]
class TypedAttribute35 {
    private function __construct(public int $flags) { }
}

#[Attribute([])]
#[DeprecatedAttribute35('extra')]
#[TypedAttribute35([])]
#[TypedAttribute35]
class MyAttribute35 {}

