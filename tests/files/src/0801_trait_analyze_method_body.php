<?php
trait T301 {
    private function f2() : int {
        return "a";  // Should warn about this line
    }
}

trait U301 {
    private function f() : self {
        return [];
    }
}

class HasTrait301 {
    use U301;
}
