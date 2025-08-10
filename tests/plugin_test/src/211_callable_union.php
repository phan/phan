<?php
// @phan-file-suppress PhanTypeMismatchArgumentProbablyReal (suppress redundant errors that aren't from CallableParamPlugin)

/**
 * @param callable|array $x
 */
function test211_array($x) {
    if (is_callable($x)) {
        return $x();
    } else {
        return count($x);
    }
}

test211_array('strval');
test211_array(['Closure', 'fromCallable']);
test211_array([]);
test211_array([1, 2, 3]);
test211_array('puppies'); // only this one is an error

/**
 * @param callable|string $x
 */
function test211_string($x) {
    if (is_callable($x)) {
        return $x();
    } else {
        return strlen($x);
    }
}

test211_string('strval');
test211_string(['Closure', 'fromCallable']);
test211_string([]); // these two are errors
test211_string([1, 2, 3]); // these two are errors
test211_string('puppies');
test211_string('not--even**a\\valid//FQSEN');

/**
 * @param callable|class-string $x
 */
function test211_classstring($x) {
    if (is_callable($x)) {
        return $x();
    } else {
        return new $x();
    }
}

test211_classstring('stdClass');
test211_classstring('strval');
test211_classstring('puppies'); // this one is an error
test211_classstring('not--even**a\\valid//FQSEN'); // this one too

/**
 * @param callable|class-string|string $x
 */
function test211_classstring_other($x) {
    if (is_callable($x)) {
        return $x();
    } elseif (class_exists($x)) {
        return new $x();
    }
    return null;
}

test211_classstring_other('stdClass');
test211_classstring_other('strval');
test211_classstring_other('puppies'); // not an error any more
test211_classstring_other('not--even**a\\valid//FQSEN'); // not an error
