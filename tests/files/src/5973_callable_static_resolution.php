<?php
namespace NS5973;

class Other {}

class Emitter {
    /** @param callable(static):void $listener */
    public function listen(callable $listener): void {
        // $this is an instance of static
        $listener($this);
    }
    /** @return \Closure(static):static */
    public function identity(): \Closure {
        return static function ($x) { return $x; };
    }
}
final class FinalEmitter extends Emitter {}

function listeners(Emitter $emitter, FinalEmitter $final): void {
    // `static` in the type of the callable is the class of the object that the method is called on
    $final->listen(function (FinalEmitter $e): void { var_dump($e); });
    $emitter->listen(function (Emitter $e): void { var_dump($e); });
    $final->listen(function (Other $o): void { var_dump($o); });
    $emitter->listen(function (Other $o): void { var_dump($o); });
    $f = $final->identity();
    '@phan-debug-var $f';
}

// A closure that returns `static` can be returned for a declared return type with `static`,
// and the result keeps the type of the object, including an intersection type
interface Named { public function name(): string; }
class Factory {
    /** @return \Closure():static */
    public function creator(): \Closure {
        return fn() => $this;
    }
    /** @return \Closure():static */
    public function wrong(): \Closure {
        return fn() => new Other();
    }
}
/** @param Factory&Named $named */
function creators($named, Factory $factory): void {
    $create = $named->creator();
    echo $create()->name();
    $other = $factory->creator();
    '@phan-debug-var $create, $other';
}

// `self` and `static` in the same callable type: each way of resolving them gives one type, without duplicates
trait PairTrait {
    /** @return \Closure(self):static */
    public function pair(): \Closure {
        return fn($x) => $this;
    }
}
class PairBase { use PairTrait; }
class PairChild extends PairBase {}
function pairs(PairBase $base, PairChild $child): void {
    $f = $base->pair();
    $g = $child->pair();
    '@phan-debug-var $f, $g';
}
