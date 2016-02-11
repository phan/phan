<?php

class C {

    /**
     * @suppress PhanCompatiblePHP7
     * @suppress PhanNonClassMethodCall
     * @suppress PhanNoopArray
     * @suppress PhanNoopVariable
     * @suppress PhanParamTooFew
     * @suppress PhanParamTooFewInternal
     * @suppress PhanParamTooManyInternal
     * @suppress PhanTypeArraySuspicious
     * @suppress PhanTypeComparisonFromArray
     * @suppress PhanTypeComparisonToArray
     * @suppress PhanTypeMismatchArgumentInternal
     * @suppress PhanTypeMismatchForeach
     * @suppress PhanUndeclaredClassCatch
     * @suppress PhanUndeclaredClassInstanceof
     * @suppress PhanUndeclaredClassMethod
     * @suppress PhanUndeclaredVariable
     * @suppress PhanUndeclaredVariable
     */
    function f() {
        $v = Undef::undef();
        $v1->$v2[0]();
        [1,2,3];
        $v3 = 42;
        $v3;
        strlen();
        strlen('str', 42);
        $v8 = null;
        $v8->f();
        $v4 = false; if($v4[1]) {}
        if ([1, 2] == 'string') {}
        if (42 == [1, 2]) {}
        strlen(42);
        foreach (null as $i) {}
        try {} catch (Undef $exception) {}
        $v5 = null;
        if ($v5 instanceof Undef) {}
        $v9 = $v10;

        /** @suppress PhanNoopClosure */
        function() {};
    }

}
