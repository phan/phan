<?php
// Syntax that PHP 8.3 to 8.6 deprecate
namespace NS015;

use Foo015\Bar as let;                  // 8.6: an alias with a name reserved for future syntax
use Foo015\Baz as is;
use Foo015\Qux as _;
use Foo015\let as Fine;                 // only the alias matters
use function Foo015\f as let;
use function Foo015\g as fine;
use function Foo015\{h as is};
use const PHP_INT_MAX as is;            // 8.6: also for the alias of a constant
use const Foo015\{C as let, D as _, E as fine};

interface _I {}                         // not deprecated
enum Suit015 {
    case namespace;                     // 8.6
    case Hearts;
}

function operators015(array $array, \ArrayAccess $object, ?array $nullable, int $number): void {
    echo `echo hi`;                     // 8.5: the backtick operator
    echo shell_exec('echo hi');

    $s = 'abc';
    $s++;                               // 8.5: increment of a non-numeric string
    $t = 'abc';
    $t--;                               // 8.3: decrement of a non-numeric string
    $e = '';
    $e++;                               // 8.3
    $bang = 'a-b';
    ++$bang;                            // 8.3: not alphanumeric
    $numeric = '5';
    $numeric++;
    $float = '1e2';
    $float--;
    $number++;
    var_dump($s, $t, $e, $bang, $numeric, $float, $number);

    var_dump($array[null]);             // 8.5: null as an array offset
    $array[null] = 1;
    var_dump(isset($array[null]), $array[null] ?? 1);
    $key = null;
    var_dump($array[$key], [null => 1]);
    var_dump($array[''], $array[0], $array[] = 1);
    var_dump($object[null], $nullable[null]); // an ArrayAccess object, or not certain to be an array
    [null => $from_array] = $array;      // 8.5: destructuring reads the offset null of the array
    [null => $from_object] = $object;    // not deprecated for an ArrayAccess object
    ['k' => [null => $nested]] = $array; // the type of the nested source is not checked
    foreach ($array as [null => $element]) {
        var_dump($element);
    }
    var_dump($from_array, $from_object, $nested);
}
var_dump(new let(), new is(), new _(), new Fine(), let(), fine(), is());

namespace NS015b;

class _ {}                              // 8.4: "_" as the name of a class
trait let {}                            // 8.6
const is = 1;                           // 8.6: a constant
const other = 2;
