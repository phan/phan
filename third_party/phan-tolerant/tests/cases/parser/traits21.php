<?php
// https://github.com/Microsoft/tolerant-php-parser/issues/98

class A {
    use \A {
        a as b;
        \c\d::a as d;
    }
}