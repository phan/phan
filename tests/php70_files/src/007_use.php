<?php

use A\void;
use NS\iterable;
use My\Framework\object;

function example(object $o, iterable $i) : void {
    var_export($o);
    var_export($i);
}
example(null, null);
