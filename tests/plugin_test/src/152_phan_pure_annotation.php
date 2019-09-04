<?php
// The @phan-side-effect-free annotation is used to indicate that the function does not have any side effects
// that matter to the end user of the application/library.
// It may be manually added when Phan can't automatically infer the function is pure,
// or when the side effects are unimportant (e.g. debug logging, performance logging)
namespace NS152;
class Debug {
    /**
     * @phan-side-effect-free
     * @return int
     */
    public function double1(int $x) {
        var_dump($x);
        return $x * 2;
    }

    /*
     * @return int
     */
    public function double2(int $x) {
        var_dump($x);
        return $x * 2;
    }

    /**
     * @phan-side-effect-free
     * @return int
     */
    public static function triple1(int $x) {
        return debug_trace_nonpure((string)$x) * 3;
    }

    /**
     * @return int
     */
    public static function triple2(int $x) {
        return debug_trace_nonpure((string)$x) * 3;
    }
}

/**
 * @phan-side-effect-free
 */
function debug_trace(string $value) {
    if (getenv('DEBUG')) {
        echo "Processing '''$value'''\n";
    }
    return $value;
}

function debug_trace_nonpure(string $value) {
    if (getenv('DEBUG')) {
        echo "Processing '''$value'''\n";
    }
    return $value;
}
// Should warn
debug_trace($argv[0]);  // should warn about unused
// Should not warn
debug_trace_nonpure($argv[0]);
$d = new Debug();
$d->double1(21);  // should warn about unused
$d->double2(2);
Debug::triple1(14);  // should warn about unused
Debug::triple2(14);

$trace1 = function ($o) {
    fwrite(STDERR, "Saw $o\n");
    return $o;
};
/** @phan-side-effect-free */
$trace2 = function ($o) {
    fwrite(STDERR, "Saw $o\n");
    return $o;
};
$trace1(sprintf("Hello, %s", "world"));
$trace2(sprintf("Hello, %s", "world"));
