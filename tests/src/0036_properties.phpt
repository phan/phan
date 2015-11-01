--TEST--
Properties
--FILE--
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
--EXPECTF--
%s:8 TypeError property is declared to be string but was assigned int
%s:8 TypeError property is declared to be int but was assigned string
%s:12 TypeError property is declared to be string but was assigned array
%s:18 AccessError Cannot access protected property A::$foo
