<?php
namespace NS5972;

class Base {}
class Child extends Base {}
class Other {}

/** @param \Closure(Child):Base $closure */
function takes_child_closure(\Closure $closure): void { var_dump($closure); }
/** @param callable(Child):void $callable */
function takes_child_callable(callable $callable): void { var_dump($callable); }
/** @param callable(Base):void $callable */
function takes_base_callable(callable $callable): void { var_dump($callable); }
/** @param callable(int):void $callable */
function takes_int_callable(callable $callable): void { var_dump($callable); }

// A callable with a wider parameter type accepts everything that it will be called with
takes_child_closure(function (Base $b): Base { return $b; });
takes_child_closure(function (object $o): Child { return new Child(); });
takes_child_callable(function (Base $b): void { var_dump($b); });
takes_child_callable(function (?Child $c): void { var_dump($c); });
takes_int_callable(function (int|string $x): void { var_dump($x); });
// A narrower parameter type is not safe, but was accepted before and still is
takes_base_callable(function (Child $c): void { var_dump($c); });
// An unrelated parameter type is not compatible
takes_child_closure(function (Other $o): Base { return new Base(); });
takes_child_callable(function (Other $o): void { var_dump($o); });
takes_int_callable(function (array $a): void { var_dump($a); });
// The return type is still covariant
takes_child_closure(function (Base $b): Other { return new Other(); });

// The template parameters of a class are compared: a Box<string> is not a Box<int>
/** @template T */
class Box {
    /** @var T */
    public $value;
    /** @param T $value */
    public function __construct($value) { $this->value = $value; }
}
/** @param callable(Box<int>):void $callable */
function takes_int_box_callable(callable $callable): void { var_dump($callable); }
takes_int_box_callable(/** @param Box<string> $box */ function (Box $box): void { var_dump($box); });
takes_int_box_callable(/** @param Box<int> $box */ function (Box $box): void { var_dump($box); });
takes_int_box_callable(function (Box $box): void { var_dump($box); });
