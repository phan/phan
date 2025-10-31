<?php

// Test that foreach loops with assignments in the 'as' clause are not flagged as empty

class Container {
    public ?string $key = null;
    public ?string $value = null;
}

$container = new Container();
$arr = ['k1' => 'v1', 'k2' => 'v2', 'k3' => 'v3'];

// Valid: foreach with property assignments in as clause (has side effects)
foreach ($arr as $container->key => $container->value) {
}

// Valid: foreach with array element assignments in as clause (has side effects)
$result = [];
foreach ($arr as $result['last_key'] => $result['last_value']) {
}

// Valid: foreach with value-only property assignment
$container2 = new Container();
foreach ($arr as $container2->value) {
}

// Invalid: foreach with simple variable assignments and empty body (truly empty, should warn)
foreach ($arr as $k => $v) {
}

// Valid: foreach with simple variables but has body
foreach ($arr as $k2 => $v2) {
    echo "$k2 => $v2\n";
}

// Edge case: Mix of property and variable (should not warn - has side effect)
$container3 = new Container();
foreach ($arr as $k3 => $container3->value) {
}

// Edge case: Array element for key only
$keys = [];
foreach ($arr as $keys[] => $temp_val) {
}
