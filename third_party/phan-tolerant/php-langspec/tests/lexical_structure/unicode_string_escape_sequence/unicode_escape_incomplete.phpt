--TEST--
PHP Spec test generated from ./lexical_structure/unicode_string_escape_sequence/unicode_escape_incomplete.php
--FILE--
<?php

var_dump("\u{blah");
--EXPECTF--
Parse error: Invalid UTF-8 codepoint escape sequence in %s on line %d
