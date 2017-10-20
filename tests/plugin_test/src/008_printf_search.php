<?php

class MyClass {
    const MY_ARRAY_CONSTANT = 'class const: %1$d of 2';
    public static function main() {
        printf(self::MY_ARRAY_CONSTANT, 2, 4);
    }
}
MyClass::main();

const MY_CONSTANT = 'global const Hello, %s';

printf("%s");
\printf(MY_CONSTANT, "World");
\printf(MY_CONSTANT, "World", "Extra");
\printf(MY_CONSTANT);
