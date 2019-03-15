<?php
declare(strict_types=1);

namespace TemplateInheritance;

use ArrayAccess;
use stdClass;

// NOTE: Phan DOES NOT yet support any template methods/fields of ArrayAccess

class Base {
    /**
     * @param ArrayAccess<int> $x
     *
     * @return ArrayAccess<int>
     */
    function example (ArrayAccess $x): ArrayAccess {
        return $x;
    }
}

class Child extends Base {
    function example (ArrayAccess $x): ArrayAccess {
        return $x;
    }
}

class IncompatibleChild extends Base {
    /**
     * @param ArrayAccess<stdClass> $x
     *
     * @return ArrayAccess<int>
     */
    function example (ArrayAccess $x): ArrayAccess {
        return $x;  // should warn
    }
}

class IncompatibleChild2 extends Base {
    /**
     * @param ArrayAccess<int> $x
     *
     * @return ArrayAccess<stdClass>
     */
    function example (ArrayAccess $x): ArrayAccess {
        return $x;  // should warn
    }
}
