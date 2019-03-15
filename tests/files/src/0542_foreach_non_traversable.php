<?php

/**
 * @property int $x
 */
class HasNoProperties {
    public function __get(string $name) {
        return strlen($name);
    }

    public function foreach_test() {
        foreach ($this as $k => $v) {  // This warns about there being no properties
            var_export([$k, $v]);
        }
    }
}

class HasPrivateProperties {
    /** @var string */
    private $p = 'default';
    public function foreach_test(ArrayObject $ao, Traversable $t, stdClass $stdClass, ast\Node $node) {
        foreach ($this as $c) {  // should warn about iterating over object with some accessible properties
            var_export($c);
        }
        foreach ($t as $c) {  // should not warn, this is traversable
            var_export($c);
        }
        foreach ($ao as $c) {  // should not warn, this extends traversable
            var_export($c);
        }
        foreach ($stdClass as $c) {  // should not warn, this is stdClass and expected to have dynamic properties
            var_export($c);
        }
        foreach ($node as $c) {  // should warn about iterating over object with some accessible properties
            var_export($c);
        }
    }
}
class ExtendsClassWithPrivateProperties extends HasPrivateProperties {
    public function other_foreach_test() {
        foreach ($this as $c) {  // should warn that no properties are accessible
            var_export($c);
        }
    }
}
call_user_func(function () {
    $p2 = new HasPrivateProperties();
    foreach (
        $p2  // should warn about inaccessible properties on the same line as $p2
        as
        $v
    ) {
        var_export($v);
    }
    foreach ((new HasNoProperties()) as $v) {  // should warn about absence of any properties
        var_export($v);
    }
});
