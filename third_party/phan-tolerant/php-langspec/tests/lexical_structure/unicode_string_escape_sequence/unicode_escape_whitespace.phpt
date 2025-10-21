--TEST--
PHP Spec test generated from ./lexical_structure/unicode_string_escape_sequence/unicode_escape_whitespace.php
--FILE--
<?php

var_dump("\u{1F602 }");
--EXPECTF--
Parse error: Invalid UTF-8 codepoint escape sequence in %s on line %d
