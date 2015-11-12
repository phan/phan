<?php
class A {
    /**
     * @var string $text
     * @var int $num
     * @var array $foo
     */
	protected $text = 1, $num = 'abc', $foo = null;

	function test() {
		echo $this->str;
		$this->text = [];
	}
}

$a = new A;
$a->test();
$a->foo = "test";
$a->bar = 1;
