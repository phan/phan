<?php

namespace NS688;

class CalledClass {
    function isInstance() {
        return true;
    }
}

class OtherClass {
    public function instanceMethod() {
        self::ownMethod();
        var_export(CalledClass::isInstance());  // Expected: should warn. Observed: no warning
        CalledClass::isInstance();  // should warn
        call_user_func(function () {
            CalledClass::isInstance();  // should warn
            self::ownMethod();
            OtherClass::ownMethod();
        });
    }
    public static function staticMethod() {
        self::ownMethod();  // should warn
        OtherClass::ownMethod();  // should warn
        var_export(CalledClass::isInstance());  // Expected: should warn. Observed: no warning
        CalledClass::isInstance();  // should warn
        call_user_func(function () {
            CalledClass::isInstance();  // should warn
            self::ownMethod();  // should warn
            OtherClass::ownMethod();  // should warn
        });
    }

    public function ownMethod() {
        echo "in own method";
    }
}
OtherClass::staticMethod();
OtherClass::ownMethod();  // should warn
