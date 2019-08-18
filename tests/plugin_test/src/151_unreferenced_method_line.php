<?php
// Regression test for saving the wrong line number of an unreferenced method.
class HasUnusedMethod
{
    public function foo(
        $array,
        $arrayIterator
    ) {
        if ($array instanceof \Traversable) {
            $array = function () use ($arrayIterator, &$array) {
                $array = [$arrayIterator];
            };
        }
        return $array;
    }
}
$x = new HasUnusedMethod();
