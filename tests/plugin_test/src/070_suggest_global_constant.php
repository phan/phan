<?php

namespace {
function test_constant_completion() {
    $myVar = test_constant_completion;
    echo myVar;
    echo AST_BINARY_OP;
    $AST_UNARY_OP = 42;
    echo AST_UNARY_OP;
    echo other\AST_UNARY_op;
}
test_constant_completion();
}

namespace TestNS {
const SOME_lowercase_constant = 'x';

class Foo70 {
    const SOME_CONSTANT_NAME = [2];
    const AST_BINARY_OP = '+';
    public static $AST_BINARY_OP = 'xx';
    private $SOME_LOWERCASE_CONSTANT = 'xx';
    public static function main() {
        var_export(AST_BINARY_OP);
        var_export(SOME_LOWERCASE_CONSTANT);
        return SOME_CONSTANT_NAME;
    }
}
Foo70::main();
}
