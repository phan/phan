<?php

class HasGetter780 {
    private $propName = 'default';

    public function getPropName() : string {
        return $this->propName;
    }

    public function setPropName(string $value) : void {
        $this->propName = $value;;
    }
    public function badMethod(string $unused_value) : void {
        'x';
    }
    public function badMethod2(string $unused_value = null) : string {
        0;
    }
}
function test() {
    $g = new HasGetter780();
    echo $g->propName;
    $g->propName = 'newValue';
}
