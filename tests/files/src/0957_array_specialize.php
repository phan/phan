<?php

class ArrayPlusTest {
    /** @var array[] */
    private $firstProp = [];

    /** @var array[] */
    private $secondProp = ['foo' => [ 'bar' => 'goat' ] ];

    public function doTest() {
        $data = [];
        $intersect = array_intersect_key( $this->secondProp, $this->firstProp );
        if ( $intersect ) {
            foreach ( $intersect as $kvp ) {
                $data += $kvp;
            }
            '@phan-debug-var $data';
        }
    }
}
