<?php

use ast as a;

// Should not warn
var_export(ast\parse_code('', 50));
var_export(ast\AST_UNPACK);
var_export(new ast\Node());
var_export(namespace\ast\parse_code('', 50));
var_export(namespace\ast\AST_UNPACK);
var_export(new namespace\ast\Node());
var_export(namespace\is_string('a string'));
var_export(namespace\PHP_VERSION);
// Also should not warn
var_export(\ast\parse_code('', 50));
var_export(\ast\AST_UNPACK);
var_export(a\parse_code('', 50));
var_export(a\AST_UNPACK);
var_export(new a\Node());
// Should warn
var_export(new namespace\a\Missing());
var_export(namespace\ast\MISSING_CONST);
var_export(namespace\ast\missing_function());
