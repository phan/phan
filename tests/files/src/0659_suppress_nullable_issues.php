<?php

namespace NS659;

class Base {}

class Something extends Base {
    /** @var Base */
    public static $o;
}

function expect_nonnull_base(Base $x) {
    var_export($x);
}

/**
 * @param ?Something $x
 * @return Base
 * @suppress PhanTypeMismatchArgument
 * @suppress PhanTypeMismatchReturn
 * @suppress PhanPossiblyNullTypeMismatchProperty
 */
function mismatches($x) {
    Something::$o = $x;
    expect_nonnull_base($x);
    return $x;
}
