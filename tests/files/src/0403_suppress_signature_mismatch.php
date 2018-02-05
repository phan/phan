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
