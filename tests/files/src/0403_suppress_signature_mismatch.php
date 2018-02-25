<?php

class A403 {
    /** @return string */
    public function run() { return ''; }
}

class B403 extends A403 {
    /**
     * @suppress PhanParamSignatureMismatch
     */
    public function run(): void { }
}

class C403 extends A403 {
    public function run() : void { }
}

class Base403 {
    public function run() { return ''; }
}

class Subclass403 extends Base403 {
    public function run() : void { echo "in subclass\n"; }
}
