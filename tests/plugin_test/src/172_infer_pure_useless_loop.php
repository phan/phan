<?php

namespace NS172;

class SomeClass {
    /** @var bool */
    public $x = false;
    public function getX(): bool {
        return $this->x;
    }
}
function test_loop_method(SomeClass $x) {
  for ($i = 0; $i < 10; $i++) {
    if ($x->getX()) { break; }
  }
}

function test_while_method(SomeClass $x) {
  while (true) {
    if ($x->getX()) { break; }
  }
}
function test_do_while_method(SomeClass $x) {
  do {
    if ($x->getX()) { break; }
  } while (rand(0,1));
}

/**
 * @param SomeClass[] $a
 */
function test_foreach_method(array $a) {
  foreach ($a as $x) {
    if ($x->getX()) {
      break;
    }
  }
}
test_loop_method(new SomeClass());
$s = new SomeClass();
$s->x = true;
test_while_method($s);
test_do_while_method($s);
test_foreach_method([$s]);
