<?php

class BaseClass {
    /**
     * @return never
     */
    protected function fatalError( $x ) {
        exit();
    }
}

class ChildClass extends BaseClass {
    /**
     * @return never
     */
    public function fatalError( $x ) {
        echo implode(['a' => $x]);
        parent::fatalError( $x );
    }

    public function main() {
        $this->fatalError( 42 );
    }
}
var_dump((new ChildClass())->main());
