<?php

const EQUALS_NULL = null;
function test_nullable782(int $x = EQUALS_NULL)  {
    var_export($x);
}
test_nullable782(2);
test_nullable782(null);
test_nullable782(false);
