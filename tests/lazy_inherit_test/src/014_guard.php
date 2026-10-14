<?php
// MutateRootPlugin initializes the scope of V014Base::configure when G014First is analyzed (in the class phase),
// between the imports of G014First and G014Second.
class G014First extends V014Base
{
    public function reportedByMutateRootPlugin(): void {}
}
class G014Second extends V014Base {}
class G014Third extends G014Second {}

function test014(G014First $f, G014Second $s, G014Third $t): void
{
    $f->configure('x', 1);
    $s->configure('x', 1);
    $t->configure([]);
    $s->other('y');
}
