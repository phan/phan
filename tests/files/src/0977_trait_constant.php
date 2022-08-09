<?php
trait T977 {
    const PUBLIC_CONST = 123;
    private const PRIVATE_CONST = 2;
    protected const PROTECTED_CONST = 2;
}
class C977 {
    use T977;
    public static function inner() {
        // Does not warn: PhanAccessClassConstantOfTraitDirectly private constants in traits become private to the scope of the class using the traits
        echo self::PUBLIC_CONST;
        echo self::PRIVATE_CONST;
        echo self::PROTECTED_CONST;
    }
}
class Other {
    public static function inner() {
        echo C977::PUBLIC_CONST;
        echo C977::PRIVATE_CONST; // should warn
        echo C977::PROTECTED_CONST; // should warn
    }
}
C977::inner();
function global_fn() {
    echo T977::PUBLIC_CONST;  // should warn, direct access to trait constants is forbidden
    echo C977::PUBLIC_CONST;  // allowed
    echo T977::PRIVATE_CONST;  // should warn, direct access to trait constants is forbidden
    echo C977::PRIVATE_CONST;  // should warn, direct access to private constants is forbidden here
    echo T977::PROTECTED_CONST;  // should warn, direct access to trait constants is forbidden
    echo C977::PROTECTED_CONST;  // should warn, direct access to protected constants is forbidden here
}
