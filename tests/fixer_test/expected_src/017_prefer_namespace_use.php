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
}
