<?php

trait TraitA {
	abstract protected function setup();
}

trait TraitB {
	use TraitA;

	protected function setup() {}
}

class TestCase {
	use TraitB {
		setup as parentSetup;
	}

	protected function setup() {
		$this->parentSetup();
	}
}
