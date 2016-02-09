<?php
echo $foo->$bar['baz'];
Foo::$bar['baz']();
$foo->$bar['baz']();
strlen($foo->$bar['baz']);

class C {
    public $bb = 2;
}

class T {
    public $b = null;
    function fn($a) {
      $this->b = new C;
      echo $this->b->$a[1];
    }
}
$t = new T;
$t->fn(['aa','bb','cc']);

// tests that should pass without warning below
class Test {
    public static $vals = array('a' => 'A', 'b' => 'B');
    public static function get($letter) {
        return self::$vals[$letter];
    }
	public function fn($letter) {
        return $this->vals[$letter];
    }
}
