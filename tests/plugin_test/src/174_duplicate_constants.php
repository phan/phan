<?php
// Tests of DuplicateConstantPlugin
define('S174', 'value');
define('T174', 'value');
const U174 = 'value', V174 = [];
define('S174', 'value');
define('T174', 'other value');
const V174 = [123];
