<?php

/**
 * @method static int static_fn()
 * @method int instance_fn()
 * @phan-pure
 */
class Magic154 {
    public $values;

    public function __construct(array $values) {
        $this->values = $values;
    }

    public function __call(string $method, array $args) {
        return [$method, $args];
    }

    public static function __callStatic(string $method, array $args) {
        $args[] = $method;
        sort($args);
        return $args;
    }

    public function __set(string $name, $value) {
        // no-op
    }
    public function __get(string $name) {
        return $this->values[$name] ?? null;
    }
}
$m = new Magic154(['blah' => 'v1']);
$m->__get('blah');  // should warn about this being unused
$m->__call('blah', ['args']);  // should warn about this being unused
$m->__set('blah', 'unused');
$m->instance_fn();  // should warn about being unused
Magic154::static_fn();  // should not warn
$m->unknown_instance_fn();  // should warn about being unused, the class is pure
$m->values = ['blah' => 'v2'];  // should warn
