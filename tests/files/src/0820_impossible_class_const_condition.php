<?php

namespace NS820;

use Closure;
use Exception;

const MY_GLOBAL = 2;
if ($_ENV['USE_INT']) {
    define('MY_DYNAMIC_GLOBAL_ENTRY', 3);
} else {
    define('MY_DYNAMIC_GLOBAL_ENTRY', true);
}

class HasConst {
    const MY_VAL = 1;
}

class MyClass {
    public function doOperation(Closure $op) {
        try {
            $result = (bool)$op();
            if ($result === MY_GLOBAL) {
                return false;
            }
            if ($result === MY_DYNAMIC_GLOBAL_ENTRY) {
                return false;
            }
            return $result;
        } catch (Exception $e) {
            if (!$e->getCode() === HasConst::MY_VAL) {
                throw $e;
            }
            // ignore this
        }
        return false;
    }
}
