<?php

class C82 {
    public final function f($a) {
        $this->g(2);
    }
    protected final function g($a) {
        $this->h(2);
    }
    private final function h($a) {
        echo "in function h\n";
    }
}
(new C82)->f('value');
