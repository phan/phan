<?php
namespace NSAnonymous;
class X {
    // should not emit PhanUnreferencedClass
    public static function getObject() {
        return new class extends X {};
    }
}
var_export(X::getObject());
