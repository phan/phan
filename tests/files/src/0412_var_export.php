<?php
function expect_int412(int $value) {}
expect_int412(var_export(['key']));
expect_int412(var_export(['key'], false));
expect_int412(var_export(['key'], true));
expect_int412(var_export(['key'], rand() % 2 > 0));
echo strlen(var_export(['key'], true));  // should not warn

expect_int412(print_r(['key']));
expect_int412(print_r(['key'], false));
expect_int412(print_r(['key'], true));
expect_int412(print_r(['key'], rand() % 2 > 0));
echo strlen(print_r(['key'], true));  // should not warn
echo strlen(var_export(['key'], 1));  // should not warn
echo strlen(var_export(['key'], 0));  // should warn
