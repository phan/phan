<?php

function test364() {

    $compare = function($x, $y) {
        return $x <=> $y;
    };
    p364(array_pop(get_array_by_ref364()));
    p364(array_shift(get_array_by_ref364()));
    p364(current(get_array_by_ref364()));
    p364(end(get_array_by_ref364()));
    $x = get_array_by_ref364();
    p364(next($x));
    p364(prev($x));
    p364(key($x));  // TODO: Should be stricter if $x had an array type or was an iterator with known key type
    p364(pos(get_array_by_ref364()));
    p364(reset(get_array_by_ref364()));
    p364(array_change_key_case(['X' => 3], CASE_LOWER));
    p364(array_combine(['X' => 'key1'], ['value' => new stdClass()]));
    p364(array_combine(['X' => 'key1'], ['value' => [2]]));
    p364(array_diff(['value', 'test'], [2]));
    p364(array_fill_keys(['value'], 5));
    p364(array_fill(0, 5, 4.3));
    p364(array_intersect([2.1, 3.5], [2.1, 4.2]));
    p364(array_intersect_assoc([2.1, 3.5], [2.1, 4.2]));
    p364(array_intersect_key([2.1, 3.5], [2.1, 4.2]));
    p364(array_intersect_key([2.1, 2 => 3.5], [2.1, 4.2]));
    p364(array_intersect_uassoc([2.1, 2 => 3.5], [2.1, 4.2], $compare));
    p364(array_intersect_ukey([2.1, 2 => 3.5], [2.1, 4.2], $compare));
    p364(array_merge([2, 4], ['val', 'x']));
    p364(array_merge_recursive([2, 4], ['val', 'x']));
    p364(array_pad([2, 4], 10, 'val'));
    p364(array_replace([2, 4], [0 => 'value']));  // int[]|string[]
    p364(array_replace_recursive([2, 4], [0 => 'value']));  // int[]|string[]
    p364(array_reverse([new stdClass()]));
    p364(array_udiff([new stdClass()], [2], $compare));  // stdClass[]
    p364(array_udiff_assoc([new stdClass()], [2], $compare));  // stdClass[]
    p364(array_udiff_uassoc([new stdClass()], [2], $compare, $compare));  // stdClass[]
    p364(array_uintersect(['x', 'y'], ['y', 'z'], $compare));  // string[]
    p364(array_uintersect_assoc(['x', 'y'], ['y', 'z'], $compare));  // string[]
    p364(array_uintersect_assoc(['x', 'y'], ['y', 'z'], $compare, $compare));  // string[]
    p364(array_unique(['x', 'y', 'x']));  // string[]
}

function p364(stdClass $x) : void {
}

/** @return &float[] */
function &get_array_by_ref364() {
    return [42.1, 36.5];
}
