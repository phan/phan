<?php
// Arguments of parameters passed by reference define the variable whether they are passed by position or by name,
// and a reference to an offset creates the array and the offset, without any warning.

function set_by_reference(int $value, &$result): void {
    $result = $value;
}

function named_by_reference_arguments(): void {
    preg_match('/(x)/', 'x', flags: 0, matches: $named_matches);
    echo $named_matches[1];
    exec('true', output: $output, result_code: $code);
    echo count($output), $code;
    set_by_reference(result: $result, value: 1);
    echo intdiv($result, 2);
    var_dump(value: $undefined_named);
}

function dim_by_reference_argument(): void {
    preg_match('/(x)/', 'x', $out['m']);
    echo $out['m'][1];
    var_dump($undefined_base['m']);
}

function reference_creates_offset(string $key): array {
    $by_key = [];
    $first = &$by_key[$key];
    $shape = ['x' => 1];
    $second = &$shape['y'];
    var_dump($shape['y']);
    $matches = [];
    preg_match('/x/', 'x', $matches['k']);
    echo $matches['k'][0];
    $undefined_array_ref = &$created['a']['b'];
    $read = ['x' => 1];
    var_dump($read['missing']);
    return [$first, $second, $by_key, $created, $undefined_array_ref];
}

function append_argument(): string
{
    $out = [];
    preg_match('/(x)/', 'x', $out[]);
    return $out[0][0];
}

function &return_offset_by_reference(bool $b)
{
    if ($b) {
        $a = [];
    }
    return $a['k'];
}

function return_offset_by_value(bool $b)
{
    if ($b) {
        $a = [];
    }
    return $a['k'];
}

function nested_append_argument(): string
{
    $out = [];
    preg_match('/(x)/', 'x', $out[]['m']);
    return $out[0]['m'][0];
}

function by_value_pair(int $value, int $other): int
{
    return $value + $other;
}

function named_argument_read_before_assignment(): int
{
    // The read of $later is reported: the second argument assigns it after the first one was evaluated.
    return by_value_pair(value: $later, other: ($later = 1));
}

function untyped_output(&$value): void
{
    $value = 42;
}

function typed_output(int &$value): void
{
    $value = 42;
}

function offset_for_untyped_parameter(): int
{
    $out = [];
    untyped_output($out['k']);
    return $out['k'];
}

function offset_for_typed_parameter(): string
{
    $out = [];
    typed_output($out['k']);
    return $out['k'];
}

/** @return \Generator<int,int,mixed,mixed> */
function &generator_return_is_not_a_reference()
{
    $a = ['x' => 1];
    yield 1;
    return $a['missing'];
}
