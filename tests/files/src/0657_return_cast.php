<?php

namespace NS657;

class Base {
}
class Subclass extends Base {
}

/**
 * @param Base|null $x
 */
function accept_nullable_base($x) {
    var_export($x);
}
/**
 * This should not warn
 * @param ?Subclass $x
 * @return Base|null
 */
function return_nullable_base($x) {
    // Should not emit PhanTypeMismatchArgumentNullable here, either
    accept_nullable_base($x);
    return $x;
}

/**
 * This does not warn.
 * @param ?Subclass $x
 * @return ?Base
 */
function return_nullable_base2($x) {
    return $x;
}
