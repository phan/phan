<?php

namespace NS658;

class Base {
}
class Subclass extends Base {
}

class O {
    /** @var Base|null */
    public static $var;

    /** @var Base */
    public static $nonnull_var;
}
/**
 * @param ?Subclass $x
 */
function handle_nullable_base($x) {
    O::$var = $x;
    O::$nonnull_var = $x;  // Should emit PhanPossiblyNullTypeMismatchProperty
    O::$nonnull_var = null;  // Should emit PhanTypeMismatchProperty
}
