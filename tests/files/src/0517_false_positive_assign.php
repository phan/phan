<?php

class Formatter517 {
    public static function format($file, $line) : string {
        return "$file:$line";
    }
}


/**
 * Regression test for a false positive about a variable assigned in an `if` statement
 * being undefined.
 */
class Example517 {
    /**
     * @param ?Formatter517 $fmt
     */
    public function access($fmt, string $file, int $line) {
        if ($file) {
            if ($fmt && $link = \is_string($fmt) ? strtr($fmt, array('%f' => $file, '%l' => $line)) : $fmt->format($file, $line)) {
                echo $link;
                echo count($link);  // should warn
            }
        }
    }
}
