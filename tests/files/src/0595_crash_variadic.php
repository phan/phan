<?php

function a595(...$args) {var_export($args);}
a595('a', '', 'c');
function b595(string ...$args) {var_export($args);}
b595('a', 0, 'c');
function c595(int ...$args) {var_export($args);}
c595(0, 1, '0', '');
