<?php

class C0 {
    public function f($p) {}
}

class C1 extends C0 {
    public static function f($p) {}
}

class C2 extends C0 {
    private function f($p) {}
}

class C3 extends C0 {
    protected function f($p) {}
}
