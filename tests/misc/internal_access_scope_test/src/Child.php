<?php
namespace App;

// A class of the project that extends a class of another package.
class Child extends \Acme\Lib\Base {
}

/**
 * An internal class of the project that extends a class of another package.
 * @internal
 */
class InternalChild extends \Acme\Lib\Base {
}

// The internal method of the other package is not a part of the project, also not in the namespace of the class that inherits it: reported.
function sameNamespace(Child $child): int {
    return $child->innerOfBase();
}
