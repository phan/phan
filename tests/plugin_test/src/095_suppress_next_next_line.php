<?php
// @phan-suppress-next-next-line PhanUndeclaredFunction
missing_func1();
missing_func2();
missing_func3();
/**
 * Do something
 * @phan-suppress-next-next-line PhanUndeclaredFunction
 */
missing_func4();
missing_func5();
missing_func6();
missing_func6();  // @phan-suppress-previous-line PhanUndeclaredFunction
/**
 * @phan-suppress-next-next-line PhanUndeclaredTypeParameter
 */
function test_missing_class(MissingClass $x) {
    var_export($x);
}
// @phan-suppress-next-next-line PhanTypeMismatchArgument this refers to the wrong line
test_missing_class(new stdClass());
