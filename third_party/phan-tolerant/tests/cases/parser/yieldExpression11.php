<?php

// should not fail.
// This is parsed as the binary bitwise and operator (yield) & ($a);
function gen() {
    yield &$a;
}
