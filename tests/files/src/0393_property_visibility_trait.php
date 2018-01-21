<?php

abstract class AbstractClass393 {
    use TraitClass393 { }
}

trait TraitClass393 {
	protected $prop = 4;
	private $propPrivate = 4;
}

class ExtendingClass393 extends AbstractClass393 {
	public function other() {
        printf("prop=%s\n", $this->prop);
        printf("prop=%s\n", $this->propPrivate);  // should warn
        printf("prop=%s\n", $this->missingProp);  // should warn
	}
}

$traitClass = new ExtendingClass393();
$traitClass->other();  // should not warn
