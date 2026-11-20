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

// The setter assigns a property that its class inherits. The type recorded for the setter is for the subclass
// and is not applied to the property of the parent class, whether the setter is declared before or after the caller.
class StaticPropInheritedParent {
    /** @var stdClass */
    protected static $prop;
}

class StaticPropInheritedSetterFirst extends StaticPropInheritedParent {
    public static function init(): void {
        self::$prop = new stdClass();
    }

    public static function getProp(): stdClass {
        if (self::$prop === null) {
            self::init();
        }
        return self::$prop;
    }
}

class StaticPropInheritedSetterLater extends StaticPropInheritedParent {
    public static function getProp(): stdClass {
        if (self::$prop === null) {
            self::init();
        }
        return self::$prop;
    }

    public static function init(): void {
        self::$prop = new stdClass();
    }
}

// The type of a property without a declared type is inferred from its default value: it is not the type after the call.
class StaticPropSetterDefaultFalse {
    private static $instance = false;

    public static function getInstance(): self {
        if (self::$instance === false) {
            self::init();
        }
        return self::$instance;
    }

    public static function getInstanceWithEarlyReturn(): self {
        if (self::$instance !== false) {
            return self::$instance;
        }
        self::init();
        return self::$instance;
    }

    private static function init(): void {
        self::$instance = new self();
    }
}

// The property has no real type: the method may assign null.
class StaticPropSetterNullablePHPDoc {
    /** @var ?stdClass */
    private static $prop;

    public static function resetProp(): void {
        if (self::$prop !== null) {
            self::reset();
            if (self::$prop === null) {
                echo "reset\n";
            }
        }
    }

    private static function reset(): void {
        self::$prop = null;
    }
}

// Writes that are not assignment expressions.
class StaticPropSetterOtherWrites {
    /** @var stdClass */
    private static $prop;
    /** @var int */
    private static $count;
    /** @var string */
    private static $key;

    public static function getAfterForeach(): stdClass {
        if (self::$prop === null) {
            self::setByForeach(['key' => new stdClass()]);
        }
        return self::$prop;
    }

    public static function getKeyAfterForeach(): string {
        if (self::$key === null) {
            self::setByForeach(['key' => new stdClass()]);
        }
        return self::$key;
    }

    public static function getAfterForeachByReference(): stdClass {
        if (self::$prop === null) {
            self::setByForeachByReference([new stdClass()]);
        }
        return self::$prop;
    }

    public static function getCount(): int {
        if (self::$count === null) {
            self::increment();
        }
        return self::$count;
    }

    public static function getCountAfterDecrement(): int {
        if (self::$count === null) {
            self::decrement();
        }
        return self::$count;
    }

    /** @param array<string,stdClass> $values */
    private static function setByForeach(array $values): void {
        foreach ($values as self::$key => self::$prop) {
            echo self::$key, "\n";
        }
    }

    /** @param list<stdClass> $values */
    private static function setByForeachByReference(array $values): void {
        foreach ($values as &self::$prop) {
            echo "set\n";
        }
    }

    private static function increment(): void {
        self::$count++;
    }

    private static function decrement(): void {
        --self::$count;
    }
}

// Creating a callable for the setter is treated as a call: calls of the callable are not tracked.
class StaticPropSetterCallable {
    /** @var stdClass */
    private static $prop;

    /** @param list<stdClass> $values */
    public static function getAfterArrayMap(array $values): stdClass {
        if (self::$prop === null) {
            array_map(self::setProp(...), $values);
        }
        return self::$prop;
    }

    public static function getAfterCallingVariable(): stdClass {
        if (self::$prop === null) {
            $setter = self::setProp(...);
            $setter(new stdClass());
        }
        return self::$prop;
    }

    public static function setProp(stdClass $v): void {
        self::$prop = $v;
    }
}

// Classes that use the same trait have separate static properties. A subclass shares the property of its parent class.
trait StaticPropSharedTrait {
    /** @var ?stdClass */
    public static $prop;
}

class StaticPropTraitUserOther {
    use StaticPropSharedTrait;
}

class StaticPropTraitUser {
    use StaticPropSharedTrait;

    public static function getAfterSettingOtherUser(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::setOtherUser();
        return self::$prop;  // Should warn: the property of another class using the trait is assigned.
    }

    public static function getAfterSettingByClassName(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::setByClassName();
        return self::$prop;
    }

    private static function setOtherUser(): void {
        StaticPropTraitUserOther::$prop = new stdClass();
    }

    private static function setByClassName(): void {
        StaticPropTraitUser::$prop = new stdClass();
    }
}

class StaticPropTraitUserChild extends StaticPropTraitUser {
    public static function getAfterSettingParent(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::setParent();
        return self::$prop;
    }

    private static function setParent(): void {
        StaticPropTraitUser::$prop = new stdClass();
    }
}

// A property with a dynamic name may be any property of its class, and no property of another class.
class StaticPropSetterDynamicName {
    /** @var ?stdClass */
    private static $prop;
    /** @var ?stdClass */
    private static $unrelated;

    public static function getProp(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::setByName('prop');
        return self::$prop;
    }

    public static function getAfterSettingOtherClass(): stdClass {
        if (self::$unrelated !== null) {
            return self::$unrelated;
        }
        self::setOtherClassByName('prop');
        return self::$unrelated;  // Should warn: a property of another class is assigned.
    }

    private static function setByName(string $name): void {
        self::$$name = new stdClass();
    }

    private static function setOtherClassByName(string $name): void {
        StaticPropTraitUserOther::$$name = new stdClass();
    }
}

// The setters are declared first. The type recorded for `self::$prop = null` is applied,
// and nothing is recorded for the assignment that follows it.
class StaticPropSetterMixedParent {
    /** @var ?stdClass */
    protected static $prop;
}

class StaticPropSetterMixed extends StaticPropSetterMixedParent {
    /** @var ?stdClass */
    private static $own;

    private static function resetOwn(): void {
        self::$own = null;
        StaticPropSetterMixed::$own = new stdClass();
    }

    // The type recorded for `self::$prop` is for this class, which does not declare the property.
    private static function resetInherited(): void {
        parent::$prop = null;
        self::$prop = new stdClass();
    }

    public static function getOwn(): stdClass {
        if (self::$own !== null) {
            return self::$own;
        }
        self::resetOwn();
        return self::$own;
    }

    public static function getInherited(): stdClass {
        if (self::$prop !== null) {
            return self::$prop;
        }
        self::resetInherited();
        return self::$prop;
    }
}
