<?php

class DoTest {
	/**
	 * @var array<string,stdClass|null>
	 */
	private $prop = [];

	function getObj(): ?stdClass {
		return rand() ? (object)[] : null;
	}

	public function test( string $key ) {
		$this->prop[$key] = $this->getObj();
	}
}
