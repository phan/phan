<?php

$x = new ArrayObject();
$methodName = [];
var_export(ArrayObject::${$methodName});  // also for instance and static property access
var_export($x->$methodName());  // Phan should warn about expecting string for method name (IMPLEMENTED)
ArrayObject::$methodName();
var_export($x->$methodName);  // also for instance and static property access
