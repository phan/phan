<?php
class I {
    /**
     * @param array<int,array> $a
     * @return array
     */
    public static function get_empty($x, $a = []) {
        // Should not emit PhanTypeInvalidDimOffset when coalescing is used
        return $a[0] ?? [$x];
    }
}
// Previously would warn, because the array was analyzed with the default of [].
I::get_empty(2);
