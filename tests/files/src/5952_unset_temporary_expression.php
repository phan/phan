<?php
// unset() of a dimension of a constant, class constant, literal or other temporary expression is a compile error
function get_array_5952(): array { return []; }
class UnsetTemporary5952 {
    private const DEMO = ['c' => ['d' => 42]];
    public $prop = ['c' => 1];
    public static $static_prop = ['c' => 1];

    public function run(array $arr): void {
        unset(self::DEMO['c']);
        unset(UnsetTemporary5952::DEMO['c']['d']);
        unset(PHP_EOL['c']);
        unset('str'['c']);
        unset(['c' => 1]['c']);
        unset(($arr + [])['c']);
        // These are accepted by PHP
        unset($arr['c']);
        unset($this->prop['c']);
        unset(self::$static_prop['c']);
        unset($this->other()['c']);
        unset(get_array_5952()['c']);
        unset(self::make()['c']);
    }

    public function other(): array { return []; }

    public static function make(): array { return []; }
}
