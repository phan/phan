<?php
// Ways of calling an internal function that are deprecated, while other calls of it are not
namespace {

class Item014 {
    public function names(): array {
        return [get_class(), get_parent_class(), get_class($this), get_parent_class($this)]; // 8.3: without arguments
    }
}

function argument_counts014(string $key, mixed $context, SessionHandlerInterface $handler): void {
    var_dump(mb_check_encoding(), mb_check_encoding('x'));                  // 8.1
    var_dump(new ReflectionMethod('Item014::names'));                       // 8.4
    var_dump(new ReflectionMethod('Item014', 'names'));
    stream_context_set_option($context, ['http' => ['method' => 'GET']]);   // 8.4
    stream_context_set_option($context, 'http', 'method', 'GET');
    session_set_save_handler('strlen', 'strlen', 'strlen', 'strlen', 'strlen', 'strlen'); // 8.4
    session_set_save_handler($handler, true);
    var_dump(openssl_pkey_derive($key, $key, 10), openssl_pkey_derive($key, $key)); // 8.5
    $args = [];
    var_dump(mb_check_encoding(...$args));                                  // unknown number of arguments
}

function objects014(stdClass $object, array $array, ArrayObject|array $either, ?stdClass $nullable, $unknown): void {
    var_dump(reset($object), end($object), key($object), current($object), next($object), prev($object)); // 8.1
    var_dump(reset($array), end($array), key($array), current($array), next($array), prev($array));
    var_dump(reset($either), reset($unknown), reset($nullable));            // not certain
    array_walk($object, 'var_dump');                                        // 8.6
    array_walk_recursive($object, 'var_dump');                              // 8.6
    array_walk($array, 'var_dump');
    var_dump(http_build_query($object), http_build_query($array));          // 8.6
    $string = 'x';
    mb_convert_variables('UTF-8', 'ISO-8859-1', $string, $object);          // 8.6
    mb_convert_variables('UTF-8', 'ISO-8859-1', $string, $array);
}

function values014(string $name, int $level, array $array, ?string $nullable_key, object $object): void {
    trigger_error('message', E_USER_ERROR);                                 // 8.4
    user_error('message', E_USER_ERROR);                                    // 8.4
    trigger_error('message', E_USER_WARNING);
    trigger_error('message', $level);
    var_dump(new DatePeriod('R4/2012-07-01T00:00:00Z/P7D'));                // 8.4
    var_dump(new DatePeriod($name, 1));                                     // 8.4
    var_dump(new DatePeriod(new DateTime(), new DateInterval('P1D'), 2));
    var_dump(array_key_exists(null, $array));                               // 8.5
    var_dump(array_key_exists($nullable_key, $array), array_key_exists('', $array));
    var_dump(chr(300), chr(-1), chr(65), chr($level));                      // 8.5
    var_dump(ord('ab'), ord(''), ord('a'), ord($name));                     // 8.5
    spl_autoload_unregister('spl_autoload_call');                           // 8.5
    spl_autoload_unregister('strlen');
    var_dump(is_a($name, 'Item014'), is_a('Item014', 'Item014', false));    // 8.6
    var_dump(is_a($name, 'Item014', true), is_a($object, 'Item014'));
}

const CALLBACK014 = 'spl_autoload_call';
function known_values014(?string $nullable_name): void {
    $callback = 'spl_autoload_call';
    spl_autoload_unregister($callback);                                     // 8.5: a variable with a known value
    spl_autoload_unregister(CALLBACK014);                                   // 8.5: a constant
    var_dump(is_a($nullable_name, 'Item014'));                              // not certain: may be null
    var_dump(new DatePeriod($nullable_name));                               // not certain
}

// A function that the analyzed code declares instead of the internal one (a polyfill) is not deprecated
if (!function_exists('imagepolygon')) {
    function imagepolygon($image, array $points, $num_points_or_color, $color = null): bool { return [$image, $points, $num_points_or_color] === $color; }
}
var_dump(imagepolygon(null, [0, 0, 1, 1, 0, 1], 3, 0));

}

// A function of the same name in a namespace is not the internal function
namespace NS014 {
    function reset(object $o): object { return $o; }
    function get_class(): string { return 'x'; }
    var_dump(reset(new \stdClass()), get_class());
}
