<?php
// @inheritDoc on a constructor (#5631): the phpdoc types of the parent class's constructor are inherited
// for the parameters with the same name, and a constructor with nothing to inherit from is reported.

class CParent5958 {
    /**
     * @param string $arg
     * @param list<string> $items
     */
    public function __construct($arg, array $items = []) {
        '@phan-debug-var $arg';
        '@phan-debug-var $items';
    }
}

class CSub5958 extends CParent5958 {
    /** @inheritDoc */
    public function __construct($arg, array $items = []) {
        parent::__construct($arg, $items);
        '@phan-debug-var $arg';
        '@phan-debug-var $items';
    }
}

class CSubNoInheritDoc5958 extends CParent5958 {
    public function __construct($arg, array $items = []) {
        parent::__construct($arg, $items);
        '@phan-debug-var $arg';
        '@phan-debug-var $items';
    }
}

class CSubReordered5958 extends CParent5958 {
    /** {@inheritDoc} */
    public function __construct($extra, $arg, $items = []) {
        parent::__construct($arg, $items);
        '@phan-debug-var $extra';
        '@phan-debug-var $arg';
        '@phan-debug-var $items';
    }
}

class CSubTyped5958 extends CParent5958 {
    /**
     * @inheritdoc
     * @param int|string $arg
     */
    public function __construct($arg, int $items = 0) {
        parent::__construct((string)$arg);
        '@phan-debug-var $arg';
        '@phan-debug-var $items';
    }
}

class CSubPromoted5958 extends CParent5958 {
    /** @inheritDoc */
    public function __construct(private $arg, protected array $items = []) {
        parent::__construct($arg, $items);
        '@phan-debug-var $arg';
    }

    public function show(): string {
        $arg = $this->arg;
        $items = $this->items;
        '@phan-debug-var $arg';
        '@phan-debug-var $items';
        return $arg . implode(',', $items);
    }
}

class CNoParent5958 {
    /** @inheritDoc */
    public function __construct($arg) {
        '@phan-debug-var $arg';
    }
}

class CNoConstructorParent5958 {
}

class CSubOfNoConstructor5958 extends CNoConstructorParent5958 {
    /** @inheritDoc */
    public function __construct($arg) {
        '@phan-debug-var $arg';
    }
}

class CExtendsInternal5958 extends ArrayObject {
    /** @inheritDoc */
    public function __construct(array $array = []) {
        parent::__construct($array);
    }
}

trait TConstructor5958 {
    /** @inheritDoc */
    public function __construct($arg) {
        '@phan-debug-var $arg';
    }
}

/** @template T */
class CGeneric5958 {
    /** @param T $value */
    public function __construct($value) {
        '@phan-debug-var $value';
    }
}

/** @extends CGeneric5958<int> */
class CGenericSub5958 extends CGeneric5958 {
    /** @inheritDoc */
    public function __construct($value) {
        parent::__construct($value);
        '@phan-debug-var $value';
    }
}
