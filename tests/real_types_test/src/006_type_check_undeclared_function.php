<?php
// 1. Should infer the return type
// 2. Should warn that the parameters would be invalid
// 3. Should warn that xdebug is undefined due to ignore_undeclared_functions_with_known_signatures being false (This test should restart itself without xdebug if that was loaded)
namespace {
    echo spl_object_hash(xdebug_get_stack_depth('stack'));
}
namespace My\Project {
    echo spl_object_hash(xdebug_get_stack_depth('stack'));
}
