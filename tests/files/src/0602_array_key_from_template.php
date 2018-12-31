<?php

/**
 * @template TKey
 * @template TValue
 * @param array<mixed,TKey> $a
 * @param array<mixed,TValue> $b
 * @return array<TKey,TValue>
 */
function array_from_template(array $a, array $b) {
    $result = [];
    foreach ($a as $key => $value) {
        if (array_key_exists($key, $b)) {
            $result[$b[$key]] = $value;
        }
    }
    return $result;
}
echo strlen(array_from_template([0], [rand(0,5)]));
echo strlen(array_from_template(['key'], [rand(0,5)]));
echo strlen(array_from_template([], [rand(0,5)]));  // should not crash
echo strlen(array_from_template([], []));  // should not crash
echo strlen(array_from_template([rand(0,5)], []));  // should not crash
echo strlen(array_from_template([false,true], []));  // should not crash
echo strlen(array_from_template([[]], []));  // should not crash
echo strlen(array_from_template(null, null));  // should not crash
