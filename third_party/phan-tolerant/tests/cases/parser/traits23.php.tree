<?php

// TODO should produce error
// https://github.com/Microsoft/tolerant-php-parser/issues/99
class A {
    use \A {
        \a as b;
        \b insteadof C;
    }
}