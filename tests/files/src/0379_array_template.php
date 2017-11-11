<?php
namespace MyNS;
// Make sure that we don't interpret array<int, string> as MyNS\array<int, string>
class TemplateArray379 {
    /**
     * @param array<int, string>
     * @return array<int, int>
     */
    public function keys($x) {
        return \array_keys($x);
    }
}

$t = new TemplateArray379();

var_export($t->keys(['x']));
var_export($t->keys([3.5]));
