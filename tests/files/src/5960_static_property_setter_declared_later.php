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
