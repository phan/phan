<?php
class Example {
    static function test(string $name): self {
        static $results = [];
        if (!isset($results[$name])) {
            $results[$name] = new static();
        }
        return $results[$name];
    }

    public function factory(): self {
        static $var;
        if (!is_object($var)) {
            $var = $this->test('default');
        }
        return $var;
    }
}
(new Example())->factory();
