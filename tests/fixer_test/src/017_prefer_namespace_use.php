<?php

/* @phan-file-suppress PhanUnreferencedFunction,PhanUnusedGlobalFunctionParameter */

namespace TestPreferNamespaceUse1 {
    class TestObject {}
}

namespace TestPreferNamespaceUse2 {
    use TestPreferNamespaceUse1\TestObject;

    function testMethodParam( \TestPreferNamespaceUse1\TestObject $param ): void {
    }

    function testMethodReturn(): \TestPreferNamespaceUse1\TestObject {
        return new \TestPreferNamespaceUse1\TestObject();
    }
}
