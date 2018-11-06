<?php
declare(strict_types=1);

namespace ns565;

define(__NAMESPACE__.'\\cons', 0);
define('ns565\\cons2', 0);
const CONS3 = 'ns565\\cons3';
// Constants defined in other files won't work as well.
// Make sure files with constants are parsed first.
define(MISSINGCONST565 . '\\cons', 0);
define(CONS3, 0);
define(2, 0);
define(2.3, 0);
define(null, 0);
define('', 0);
define('\\\\', 0);
define(Missing565::class, 0);

var_dump(cons);
var_dump(cons2);
var_dump(cons3);
var_dump(\cons);  // should warn
var_dump(\ns565\cons);
var_dump(ns565\cons);  // should warn
