<?php
namespace {
use function ast\parse_ode as pc;
var_export(isstring("a string"));
var_export(parsecode("a string"));  // Currently doesn't bother searching namespaces not in the path
var_export(ast\parsecode("a string", 50));  // should suggest ast\parse_code
var_export(\ast\parsecode("a string", 50));  // should suggest ast\parse_code
var_export(namespace\ast\parsecode("a string", 50));  // should suggest ast\parse_code
var_export(pc('invalid', 50));
}
namespace ast {
var_export(isstring("a string"));
var_export(parsecode("other", 50));  // should suggest ast\parse_code
var_export(namespace\parsecode("other", 50));  // should suggest ast\parse_code
var_export(\parsecode("other", 50));  // currently doesn't suggest other namespaces
var_export(\parse_code("other", 50));  // suggests other namespaces for exact matches
}
