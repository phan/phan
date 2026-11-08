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

interface ICtor5958 {
    /** @param string $arg */
    public function __construct($arg);
}

class CImplements5958 implements ICtor5958 {
    /** @inheritDoc */
    public function __construct($arg) {
        '@phan-debug-var $arg';
    }
}

/** @template T */
interface IGenericCtor5958 {
    /** @param T $value */
    public function __construct($value);
}

/** @implements IGenericCtor5958<int> */
class CImplementsGeneric5958 extends CNoConstructorParent5958 implements IGenericCtor5958 {
    /** @inheritDoc */
    public function __construct($value) {
        '@phan-debug-var $value';
    }
}

class CPrivateCtor5958 {
    /** @param string $arg */
    private function __construct($arg) {
        echo $arg;
    }
}

class CSubOfPrivateCtor5958 extends CPrivateCtor5958 {
    /** @inheritDoc */
    public function __construct($arg) {
        '@phan-debug-var $arg';
    }
}

class CMiddleNoCtor5958 extends CParent5958 {
}

class CGrandChild5958 extends CMiddleNoCtor5958 {
    /** @inheritDoc */
    public function __construct($arg) {
        parent::__construct($arg);
        '@phan-debug-var $arg';
    }
}

class CException5958 extends Exception {
    /** @inheritDoc */
    public function __construct($message = '', $code = 0) {
        parent::__construct($message, $code);
        '@phan-debug-var $message';
        '@phan-debug-var $code';
    }
}

abstract class CAbstractCtor5958 {
    /** @param list<string> $items */
    abstract public function __construct($items);
}

class CSubOfAbstractCtor5958 extends CAbstractCtor5958 {
    public function __construct(private $items) {
        '@phan-debug-var $items';
    }

    public function show(): string {
        $items = $this->items;
        '@phan-debug-var $items';
        return implode(',', $items);
    }
}

// The inline @var of a promoted property is an explicit phpdoc type (review)
class CSubInlineVar5958 extends CParent5958 {
    /** @inheritDoc */
    public function __construct(/** @var int|string */ public $arg, /** @var list<int> */ public array $items = []) {
        parent::__construct((string)$arg, array_map('strval', $items));
        '@phan-debug-var $arg';
        '@phan-debug-var $items';
    }

    public function show(): string {
        $arg = $this->arg;
        $items = $this->items;
        '@phan-debug-var $arg';
        '@phan-debug-var $items';
        return $arg . implode(',', $items);
    }
}

echo (new CSubInlineVar5958(42, [1]))->show();

// The inherited type is also the declared type of the promoted property, for assignments (review)
class CSubPublicPromoted5958 extends CParent5958 {
    /** @inheritDoc */
    public function __construct(public $arg, public array $items = []) {
        parent::__construct($arg, $items);
    }
}

function assign5958(CSubPublicPromoted5958 $c): void {
    $c->arg = new stdClass();
    $c->items = [1, 2];
    $c->items = ['a'];
}

// The template types of the class are inherited before the generic constructor is analyzed (review)
/**
 * @template U
 * @extends CGeneric5958<U>
 */
class CGenericChild5958 extends CGeneric5958 {
    /** @inheritDoc */
    public function __construct($value) {
        parent::__construct($value);
        '@phan-debug-var $value';
    }
}

function generic5958(): CGenericChild5958 {
    $c = new CGenericChild5958(42);
    '@phan-debug-var $c';
    return $c;
}

// The template types of an interface or a grandparent class are mapped through the parent class (review)
/**
 * @template T
 * @implements IGenericCtor5958<T>
 */
abstract class CAbstractGenericImplements5958 implements IGenericCtor5958 {
}

/** @extends CAbstractGenericImplements5958<int> */
class CSubOfGenericImplements5958 extends CAbstractGenericImplements5958 {
    /** @inheritDoc */
    public function __construct($value) {
        '@phan-debug-var $value';
    }
}

/** @template S */
interface IGenericSet5958 {
    /** @param S $x */
    public function set($x): void;
}

/**
 * @template T
 * @implements IGenericSet5958<T>
 */
abstract class CGenericSetParent5958 implements IGenericSet5958 {
}

/** @extends CGenericSetParent5958<int> */
class CGenericSetChild5958 extends CGenericSetParent5958 {
    /** @param int $x */
    public function set($x): void {
        echo $x;
    }
}

/** @extends CGenericSetParent5958<int> */
class CGenericSetChildInherits5958 extends CGenericSetParent5958 {
    public function set($x): void {
        '@phan-debug-var $x';
    }
}

/** @extends CGeneric5958<string> */
class CGenericMiddle5958 extends CGeneric5958 {
}

class CGenericGrandChild5958 extends CGenericMiddle5958 {
    /** @inheritDoc */
    public function __construct($value) {
        parent::__construct($value);
        '@phan-debug-var $value';
    }
}

// The copies of the promoted property inherited by subclasses get the type too (review)
class CSubOfPublicPromoted5958 extends CSubPublicPromoted5958 {
}

function assignSub5958(CSubOfPublicPromoted5958 $c): void {
    $c->arg = new stdClass();
    $c->items = ['a'];
}

/**
 * @template U
 * @extends CGeneric5958<U>
 */
class CGenericPromoted5958 extends CGeneric5958 {
    /** @inheritDoc */
    public function __construct(public $value) {
        parent::__construct($value);
    }
}

/** @extends CGenericPromoted5958<int> */
class CGenericPromotedSub5958 extends CGenericPromoted5958 {
}

function assignGeneric5958(CGenericPromotedSub5958 $c): int {
    $c->value = 'x';
    $v = $c->value;
    '@phan-debug-var $v';
    return $v;
}

// The parent class named by its class alias (enable_class_alias_support) in @extends (review)
/** @template T */
class CGenericOriginal5958 {
    /** @param T $value */
    public function __construct($value) {
        '@phan-debug-var $value';
    }

    /** @param T $value */
    public function set($value): void {
        '@phan-debug-var $value';
    }
}

class_alias('CGenericOriginal5958', 'CGenericAlias5958');

/** @extends CGenericAlias5958<int> */
class CSubOfAlias5958 extends CGenericAlias5958 {
    /** @inheritDoc */
    public function __construct($value) {
        parent::__construct($value);
        '@phan-debug-var $value';
    }

    /** @param int $value */
    public function set($value): void {
        echo $value;
    }
}
