<?php

trait TT {
    public $traitProperty;
    public function traitInstanceMethod() {
        return 42;
    }
}

// Note: Phan only warns about enums when no cases when there is at least one instance method that cannot be used
// because enums may end up getting used as a shorthand for collections of static methods/constants that cannot be instantiated.
enum Mu {
    use TT;
    const SIC = '[sic]';

    // Declaring even a static property is an error on enums.
    // The feature set was deliberately limited for the initial implementation and may be relaxed in the future.
    public static $publicStaticProperty = 123;
    public $publicProperty = 123;

    public function instanceMethod(int $arg): int {
        return $arg * 2;
    }

    public static function staticMethod(int $arg): int {
        return $arg * 2;
    }
}
var_dump(Mu::staticMethod(21));
Mu::$publicStaticProperty *= 2;
var_dump(Mu::$publicStaticProperty);

class NotEnum {
    case FOO;
}

enum Suit {
    case HEARTS = 'H';
}
var_dump(clone Suit::HEARTS);  // cannot clone
