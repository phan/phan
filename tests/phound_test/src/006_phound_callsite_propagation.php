<?php

// =====================================================================
// Test callsite propagation up the class/interface/trait hierarchy.
// When a method is called on a child class, the callsite should also
// appear for parent classes, interfaces, and traits that declare the
// member (verified via signatures).
// =====================================================================

namespace CallsitePropagation;

// --- Hierarchy setup ---

interface Greetable {
    public function greet(): string;
}

interface Describable extends Greetable {
    public function describe(): string;
}

trait GreetTrait {
    public function greet(): string {
        return "hello";
    }
}

trait InnerTrait {
    public function innerMethod(): void {}
}

trait OuterTrait {
    use InnerTrait;
    public function outerMethod(): void {}
}

class Base {
    public int $baseProp = 0;
    public const BASE_CONST = 1;
    public function baseMethod(): void {}
    private function privateMethod(): void {}
    private int $privateProp = 0;
}

class Middle extends Base implements Greetable {
    use GreetTrait;
    public function middleOnly(): void {}
    private function privateMethod(): void {} // same name as Base::privateMethod, but independent
    private int $privateProp = 0; // same name as Base::privateProp, but independent

    public function callPrivates(): void {
        $this->privateMethod(); // \Middle::privateMethod — should NOT propagate to \Base::privateMethod
        echo $this->privateProp; // \Middle::privateProp — should NOT propagate to \Base::privateProp
    }
}

class Leaf extends Middle implements Describable {
    use OuterTrait;
    public function describe(): string {
        return "leaf";
    }
}

// --- Callsites that should propagate ---

// 1) Basic class inheritance: call on Leaf -> should propagate to Middle, Base
$leaf = new Leaf;
$leaf->baseMethod();   // \Leaf::baseMethod -> \Middle::baseMethod, \Base::baseMethod

// 2) Property propagation: access on Leaf -> should propagate to Base
echo $leaf->baseProp;  // \Leaf::baseProp -> \Middle::baseProp, \Base::baseProp

// 3) Constant propagation: access on Leaf -> should propagate to Base
echo Leaf::BASE_CONST; // \Leaf::BASE_CONST -> \Middle::BASE_CONST, \Base::BASE_CONST

// 4) Interface method propagation: call on Leaf -> Greetable, Describable
$leaf->greet();        // \Leaf::greet -> \Middle::greet, \Greetable::greet, \Describable::greet, \GreetTrait::greet

// 5) Trait method propagation: call on Leaf -> OuterTrait, InnerTrait
$leaf->outerMethod();  // \Leaf::outerMethod -> \OuterTrait::outerMethod
$leaf->innerMethod();  // \Leaf::innerMethod -> \OuterTrait::innerMethod (via flattened class_traits), \InnerTrait::innerMethod

// 6) Method only on child, not on parent -> NO propagation for middleOnly to Base
$middle = new Middle;
$middle->middleOnly(); // \Middle::middleOnly stays, NOT propagated to \Base

// 7) Method on Middle (not on Base) -> propagation to interface/trait but not Base
$middle->greet();      // \Middle::greet -> \Greetable::greet, \GreetTrait::greet (NOT \Base::greet)

// 8) Private members -> NO propagation (private members are not inherited)
// Calls to private members happen inside Middle::callPrivates()
$middle->callPrivates(); // triggers \Middle::privateMethod and \Middle::privateProp internally

// 9) Standalone function -> NO propagation at all
function standaloneFunc(): void {}
standaloneFunc();

// 10) Interface->interface propagation: call on Describable -> should propagate to Greetable
function testDescribable(Describable $d): void {
    $d->greet();   // \Describable::greet -> \Greetable::greet (via interface_relationships)
    $d->describe(); // \Describable::describe stays (Greetable doesn't have describe)
}

// 11) ::class pseudo-constant — not a propagation concern.
//     PHP represents Foo::class as AST_CLASS_NAME (not AST_CLASS_CONST),
//     so visitClassConst is never triggered and no callsite is recorded.
//     This means ::class can never be propagated, even without an explicit filter.
$leafClass = Leaf::class;
