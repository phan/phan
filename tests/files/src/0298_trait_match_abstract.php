<?php
error_reporting(E_ALL);

abstract class BaseClass {
    abstract function foo1(int $arg) : void;
    abstract function foo2() : void;
    abstract function foo3();
    abstract function fooOverrideByAliasBad(int $arg) : void;
    abstract function fooOverrideByAliasGood(int $arg) : void;
    abstract function fooMatching(int $arg) : bool ;
}

trait WorkTrait {
    function foo1(string $arg) : void {
        echo "Working\n";
    }

    function foo2() : bool {
        echo "Working!\n";
        return true;
    }

    function foo3($requiredArg) {
        echo "Working!\n";
        return true;
    }

    function fooMatching(int $arg) : bool {
        echo "Working!\n";
        return true;
    }

    function fooToAliasBad(int $arg) : bool {
        return false;
    }

    function fooToAliasGood(int $arg) : void {
    }
}

class DerivedClass extends BaseClass {
    // TODO: Fix edge cases comparing visibility of these methods in later PRs.
    use WorkTrait {
        fooToAliasBad as fooOverrideByAliasBad;
        fooToAliasGood as fooOverrideByAliasGood;
    }
}

$obj = new DerivedClass;
$obj->foo1('x');
$obj->foo2();
$obj->foo3('x');
$obj->fooMatching(42);
