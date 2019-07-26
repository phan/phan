<?php
namespace NS2371;

trait FooTrait {
    private $prop = 'private prop';
    public function test(ClassUsingTrait $o) {
        echo $o->prop;
        if ($this instanceof ClassUsingTrait) {
            // Same issue seen for class elements such as methods, etc.
            echo $this->prop;
            $this->privateMethod();
        }
        // TODO: Phan should **not** warn here for the property/method
        echo $o->otherPrivateProp;
        $o->otherPrivateMethod();
        echo $o->otherProtectedProp;
        $o->otherProtectedMethod();
    }

    public function testSubclass(SubClass $s) {
        echo $s->otherPrivateProp;
        $s->otherPrivateMethod();
        echo $s->inaccessiblePrivateProp;
        $s->inaccessiblePrivateMethod();
    }
    private function privateMethod() {
    }
}

class ClassUsingTrait {
    use FooTrait;
    private $otherPrivateProp = "private\n";
    private function otherPrivateMethod() {
        echo "In private method of class using trait\n";
    }
    protected $otherProtectedProp = "protected\n";
    private function otherProtectedMethod() {
        echo "In protected method of class using trait\n";
    }
}
class SubClass extends ClassUsingTrait {
    private $inaccessiblePrivateProp = "inaccessible\n";
    private function inaccessiblePrivateMethod() {
        echo "In private method of subclass\n";
    }
}
$c = new ClassUsingTrait();
echo $c->test($c);
echo $c->testSubclass(new SubClass());
