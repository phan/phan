<?php
require_once [];  // invalid
include_once [];  // invalid
include [];  // invalid
require [];  // invalid
require (new stdClass());  // invalid
eval([]);  // invalid
require_once false;  // invalid
require_once 2.5;  // invalid
require_once __FILE__;  // valid but questionable
require_once __DIR__ . '/0529_require_once.php';  // valid but questionable
require_once '';
require_once '/';
require_once __FILE__ . '.missing';  // valid but insane
require_once __DIR__ . '/missing';  // valid but insane
require_once __DIR__;  // valid but questionable
require_once 'file:///notarealpath';  // Phan does not understand this, and treats it like a regular path
