<?php

// Regression test for https://github.com/phan/phan/issues/4916

namespace NS979;

class TestCoalesce {
    /** @var string|null */
    private $prop;
    private $otherProp;

    /** @suppress PhanUnusedVariable */
    public function test() {
        if ( rand() && !$this->prop ) {
            $this->prop = 'foo';
        }

        $propBefore = $this->prop;
        '@phan-debug-var $propBefore';

        if ( !$this->otherProp ) {
            return;
        }

        $propAfter = $this->prop;
        '@phan-debug-var $propAfter';

        // No issue here
        echo $this->prop ?? 'bar';
    }
}
