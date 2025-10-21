<?php
// https://github.com/Microsoft/tolerant-php-parser/issues/98

class A {
    use \A {
        \a::b insteadof b;
        a as d;
    }
}