<?php

namespace NS7;
// AvoidableGetterPlugin is tested here because this folder has few pre-existing tests.
class HasGetter {
    /** @var string */
    protected $prop;

    public function __construct(string $prop) {
        $this->prop = $prop;
    }

    public function getProp() : string {
        return $this->prop;
    }

    public function main() : string {
        return 'prefix ' . $this->getProp();
    }
}

class Descendant extends HasGetter {
    public function main() : string {
        return 'other prefix ' . $this->getProp();
    }
}
$f = new Descendant('f');
var_export($f->main());
$f = new HasGetter('f');
var_export($f->main());
