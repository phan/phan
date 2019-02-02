<?php
$a = 2;
empty($a);
isset($a);

// TODO: Also warn about whether these casts are impossible (e.g. certain scalars to/from array/object)
(string)$a;
(int)$a;
(bool)$a;
(unset)$a;
(float)$a;
(array)$a;
(object)$a;
/** @return ?string */
function some_function() {
    echo "Something";
    return rand(0,1) ? "value" : null;
}
// This is a NoopBinaryOperator because it does the same thing without the right hand side
// of the `??` expression - evaluating the right hand s.
// Other `??` expressions might not be.
some_function() ?? 'default';
// this does not emit NoopBinaryOperator - The right hand side has side effects
some_function() ?? printf("This has side effects - %s\n", PHP_VERSION);
// This does - it can guess if complicated expressions have no side effects
some_function() ?? (stdClass::class . (PHP_VERSION_ID & ~1));
// Should also do the same thing check of the right hand side for short-circuiting binary expressions
some_function() && 'default';
some_function() || true;
