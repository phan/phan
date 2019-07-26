<?php

class TestArrayPush {

	public $x = [];

	function add($something) {
		array_push($this->x, $something);
	}
}
class TestArrayUnshift {

	public $x = [];
	public $y = [];

	function add($something, $else) {
		array_unshift($this->x, $something, $else);
		array_unshift($this->y, ...$something);
		array_unshift('x', ...$something);
	}
}

$test = new TestArrayPush();

$test->add('hund');
$test->add('katze');

echo $test->x[0];
$test2 = new TestArrayUnshift();
$test2->add(new stdClass(), new ast\Node());
echo $test2->x[0];
