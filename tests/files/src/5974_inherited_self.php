<?php
namespace NS5974;

// `self` in the signature of a method is the class that declares the method, or that uses the trait declaring it.
// It is not the subclass that inherits the method: that is `static`.
trait SelfTrait {
    /** @return self */
    public function me() { return $this; }
    /** @param self $other */
    public function take($other): void { var_dump($other); }
    /** @param list<self> $others */
    public function takeAll(array $others): void { var_dump($others); }
    /** @return array{value:self,same:static} */
    public function shape(): array { return ['value' => $this, 'same' => $this]; }
    /** @return \Closure(self):static */
    public function pair(): \Closure { return fn($x) => $this; }
}
class Base { use SelfTrait; }
class Child extends Base {}
class Other {}

class PlainBase {
    /** @param self $other */
    public function equals($other): bool { return $other === $this; }
    /** @param static $other */
    public function sameClass($other): bool { return $other === $this; }
    /** @return array<string,self> */
    public function all(): array { return ['x' => $this]; }
}
class PlainChild extends PlainBase {}

function inherited(Base $base, Child $child, Other $other, PlainBase $plain_base, PlainChild $plain_child): void {
    // An instance of the class that `self` is can be passed to the inherited method
    $child->take($base);
    $child->takeAll([$base, $child]);
    $plain_child->equals($plain_base);
    $closure = $child->pair();
    $closure($base);
    // Other classes are still rejected
    $child->take($other);
    $child->takeAll([$other]);
    $plain_child->equals($other);
    $closure($other);
    // `static` is the class of the object
    $plain_child->sameClass($plain_child);
    $plain_child->sameClass($plain_base);
    $me = $child->me();
    $shape = $child->shape();
    $all = $plain_child->all();
    '@phan-debug-var $me, $shape, $closure, $all';
}

// A trait that uses another trait: `self` is the class that uses the outer trait, not the outer trait
trait InnerTrait {
    /** @return self */
    public function inner() { return $this; }
    /** @return array{value:self} */
    public function innerShape(): array { return ['value' => $this]; }
    // An optional field stays optional
    /** @return array{value?:self,same?:static} */
    public function optional(): array { return []; }
    /**
     * The template of the key is kept
     * @template TKey
     * @param TKey $key
     * @return array<TKey,self>
     */
    public function keyed($key): array { return [$key => $this]; }
}
trait OuterTrait { use InnerTrait; }
class Composed { use OuterTrait; }
class ComposedChild extends Composed {}

function composed(Composed $composed, ComposedChild $child): void {
    $inner = $composed->inner();
    $inner_shape = $composed->innerShape();
    $optional = $child->optional();
    $keyed = $child->keyed('a');
    '@phan-debug-var $inner, $inner_shape, $optional, $keyed';
}

// A generic trait: `self` is the class that uses the trait, also when the trait is specialized with (at)use
/** @template T */
trait GenericTrait {
    /** @return array{value:self,data:T} */
    public function generic(): array { throw new \Exception(); }
    /** @return array{value:self,same:static,data:T} */
    protected function genericProtected(): array { throw new \Exception(); }
}
/** @use GenericTrait<int> */
class GenericUser { use GenericTrait; }
class GenericUserChild extends GenericUser {
    public function check(): void {
        $public = $this->generic();
        $protected = $this->genericProtected();
        '@phan-debug-var $public, $protected';
    }
}
