<?php

use A\void;
use NS\iterable;
use My\Framework\object;
use stdClass as NotAReservedKeyword;
function example007(object $o, iterable $i) : void {
    var_export($o);
    var_export($i);
}
example007(null, null);
