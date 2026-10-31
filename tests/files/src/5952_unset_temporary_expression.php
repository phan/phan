<?php
// unset() of a dimension of a constant, class constant, literal or other temporary expression is a compile error
const EOL_5952 = "\n";
function get_array_5952(): array { return []; }
class UnsetTemporary5952 {
    private const DEMO = ['c' => ['d' => 42]];
    public $prop = ['c' => 1];
    public static $static_prop = ['c' => 1];

    public function run(array $arr): void {
        unset(self::DEMO['c']);
        unset(UnsetTemporary5952::DEMO['c']['d']);
        unset(EOL_5952['c']);
        unset('str'['c']);
        unset(['c' => 1]['c']);
        unset(($arr + [])['c']);
        // The receiver of a property fetch is compiled in the same write context
        unset((new UnsetTemporary5952())->prop['c']);
        unset((new UnsetTemporary5952())->prop);
        unset('str'->prop['c']);
        unset(self::DEMO['c']->prop);
        // These are accepted by PHP
        unset($arr['c']);
        unset($this->obj()->prop['c']);
        unset($this->obj()->prop);
        unset($this->obj()::$static_prop['c']);
        unset($this->obj()->prop['c']['d']);
        unset($this->prop['c']);
        unset(self::$static_prop['c']);
        unset($this->other()['c']);
        unset(get_array_5952()['c']);
        unset(self::make()['c']);
    }

    public function other(): array { return []; }

    public function obj(): UnsetTemporary5952 { return $this; }

    public static function make(): array { return []; }
}
