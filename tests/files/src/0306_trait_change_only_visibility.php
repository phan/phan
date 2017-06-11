<?php

trait MyTrait306 {
    public function publicAsPrivate() {}
    public function publicAsProtected() {}
    public function publicAsProtected2() {}
    public function publicAsPublic() {}
    private function privateAsPrivate() {}
    private function privateAsProtected() {}
    private function privateAsProtected2() {}
    private function privateAsPublic() {}
}
class Foo306 {
    use MyTrait306 {
        publicAsPrivate as private;
        publicAsProtected as protected;
        // publicAsProtected2 as protected;  // FIXME : This should be an error if the alias and the original have the same name.
        publicAsPublic as public;
        privateAsPrivate as private;
        privateAsProtected as protected;
        // privateAsProtected2 as protected privateAsProtected2; // FIXME : This should be an error if the alias and the original have the same name.
        privateAsPublic as public;
        missingAsProtected as protected;
    }
}
function test_foo306() {
    $foo = new Foo306();
    $foo->publicAsPublic();  // this is fine
    $foo->publicAsProtected();  // should warn
    // $foo->publicAsProtected2();  // should warn
    $foo->publicAsPrivate();  // should warn
    $foo->privateAsPublic();  // this is fine
    $foo->privateAsProtected();  // should warn
    $foo->privateAsPrivate();  // should warn
    // $foo->privateAsProtected2();  // should warn
    $foo->missingAsProtected();  // this is undeclared
}
test_foo306();
