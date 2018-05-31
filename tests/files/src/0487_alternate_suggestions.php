<?php

// NOTE: Suggestions in may fail to be made
// for some classes in namespaces with many classes,
// because of the 'suggestion_check_limit' config being too low.
class Avid extends Exception {
}

interface Imj extends ArrayAccess {
}
interface Imi extends  throwable {
}
trait Ime {}
class Imf extends Exception {
}
class Img {
}

class A487 {
    /** @var avoid */
    public $x;

    /**
     * @param avoid $x
     * @return avoid
     * @throws avoid
     */
    public function test_suggestions($x) {
        if (rand() % 2) {
            throw $x;
        }
        var_export($x);
    }

    /**
     * @throws imt (should only suggest classes)
     * @param array{a:stdClass,x:imt}[] $x
     * @return array{a:stdClass,x:imt}[]
     */
    public function test_throw_suggestions($x) {
        if (rand() % 2) {
            throw $_GLOBALS['_exception'];
        }
        return $x;
    }
}
