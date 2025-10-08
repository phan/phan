<?php

/* @phan-file-suppress PhanUnreferencedFunction,PhanUnusedGlobalFunctionParameter */

namespace TestPreferNamespaceUse1 {
    class TestObject {}
}

namespace TestPreferNamespaceUse2 {
    use TestPreferNamespaceUse1\TestObject;

    function testMethodParam( TestObject $param ): void {
    }

    function testMethodReturn(): TestObject {
        return new \TestPreferNamespaceUse1\TestObject();
    }

    function testNullable(): ?TestObject {
        return new \TestPreferNamespaceUse1\TestObject();
    }

    function testUnionTypes ( TestObject|string $param ) : bool|TestObject {
        return new \TestPreferNamespaceUse1\TestObject();
    }
}
