<?php
class A {
    private const FOO = 'foo';
}

class B extends A {
    function main() {
        echo self::FOO;// No error
        echo A::FOO;// Error
    }
}
