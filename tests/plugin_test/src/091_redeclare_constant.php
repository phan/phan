<?php
const true='x';
define('foo\false', 'value');
const NULL='nil';
// These should not affect Phan's inferences
var_export([true => 'x', true => 'y']);
