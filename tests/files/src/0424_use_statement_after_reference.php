<?php
namespace foo;
var_export(parse_file(__FILE__, 50));  // This is before the use statement, so it refers to foo\parse_file

use function ast\parse_file;

var_export(parse_file(__FILE__, 50));  // This is before the use statement, so it refers to foo\parse_file

namespace otherNS;
var_export(parse_file(__FILE__, 50));  // This is a different namespace, and doesn't see the use statement.
