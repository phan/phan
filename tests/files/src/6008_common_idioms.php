<?php

namespace NS6008;

// An error handler may return nothing: only `false` passes the error on.
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    throw new \ErrorException($errstr, $errno, $errno, $errfile, $errline);
});
set_error_handler(function (int $errno, string $errstr): void { echo $errno, $errstr; });
set_error_handler(function (int $errno, string $errstr): bool { return $errno > 0 && $errstr !== ''; });
set_error_handler(function (int $errno, string $errstr): ?bool { return $errno > 0 && $errstr !== '' ? true : null; });
set_error_handler(function (int $errno, string $errstr): never { throw new \Exception($errstr, $errno); });
set_error_handler(null);
// Still reported: a handler that returns something else, or that takes other parameters.
set_error_handler(static fn (int $errno, string $errstr): string => $errstr . $errno);
set_error_handler(function (string $a, array $b): bool { return $a === '' && !$b; });

// A constant that describes where the code runs is the text that is matched, not the pattern.
function matchesOs(string $pattern, string $needle): bool {
    return preg_match($pattern, PHP_OS) === 1 || strpos(PHP_OS_FAMILY, $needle) !== false || str_contains(\PHP_VERSION, $needle);
}
// Such a constant is not out of place as the needle either.
function osIsListed(): bool {
    return strpos('Linux Darwin WINNT', PHP_OS) !== false;
}
// Still reported: another constant.
const SEPARATOR = ',';
function wrongOrder(string $s): array {
    return [explode($s, SEPARATOR), strpos(PHP_EOL, $s)];
}

/**
 * The arguments of a function that reads them with func_get_args() can only be documented with (at)param.
 * @param string $first
 * @param string ...$rest
 */
function legacy() {
    return func_get_args();
}
/**
 * @param int $count
 * @param string $extra
 */
function legacyWithParam($count) {
    return $count + func_num_args();
}
/**
 * Still reported.
 * @param string $missing
 */
function noParams() {
    return 1;
}
/**
 * @param int $a
 * @param string $missing
 */
function oneParam($a) {
    return $a;
}
/**
 * @param string $missing
 * @param string ...$rest
 */
function variadic(...$rest) {
    return func_get_args() + $rest;
}
class Magic {
    /**
     * Still reported: __call() takes any number of arguments without reading them with func_get_args().
     * @param string $nmae
     * @param array $args
     */
    public function __call($name, $args) {
        return [$name, $args];
    }
}
