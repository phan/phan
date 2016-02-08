<?php
class C1 extends AbstractC1 {
    public function m1() {}
}

abstract class AbstractC1 {
    abstract protected function m1();
}

class C2 {
    public function pub() {}
    protected function prot() {}
    private function priv() {}
}

class C3 extends C2 {
    public function pub() {}
    public function prot() {}
    public function priv() {}
}

class C4 extends C2 {
    protected function pub() {}
    protected function prot() {}
    protected function priv() {}
}

class C5 extends C2 {
    private function pub() {}
    private function prot() {}
    private function priv() {}
}
