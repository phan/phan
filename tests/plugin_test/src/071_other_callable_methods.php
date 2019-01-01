<?php

class InvokableTemplateTest {
    public function __invoke($arg) : stdClass {
        return (object)['arg' => $arg];
    }

    public static function createArrayObject($args) : ArrayObject {
        return new ArrayObject($args);
    }
}

/**
 * @template T
 * @param callable(mixed):T $c
 * @return T
 */
function template_return_test(callable $c) {
    return $c('arg');
}
$o = new InvokableTemplateTest();
echo strlen(template_return_test(['InvokableTemplateTest', 'createArrayObject']));
echo strlen(template_return_test(['InvokableTemplateTest', 'missingMethod']));
echo strlen(template_return_test([$o, 'createArrayObject']));
echo strlen(template_return_test([$o, 'missingMethod']));
echo strlen(template_return_test(new ArrayObject()));
$c = [$o];
var_export($o());
var_export((new stdClass())());
echo strlen(template_return_test($c));
function non_template_return_test(callable $c) {
    var_export($c('arg'));
}
non_template_return_test(['InvokableTemplateTest', 'createArrayObject']);
non_template_return_test(['InvokableTemplateTest', 'missingMethod']);
non_template_return_test([$o, 'createArrayObject']);
non_template_return_test([$o, 'missingMethod']);
non_template_return_test(new ArrayObject());
non_template_return_test($c);

function create_from_closure(Closure $x, callable $y) {
    echo strlen(template_return_test($x));  // should not warn, type is unknown
    echo strlen(template_return_test($y));  // should not warn, type is unknown
}

/**
 * NOTE: Currently may only detect some issue types when templates are involved,
 * but not detect them when templates aren't involved.
 *
 * @template T
 * @param array<mixed,callable(mixed):T> $a
 * @return T[]
 */
function template_return_test_array(array $a) {
    return [$a[0]('value')];
}
// TODO: A side effect of the current implementation
// is that this only warns about misuse of templates when the return value is used.
var_export(template_return_test_array([new stdClass()]));
echo strlen(template_return_test_array(['strlen']));
