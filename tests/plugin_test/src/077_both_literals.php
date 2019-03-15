<?php
$x = rand(0,10) ?: null;
var_export(2 <=> 3);
var_export($x ?? null == 9);
var_export($x ?? null === 9);
var_export($x ?? 1 !== 9);
var_export($x ?? 1 != 9);
var_export($x ?? 1 != 9);
var_export(2 < 3);
var_export(2 <= 2);  // If PhanPluginDuplicateExpressionBinaryOp is emitted, don't bother emitting PhanPluginBothLiteralsBinaryOp
var_export(2 <= '700');  // If PhanPluginDuplicateExpressionBinaryOp is emitted, don't bother emitting PhanPluginBothLiteralsBinaryOp
var_export(3 > 2);
var_export(3 >= 4);
var_export(true && false);
var_export(true and 'aString');
var_export(true || false);
var_export(true xor false);
