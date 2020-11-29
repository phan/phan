<?php
// When this test case is run
namespace {

function contains_x(string $x) {
    return str_contains($x, 'x');
}
var_export(contains_x('xyz'));

}

namespace NS914 {

function contains_x(string $x) {
    return str_contains($x, 'x');
}
var_export(contains_x('xyz'));

}
