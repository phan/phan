<?php

class HasGetter780 {
    private $propName = 'default';

    public function getPropName() : string {
        return $this->propName;
    }

    public function setPropName(string $value) : void {
        $this->propName = $value;;
    }
}
function test() {
    $g = new HasGetter780();
    echo $g->propName;
    $g->propName = 'newValue';
}
