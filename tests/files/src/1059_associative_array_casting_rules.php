<?php
/**
 * @param associative-array<int,string> $a
 * @param associative-array<string,string> $b
 */
function test_cannot_pass_associative_to_list($a, $b) {
    if (rand() % 2 === 0) {
        // For php 7.4, this should not warn, this renumbers the keys
        return [...$a];
    }
    return [...$b];
}
