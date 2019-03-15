<?php

/**
 * @template T
 */
class C{
    /**
     * @suppress PhanGenericConstructorTypes, PhanTemplateTypeNotDeclaredInFunctionParams
     */
    public function __construct(int $x) {
    }
}
$c = new C(2);
