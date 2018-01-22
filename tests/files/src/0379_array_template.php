<?php
namespace MyNS;
// Make sure that we don't interpret array<int, string> as MyNS\array<int, string>
class TemplateArray379 {
    /**
     * @param array<int,string> $x
     * @return array<int, int>
     */
    public function keys($x) {
        return \array_keys($x);
    }

    /**
     * @param array<string,string> $x
     * @return array<string, string> (wrong key)
     */
    public function keys2($x) {
        return \array_keys($x);  // should warn
    }
}

$t = new TemplateArray379();

var_export($t->keys(['x']));
var_export($t->keys([3.5]));
