<?php

class A541 {
    public $myPublicVar;
    private $myPrivateVar;
    public function test() {
        unset($this->myPublicVar);
        unset($this->myPrivateVar);
        unset($this->myDynamicVar);
        $n = [];
        unset($n->prop);
    }
}
call_user_func(function () {
    $a = new A541();
    unset($a->myPrivateVar);
    unset($a->myPublicVar);
});
