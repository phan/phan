<?php
// Should warn.
// To make it easier to parse union types, brackets such as <> must be escaped as \x3c and \x3e in Phan's current type representation.
var_export(version_compare('1.2', '1.3', 'EQ'));
var_export(version_compare('1.2', '1.3', null));
