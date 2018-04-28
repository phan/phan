<?php

/**
 * @param string $unknown
 * @return array{key:string}
 */
function test_return472(string $unknown) : array {
    switch (rand() % 5) {
        case 0:
            return ['key' => 'x', 'other' => 2];  // valid
        case 1:
            return ['key' => 2, 'other' => 'x'];  // invalid
        case 2:
            return rand() % 2
                ? ['key' => new stdClass()]  // invalid
                : ['key' => 'a'];  // invalid
        case 3:
            return rand() % 2 ? ['key' => 'a'] : ['key' => null];  // invalid
        case 4:
        default:
            return [];
    }
}

/**
 * @param string $unknown
 * @return array<string,string|bool>
 */
function test_return_generic_array_472(string $unknown) : array {
    switch (rand() % 5) {
        case 0:
            return ['key' => 'x', 'other' => 2];  // invalid due to int[]
        case 1:
            return ['key' => 'x', 'other' => false];  // valid
        case 2:
            return ['key' => 'x', 'other' => null];  // invalid
        case 3:
            return ['key' => 'x', 3 => 'y'];  // invalid
        case 4:
        default:
            return [
                'key' => 'x',
                'other' => new stdClass(),  // invalid
                $unknown => null,  // invalid
            ];
    }
}
