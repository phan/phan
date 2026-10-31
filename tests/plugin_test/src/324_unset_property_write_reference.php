<?php
// Regression test for https://github.com/phan/phan/issues/5617
// unset() of a property or of a dimension of a property, and ++/-- of a dimension of a property,
// are write references for dead code detection.
class UnsetWriteReference324 {
    private static $static_arr = ['a' => [1, 2], 'b' => [3, 4]];
    private $arr = ['a' => 1, 'b' => 2];
    private $nested = ['a' => ['b' => 1]];
    private $counts = ['a' => 0];
    private $whole = ['a' => 1];
    private $only_read = ['a' => 1];

    public function run(): array {
        unset(self::$static_arr['a']);
        unset($this->arr['a']);
        unset($this->nested['a']['b']);
        $this->counts['a']++;
        --$this->counts['a'];
        unset($this->whole);
        return [self::$static_arr, $this->arr, $this->nested, $this->counts, $this->only_read];
    }
}
var_export((new UnsetWriteReference324())->run());
