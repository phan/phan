<?php

// Regression test for https://github.com/phan/phan/issues/4883

namespace NS982;

class HasStaticProp {
    /** @var static */
    public $staticProp;
}

class ChildWithStaticProp extends HasStaticProp {
    public function __construct() {
        $this->staticProp = $this;
    }
}

$base = (new HasStaticProp)->staticProp;
'@phan-debug-var $base';
$child = (new ChildWithStaticProp)->staticProp;
'@phan-debug-var $child';


class UnrelatedClass {
    /** @suppress PhanUnusedVariable */
    public function unrelatedMethod() {
        $baseInstance = new HasStaticProp;
        $baseProp = $baseInstance->staticProp;
        '@phan-debug-var $baseProp';
        $baseInstance->staticProp = new HasStaticProp;
        $baseInstance->staticProp = new ChildWithStaticProp;
        $baseInstance->staticProp = $this;

        $childInstance = new ChildWithStaticProp;
        $childProp = $childInstance->staticProp;
        '@phan-debug-var $childProp';
        $childInstance->staticProp = new ChildWithStaticProp;
        $childInstance->staticProp = new HasStaticProp; // TODO: This should warn
        $childInstance->staticProp = $this;
    }
}
(new UnrelatedClass)->unrelatedMethod();
