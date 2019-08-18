<?php

class ClassWithEmptyMethods756 {
    public function one() {} // no warning, overridden

    public function two() {}

    protected function three() {}

    private function four() {}

    /** @deprecated */
    public function five() {} // no warning, deprecated
}

class OverridesClassWithEmptyMethods756 extends ClassWithEmptyMethods756 {
    public function one() {} // no warning, overrides
}

function emptyFunction756() {}

/** @deprecated */
function deprecatedEmptyFunction756() {} // no warning, deprecated

array_map(function() {}, []);