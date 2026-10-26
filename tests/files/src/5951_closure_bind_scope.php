<?php
// https://github.com/phan/phan/issues/5260
// A closure literal passed directly to Closure::bind()/bindTo()/call() with a known scope
// is analyzed as if it had @phan-closure-scope ThatClass.

class Item5951 {
    private const DEFAULT_VALUE = 100;
    private $priv = 1;
    private static function privStatic(): int { return 2; }
    private function privMethod(): int { return 3; }
}

class Other5951 {
    private $other = 1;
}

// The example from #5260
$getDefaultValue = Closure::bind(
    static function () {
        return Item5951::DEFAULT_VALUE;
    },
    null,
    Item5951::class
);
var_dump($getDefaultValue());

$item = new Item5951();

// $this is the bound class; self:: and static:: resolve to it
$getPriv = Closure::bind(function (): int {
    return $this->priv + self::DEFAULT_VALUE + static::privStatic() + $this->privMethod();
}, $item, Item5951::class);
var_dump($getPriv());

// Fully qualified \Closure, string scope, arrow function
$a = \Closure::bind(fn() => Item5951::DEFAULT_VALUE, null, 'Item5951');
var_dump($a());

// Scope given as an object
$b = Closure::bind(function () { return $this->priv; }, $item, $item);
var_dump($b());
$c = Closure::bind(static function () { return Item5951::DEFAULT_VALUE; }, null, new Item5951());
var_dump($c());

// bindTo() and call() on a closure literal
$d = (function (): int { return $this->priv; })->bindTo($item, Item5951::class);
var_dump($d());
$e = (static function (): int { return Item5951::DEFAULT_VALUE; })->bindTo(null, 'Item5951');
var_dump($e());
var_dump((function (): int { return $this->priv; })->call($item));

// The body really is analyzed as Item5951: undeclared members are reported against it
$f = Closure::bind(function () { return $this->nope; }, $item, Item5951::class);
var_dump($f());

// No scope argument: PHP keeps the declaring scope, so this still warns
$g = Closure::bind(function () { return $this->priv; }, $item);
var_dump($g());

// A closure stored in a variable first is analyzed before the bind is seen: still warns
$h = function () { return Item5951::DEFAULT_VALUE; };
$h = Closure::bind($h, null, Item5951::class);
var_dump($h());

// An explicit @phan-closure-scope wins over the inferred one
$i = Closure::bind(
    /** @phan-closure-scope Other5951 */
    function () { return $this->other . $this->priv; },
    $item,
    Item5951::class
);
var_dump($i());

// Undeclared scope class: no crash, only the usual warning for the argument
$j = Closure::bind(static function () { return Item5951::DEFAULT_VALUE; }, null, NoSuchClass5951::class);
var_dump($j());
$k = Closure::bind(static function () { return Item5951::DEFAULT_VALUE; }, null, 'NoSuchClass5951');
var_dump($k());

// 'static' as the scope keeps the declaring scope
$l = Closure::bind(static function () { return Item5951::DEFAULT_VALUE; }, null, 'static');
var_dump($l());

// A non-static closure bound without an object: the scope applies, but there is no $this
$n = Closure::bind(function () { return Item5951::DEFAULT_VALUE + static::privStatic(); }, null, Item5951::class);
var_dump($n());
$o = Closure::bind(function () { return $this->priv; }, null, Item5951::class);
var_dump($o());

// The bound object is not an instance of the scope class: don't guess what $this is
$p = Closure::bind(function () { return $this->priv; }, new Other5951(), Item5951::class);
var_dump($p());

// An instance of a subclass bound to the parent scope is fine
class Child5951 extends Item5951 {}
$q = Closure::bind(function () { return $this->priv; }, new Child5951(), Item5951::class);
var_dump($q());

// A nullable or union-typed scope object is not a single known class
function scope5951(?Item5951 $x, Item5951|Other5951 $y): array {
    return [
        Closure::bind(static function () { return Item5951::DEFAULT_VALUE; }, null, $x),
        Closure::bind(static function () { return Item5951::DEFAULT_VALUE; }, null, $y),
    ];
}
var_dump(scope5951($item, $item));

// A static call to bind() on something that is not \Closure is not a binding
class NotClosure5951 {
    public static function bind(Closure $c, $t, $s): Closure { return $c; }
}
$m = NotClosure5951::bind(static function () { return Item5951::DEFAULT_VALUE; }, null, Item5951::class);
var_dump($m());
