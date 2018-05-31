<?php

/**
 * @param ?array $a
 * @param array{key:?string} $a2
 * @param string $offset
 */
function example471Isset($a, array $a2, string $offset) {
    if (isset($a[$offset])) {
        echo intdiv($a, 2);  // Expect array (not null)
    }
    if (isset($a2['key'])) {
        echo intdiv($a2, -2);  // Expect array{key:string} (not null)
    }
}
/**
 * @param ?array $a
 * @param array{key:?string} $a2
 * @param array{key:?bool} $a3
 * @param string $offset
 */
function example471NotEmpty($a, array $a2, array $a3, string $offset) {
    if (!empty($a[$offset])) {
        echo intdiv($a, 2);
    }
    if (!empty($a2['key'])) {
        echo intdiv($a2, -2);  // Expect array{key:string} (not falsey)
    }
    if (!empty($a3['key'])) {
        echo intdiv($a3, -2);  // Expect array{key:true} (not falsey)
    }
}
