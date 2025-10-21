--TEST--
PHP Spec test generated from ./lexical_structure/unicode_string_escape_sequence/unicode_escape_legacy.php
--FILE--
<?php

// These are ignored to avoid breaking JSON string literals
var_dump("\u");
var_dump("\u202e");
var_dump("\ufoobar");
--EXPECTF--
string(2) "\u"
string(6) "\u202e"
string(8) "\ufoobar"
