<?php

class C {
    private function priv() {}
    protected function prot() {}
    public function pub() {}
}

class D extends C {
    function f() {
        $this->prot();
        $this->priv();
        $this->pub();
    }
}

$c = new C;
$c->prot();
$c->priv();
$c->pub();

$d = new D;
$d->prot();
$d->priv();
$d->pub();
