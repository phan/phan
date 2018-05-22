<?php
/**
 * @property int $prop1
 * @property int $prop2 @phan-suppress-current-line PhanInvalidCommentForDeclarationType
 * @property int $prop3
 * @phan-suppress-next-line PhanInvalidCommentForDeclarationType
 * @property int $prop4
 * @property int $prop5
 */
function test_line_suppression() {
    echo $w; /** @phan-suppress-current-line PhanUndeclaredVariable */
    echo $x; // @phan-suppress-next-line PhanUndeclaredVariable
    echo $y;
    echo $z;

    call_undeclared_function_line_suppressed();  /** @phan-suppress-current-line PhanUndeclaredFunction */
    $result = call_undeclared_function_not_suppressed() + $undefVariable + stdClass::undeclaredMethod();  // @phan-suppress-current-line PhanUndeclaredStaticMethod, PhanUndeclaredVariable description goes here

    // @phan-suppress-current-line PhanUndeclaredVariable should warn about being unused
}
