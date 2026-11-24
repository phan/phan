<?php
// Taking a reference to an undefined variable defines it (as null) in PHP, without any warning.
// Phan still reports a variable that is undefined on every path (it is probably a typo), once, and then treats it as null.
// A variable that is only possibly undefined is not reported. Reading an undefined variable is always reported.

$global_alias = &$global_target;
var_dump($global_alias, $global_target);
$global_copy = $global_undefined;
var_dump($global_copy);

function assign_ref_defines_variable(): void {
    $alias = &$target;
    echo strlen($target);  // null until assigned through $alias
    echo strlen($alias);
    $copy = $undefined_value;
    var_dump($copy);
}

function assign_ref_defines_possibly_undefined_variable(): void {
    if (rand(0, 1)) {
        $target = 'value';
    }
    $alias = &$target;
    var_dump($alias, $target);
    if (rand(0, 1)) {
        $other = 'value';
    }
    $copy = $other;
    var_dump($copy);
}

function array_element_ref_defines_variable(): array {
    $by_ref = [&$element];
    $by_value = [$undefined_element];
    return [$by_ref, $by_value, $element];
}

function self_references(): array {
    $self = [&$self];
    $dim[0] = &$dim;
    $append[] = &$append;
    $outer[] = $inner = &$outer;
    return [$self, $dim, $append, $outer, $inner];
}

function property_ref_defines_variable(stdClass $o): void {
    $o->ref = &$property_target;
    var_dump($property_target);
    $o->value = $undefined_property_value;
}

function &return_by_reference() {
    return $returned_by_reference;
}

function return_by_value() {
    return $returned_by_value;
}

function closure_use_by_reference(): array {
    if (rand(0, 1)) {
        $maybe = 1;
    }
    $by_ref = function () use (&$maybe): ?int {
        return $maybe;
    };
    $by_ref_undefined = function () use (&$never_defined) {
        return $never_defined;
    };
    if (rand(0, 1)) {
        $maybe_by_value = 1;
    }
    $by_value = function () use ($maybe_by_value): ?int {
        return $maybe_by_value;
    };
    $by_value_undefined = function () use ($undefined_by_value) {
        return $undefined_by_value;
    };
    return [$by_ref, $by_ref_undefined, $by_value, $by_value_undefined, $maybe, $never_defined];
}
