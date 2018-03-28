<?php
$x = [];
$x[] = $this->undefVar;  // Expected PhanUndeclaredVariable
echo $x[0];
echo $x['key'];
