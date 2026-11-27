<?php

// More about a property of $this after a call of a method of $this: a closure of the method, a private property of a parent class,
// destructuring, a method that calls an abstract method, a property with a dynamic name, parent:: in a trait, and self::.

namespace NS6006;
class Conn { public function q(): void {} }
trait Connects {
    public function viaTrait(): void { parent::connect(); }
}
class Root {
    /** @var ?Conn */
    protected $conn = null;
    private ?string $value = 'x';
    protected function connect(): void { $this->conn = new Conn(); }
    protected function resetValue(): void { $this->value = null; }
}
abstract class Mid extends Root {
    use Connects;
    private ?string $value = null;
    /** @var ?Conn */
    protected $b = null;
    /** @var ?Conn */
    protected $c = null;
    abstract protected function load(): void;
    private function wrapper(): void { $this->load(); }
    private function destructure(): void { [$this->b] = [null]; }
    private function dyn(string $n): void { $this->b = new Conn(); $this->$n = null; }
    private function reset(): void { $this->b = null; }
    final protected function selfCall(): void { self::connect(); }
    protected function connect(): void {}

    public function callable(): Conn {
        if ($this->b === null) { throw new \Exception(); }
        $f = $this->reset(...);
        return $this->b; // kept: reset() is not called
    }
    public function privateOfParent(): string {
        if ($this->value === null) { return ''; }
        parent::resetValue();
        return $this->value; // kept: the parent assigns its own private $value
    }
    public function afterDestructure(): Conn {
        if ($this->b === null) { throw new \Exception(); }
        $this->destructure();
        return $this->b; // may be null
    }
    public function afterWrapper(): Conn {
        if ($this->c === null) { $this->wrapper(); return $this->c; } // not known to be null
        return $this->c;
    }
    public function afterDyn(): Conn {
        if ($this->b === null) { throw new \Exception(); }
        $this->dyn('b');
        return $this->b; // may be null
    }
    public function afterTraitParent(): void {
        if ($this->conn) { return; }
        $this->viaTrait();
        $this->conn->q(); // not null: Root::connect() assigns it
    }
    public function afterSelf(): void {
        if ($this->conn) { return; }
        $this->selfCall();
        $this->conn->q(); // still null: self::connect() is Mid::connect(), which assigns nothing
    }
}

// A generator method runs nothing when it is called. A private property of a parent class is another property than
// the protected property of the class with that name. `$this->helper()` in a parent class calls the private helper() of that class.
class GenBase {
    private ?string $slot = null;
    protected ?Conn $kept = null;
    public function __construct(?Conn $kept, ?string $slot) { $this->kept = $kept; $this->slot = $slot; }
    protected function resetSlot(): void { $this->slot = null; }
    final protected function callHelper(): void { $this->helper(); }
    private function helper(): void {}
}
final class Gen extends GenBase {
    protected ?string $slot = 'x';
    private function resetLater(): \Generator { $this->kept = null; yield 1; }
    private function helper(): void { $this->kept = null; }
    public function afterGenerator(): void {
        if ($this->kept === null) { throw new \Exception(); }
        $generator = $this->resetLater();
        $kept = $this->kept;
        '@phan-debug-var $generator, $kept';
    }
    public function shadowed(?string $slot): void {
        $this->slot = $slot;
        if ($this->slot === null) { return; }
        parent::resetSlot();
        $kept = $this->slot;
        '@phan-debug-var $kept';
    }
    public function privateHelperOfParent(): void {
        if ($this->kept === null) { throw new \Exception(); }
        $this->callHelper();
        $kept = $this->kept;
        '@phan-debug-var $kept';
    }
    public function ownHelper(): void {
        if ($this->kept === null) { throw new \Exception(); }
        $this->helper();
        $widened = $this->kept;
        '@phan-debug-var $widened';
    }
}
