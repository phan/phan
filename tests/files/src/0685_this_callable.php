<?php

class Invokable685 {
    public function __construct(array $values) {
    }

    public function __invoke(int $arg) {
        echo "__invoke $arg\n";
    }

    public function dynamic(stdClass $o) {
        var_export($o);
    }

    public function test() {
        $instance = new Invokable685();
        $this('invalid');  // should warn
        $this(2);
        $instance('invalid');  // should warn
        $instance(2);
        call_user_func([$this, 'dynamic'], new stdClass());
        // should all warn
        call_user_func([$this, 'dynamic']);
        call_user_func([$this, 'missing']);
        call_user_func([$instance, 'dynamic']);
        call_user_func([$instance, 'missing']);
        call_user_func_array([$this, 'dynamic'], []);

        // should not warn
        call_user_func_array([$this, 'dynamic'], [new stdClass()]);
        // should warn
        $cb = Closure::fromCallable([$this, 'dynamic']);
        $cb();
    }

    public function testConstructor() {
        $a = new $this();
        $a->dynamic(null);
    }
}
