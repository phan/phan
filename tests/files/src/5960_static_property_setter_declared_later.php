<?php

// The static methods that set the static property are declared after the methods calling them.
class StaticPropSetterDeclaredLater {
    /** @var stdClass */
    private static $prop;

    public static function getProp(): stdClass {
        if (self::$prop === null) {
            self::setProp(new stdClass());
        }
        return self::$prop;
    }

    public static function getPropWithEarlyReturn(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        static::setProp(new stdClass());
        return self::$prop;
    }

    public static function getPropRecursively(int $depth): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::initRecursively($depth);
        return self::$prop;
    }

    public static function getPropWithoutSetting(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::log('not set');
        return self::$prop;  // Should warn: log() does not set the property.
    }

    public static function setProp(stdClass $v): void {
        self::$prop = $v;
    }

    private static function initRecursively(int $depth): void {
        if ($depth > 0) {
            self::initRecursively($depth - 1);
            return;
        }
        self::$prop = new stdClass();
    }

    private static function log(string $message): void {
        echo $message, "\n";
    }
}

// The property has a nullable real type.
class StaticPropSetterDeclaredLaterTyped {
    private static ?stdClass $prop = null;

    public static function getProp(): stdClass {
        if (self::$prop === null) {
            self::init();
        }
        return self::$prop;
    }

    public static function getPropWithEarlyReturn(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::init();
        return self::$prop;
    }

    private static function init(): void {
        self::$prop = new stdClass();
    }
}

// The property has no declared type.
class StaticPropSetterDeclaredLaterUntyped {
    private static $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::init();
        }
        return self::$instance;
    }

    private static function init(): void {
        self::$instance = new self();
    }
}

// The setter is declared in a parent class that is declared later.
class StaticPropSetterChildDeclaredFirst extends StaticPropSetterParentDeclaredLater {
    public static function getWithParent(): stdClass {
        if (parent::$instance !== null) {
            return parent::$instance;
        }
        parent::init();
        return parent::$instance;
    }

    public static function getWithSelf(): stdClass {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::init();
        return self::$instance;
    }
}

class StaticPropSetterParentDeclaredLater {
    /** @var stdClass */
    protected static $instance;

    protected static function init(): void {
        self::$instance = new stdClass();
    }
}

// Only the assignments that the called method makes to the property of the caller are used.
class StaticPropOtherClass {
    /** @var stdClass */
    public static $prop;
}

class StaticPropSetterTargets {
    /** @var stdClass */
    private static $prop;
    /** @var string */
    private static $key;
    /** @var array<string,int> */
    private static $counts = [];

    public static function getAfterSettingByClassName(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::setByClassName();
        return self::$prop;
    }

    public static function getAfterSettingOtherClass(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::setOtherClass();
        return self::$prop;  // Should warn: the property of another class is assigned.
    }

    public static function getAfterCreatingClosure(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::createSetter();
        return self::$prop;  // Should warn: the closure that assigns the property is not called.
    }

    public static function getKeyAfterReadingIt(): string {
        if (self::$key !== null) {
            return self::$key;
        }
        self::count();
        return self::$key;  // Should warn: the property is read as a dimension and not assigned.
    }

    public static function getKeyAfterDestructuring(): string {
        if (self::$key !== null) {
            return self::$key;
        }
        self::destructure(['a', 'b']);
        return self::$key;
    }

    private static function setByClassName(): void {
        StaticPropSetterTargets::$prop = new stdClass();
    }

    private static function setOtherClass(): void {
        StaticPropOtherClass::$prop = new stdClass();
    }

    private static function createSetter(): Closure {
        return static function (): void {
            self::$prop = new stdClass();
        };
    }

    private static function count(): void {
        self::$counts[self::$key] = 1;
    }

    /** @param list<string> $values */
    private static function destructure(array $values): void {
        [, self::$key] = $values;
    }
}

// The setter of the parent class assigns the property of the parent class, not the one that the subclass redeclares.
class StaticPropRedeclaredChild extends StaticPropRedeclaredParent {
    /** @var stdClass */
    protected static $prop;

    public static function getProp(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::init();
        return self::$prop;  // Should warn
    }

    public static function getShared(): stdClass {
        if (self::$shared !== null) {
            return self::$shared;
        }
        self::init();
        return self::$shared;
    }
}

class StaticPropRedeclaredParent {
    /** @var stdClass */
    protected static $prop;
    /** @var stdClass */
    protected static $shared;

    protected static function init(): void {
        self::$prop = new stdClass();
        self::$shared = new stdClass();
    }
}
