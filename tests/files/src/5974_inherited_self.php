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
