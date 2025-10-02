<?php
// create_function is deprecated in Reflection since php 7.2+
// Phan should emit PhanDeprecatedFunctionInternal
$f = create_function('', 'return 2;');
var_export($f());
