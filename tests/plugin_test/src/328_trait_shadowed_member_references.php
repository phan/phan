<?php
// A class using a trait can declare a property or method with the same name as the trait's (#5630):
// uses of the trait's property or method from within the trait are also references to the class's declaration.

/**
 * @property-read string $magicProp
 * @property-read string $magicPropUnused
 * @method string magicMethod()
 */
trait Shadowed5630 {
    private readonly string $promotedProp;
    private string $normalProp;
    private string $unusedProp;
    private string $readInTraitOnly = 'default';
    private int $writtenInTraitOnly;
    private static int $staticProp = 0;
    abstract private function abstractMethod(): string;
    abstract private function abstractUnusedMethod(): string;
    private function concreteMethod(): string {
        return 'trait';
    }
    private function unusedTraitMethod(): string {
        return 'trait';
    }

    public function traitMethod(): string {
        $this->writtenInTraitOnly = 1;
        self::$staticProp++;
        return $this->promotedProp . $this->normalProp . $this->magicProp . $this->readInTraitOnly .
            $this->abstractMethod() . $this->magicMethod() . $this->concreteMethod() . self::$staticProp;
    }
}

class Uses5630 {
    use Shadowed5630;

    private string $normalProp;
    private readonly string $magicProp;
    private string $unusedProp;
    private string $readInTraitOnly = 'default';  // still read-only
    private int $writtenInTraitOnly;  // still write-only
    private static int $staticProp = 0;

    public function __construct(
        private readonly string $promotedProp,
        string $normalProp,
    ) {
        $this->normalProp = $normalProp;
        $this->magicProp = '3';
    }

    private function abstractMethod(): string {
        return 'class';
    }

    private function abstractUnusedMethod(): string {
        return 'class';
    }

    private function magicMethod(): string {
        return 'class';
    }

    // Replaces the trait's method: the trait's call is a reference to this one, but a call of this one is not a reference to the trait's.
    private function concreteMethod(): string {
        return 'class';
    }

    private function unusedTraitMethod(): string {
        return 'class';
    }
}
echo (new Uses5630('1', '2'))->traitMethod();

// The same for a trait used through another trait
trait Inner5630 {
    private string $innerProp;
    public function innerMethod(): string {
        return $this->innerProp;
    }
}
trait Outer5630 {
    use Inner5630;
}
class UsesOuter5630 {
    use Outer5630;
    private string $innerProp;
    public function __construct() {
        $this->innerProp = 'x';
    }
}
echo (new UsesOuter5630())->innerMethod();

// Several classes using the same trait: a use of the trait's property or method by one class is not a use of
// another class's declaration, and the result does not depend on the order of the declarations.
trait Shared5630 {
    private int $counter = 0;
    private function helper(): int {
        return 1;
    }
    public function get(): int {
        return $this->counter;
    }
}
class NoRedeclare5630 {
    use Shared5630;
    public function write(): void {
        $this->counter = 1;
    }
    public function callHelper(): int {
        return $this->helper();
    }
}
class RedeclareAndWrite5630 {
    use Shared5630;
    private int $counter = 0;
    private function helper(): int {  // never called: the trait does not call helper()
        return 2;
    }
    public function write(): void {
        $this->counter = 1;
    }
}
class RedeclareOnly5630 {
    use Shared5630;
    private int $counter = 0;  // only read, by the trait
    private function helper(): int {
        return 3;
    }
}
class RedeclareOnlyAgain5630 {
    use Shared5630;
    private int $counter = 0;  // only read, by the trait
    private function helper(): int {
        return 4;
    }
}
$no = new NoRedeclare5630();
$no->write();
echo $no->callHelper() + $no->get();
$rw = new RedeclareAndWrite5630();
$rw->write();
echo $rw->get() + (new RedeclareOnly5630())->get() + (new RedeclareOnlyAgain5630())->get();

// A subclass of the class writes the property that the class declares with the same name as the trait's,
// whether the subclass is declared before or after the class.
trait Counted5630 {
    protected int $count = 0;
    public function getCount(): int {
        return $this->count;
    }
}
class ChildFirst5630 extends ParentLast5630 {
    public function increment(): void {
        $this->count++;
    }
}
class ParentLast5630 {
    use Counted5630;
    protected int $count = 0;
}
class ParentFirst5630 {
    use Counted5630;
    protected int $count = 0;
}
class ChildLast5630 extends ParentFirst5630 {
    public function increment(): void {
        $this->count++;
    }
}
$child = new ChildFirst5630();
$child->increment();
$other_child = new ChildLast5630();
$other_child->increment();
echo $child->getCount() + $other_child->getCount();

// A static property of the class is not the instance property that a trait's @property describes.
/** @property-read string $value */
trait MagicValue5630 {
    public function __get(string $name): string {
        return $name;
    }
    public function show(): string {
        return $this->value;
    }
}
class StaticValue5630 {
    use MagicValue5630;
    private static string $value = 'a';  // write-only: the trait reads the magic instance property
    public static function set(): void {
        self::$value = 'b';
    }
}
echo (new StaticValue5630())->show();
StaticValue5630::set();

// A protected or private method of a trait used through another trait: the result does not depend on the order of the declarations
// (the outer trait declaring a method of its own changes the order in which the classes are processed).
trait InnerUsed5630 {
    protected function innerHelper(): string {
        return 'i';
    }
    private function innerPrivate(): string {
        return 'p';
    }
    protected static function innerStatic(): string {
        return 's';
    }
}
trait OuterUsed5630 {
    use InnerUsed5630;
    public function outerOwn(): string {
        return 'o';
    }
}
class UsesNested5630 {
    use OuterUsed5630;
    public function run(): string {
        return $this->innerHelper() . $this->innerPrivate() . self::innerStatic() . $this->outerOwn();
    }
}
echo (new UsesNested5630())->run();
