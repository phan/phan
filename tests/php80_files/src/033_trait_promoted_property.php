<?php
trait T32 {
    public function __construct(private int $x) {}
}
class Y32 {
    use T32;
    // Should infer the private property from the trait is accessible from within the class
    public function getX(): stdClass {
        return $this->x;
    }
}
$y = new Y32(32);
var_export($y->getX());
var_export($y->x);  // inaccessible
