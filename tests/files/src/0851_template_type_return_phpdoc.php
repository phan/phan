<?php
namespace NS851;

abstract class MyTest {
    /**
     * @template T
     * @param string $jn
     * @phan-param class-string<T> $jn (Phan template param)
     *
     * @return T
     * NOTE: phan 2.4.7-dev started excluding phpdoc types that didn't match the real return type.
     * "PhanTemplateTypeNotUsedInFunctionReturn Template type T not used in return value of function/method create()" started getting emitted because of that.
     *
     * @suppress PhanTypeMismatchDeclaredReturn
     */
    public static function create($jn) : MyTest {
        return new $jn();
    }
}
class MySubclass extends MyTest {
}
echo strlen(MyTest::create(MySubclass::class));
