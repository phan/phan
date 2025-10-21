<?php
// https://github.com/Microsoft/tolerant-php-parser/issues/98

class A {
    use X, Y, Z  {
        \X::b insteadof Y, Z;
    }
}