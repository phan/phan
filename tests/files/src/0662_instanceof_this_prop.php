<?php

namespace N662;

class C {
}

class D extends C {
    function asdf() {}
}

class E {
    /** @var C */
    private $c;

    /** @var ?D */
    private $d;

    public function foo() {
        if ($this->c instanceof D) {
            $this->c->asdf(); // should not warn
        } else {
            $this->c->asdf(); // should warn
        }

        $this->c->asdf(); // should warn
        if ($this->d instanceof D) {
            $this->d->asdf(); // should not warn
        } else {
            $this->d->asdf(); // should warn, d is null
        }
    }
}
