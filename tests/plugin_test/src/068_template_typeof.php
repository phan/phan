<?php
/**
 * @template TTemplateType $x is a description
 * @param class-string<TTemplateType> $x
 * @phan-closure-scope TTemplateType
 * @return TTemplateType (wrong, it returns a class-string)
 */
$cb = function ($x) {
    var_export($this->method());
    var_export($x->method());
    return $x;
};
