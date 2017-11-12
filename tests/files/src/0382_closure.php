<?php
// Test of Closure::bind, bindTo not supported yet.
class ClosureBindTest {

    public function main() {
        $x = Closure::bind(function(int $x) : bool {echo $x; return $x > 90;}, null);
        $x();  // should warn
        echo count($x(2));  // should warn

        $y = Closure::bind(function() : string { return get_class(); }, $this);
        $y('extra');
    }
}

(new ClosureBindTest())->main();
