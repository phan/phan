<?php
interface MyInterface506 {
    public function doSomething() {}
}
try {

} catch (MyInterface506 $e) {
    $e->doSomething('extra');
    throw new \RuntimeException("bla", 0, $e);
}
