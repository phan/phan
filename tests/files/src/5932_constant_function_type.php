<?php

define('DEFINITE_STRING', random_bytes(3));

$definiteStringVarFromConstantPlain = DEFINITE_STRING;
'@phan-debug-var $definiteStringVarFromConstantPlain';

$definiteStringVarFromConstantFn = constant('DEFINITE_STRING');
'@phan-debug-var $definiteStringVarFromConstantFn';
