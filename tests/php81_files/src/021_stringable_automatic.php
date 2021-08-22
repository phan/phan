<?php

class DemoC {
    public function __toString() {
        return 'demo';
    }
}

function demoF( Stringable $obj ) {
    echo $obj->__toString();
}

demoF( new DemoC() );
