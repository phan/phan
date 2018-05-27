<?php
/**
 * @property int $prop1
 * @property int $prop2 @phan-suppress-current-line PhanInvalidCommentForDeclarationType
 * @property int $prop3
 * @phan-suppress-next-line PhanInvalidCommentForDeclarationType
 * @property int $prop4
 * @property int $prop5
 * @phan-file-suppress MissingIssueType,PhanParamTooMany
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

class C1 { private static $p = 42; }
print C1::$p;
// These issues might change later on.
// This is an example of phan-file-suppress working as a line comment anywhere in this file.
// @phan-file-suppress PhanAccessPropertyPrivate, PhanUnreferencedClass, PhanUnreferencedPrivateProperty

// @phan-suppress-next-line PhanUndeclaredVariable should warn about being unused
