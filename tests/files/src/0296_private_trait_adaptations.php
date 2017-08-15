<?php
// Phan should detect and catch some types of misuse of trait adaptations(`insteadof`/`as`)

trait Trait296 {
    public function fPrivate() { }
    public function fProtected() { }
    public function fPublic() { }
    public function fRegular() { }

    private function fPrivateAsPublic() {}
    private function fPrivateAsProtected() {}
    protected function fProtectedAsPublic() {}
}

trait Trait296B {
}

class A296 {
    use Trait296 {
        fPrivate as private asPrivateAlias;
        fProtected as protected asProtectedAlias;
        fPublic as public asPublicAlias;
        fPrivateAsPublic as public asPublicAliasFromPrivate;
        fPrivateAsProtected as protected asProtectedAliasFromPrivate;
        fProtectedAsPublic as public asPublicAliasFromProtected;
    }

    public function accessAPrivate() {
        return $this->asPrivateAlias();  // should not warn.
    }

    private function privateNonTrait() {
    }
}

class B296 extends A296 {
    public function accessParentPrivate() {
        return $this->asPrivateAlias();
    }

    public function accessParentProtected() {
        $this->asProtectedAliasFromPrivate();
        return $this->asProtectedAlias();
    }
}

function test296() {
    $x = new A296();
    // should not warn, the originals are still public
    $x->fPublic();
    $x->fProtected();
    $x->fPrivate();
    $x->fRegular();

    $x->fPrivateAsPublic(); // warn, source visibility does not change
    $x->fPrivateAsProtected(); // warn
    $x->fProtectedAsPublic(); // warn

    // but the new visibilities should be applied
    $x->asPublicAliasFromPrivate();
    $x->asPublicAlias();
    $x->asProtectedAlias();  // warn about bad access
    $x->asPrivateAlias();  // warn about bad access

    $x->fMissing();

    $x->privateNonTrait();  // continue warning about private methods that aren't part of traits
}
test296();
