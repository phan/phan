<?php

abstract class AbstractClass {
    use TraitClass {
        foo as foo2;
        foo as protected foo3;
        foo as public foo4;
        publicFn as protected protectedAlias;
    }
}

trait TraitClass {
	protected $prop = 4;

    protected function foo() {
        echo "bar\n";
	}
    public function publicFn() {
        echo "bar\n";
	}
}

class ExtendingClass extends AbstractClass {
	public function foo() {
		parent::foo();
	}

    // should not warn.
	public function foo2() {
		parent::foo2();
        parent::foo3();
        parent::foo4();
        parent::protectedAlias();
	}

	public function other() {
        printf("prop=%s\n", $this->prop);
        printf("prop=%s\n", $this->missingProp);  // should warn
	}
}

$traitClass = new ExtendingClass();
$traitClass->foo();  // should not warn
$traitClass->foo2();  // should not warn
$traitClass->foo4();  // should not warn

$traitClass->protectedAlias();  // should warn
$traitClass->foo3(); // should warn
$traitClass->publicFn();  // should not warn
echo $traitClass->prop;
