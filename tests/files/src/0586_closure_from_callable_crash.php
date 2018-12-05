<?php
class misc {
    protected function _sort() {
        $cb = Closure::fromCallable([$this, "foo"]);
        $cb('arg', []);
        $cb('arg', 'invalid');
    }

    protected function foo($type, array $item) {
        var_export([$type, $item]);
    }
}
