<?php
class X848 {
    /**
     * @param string $c
     */
    public function find($c) : void {
        $result = [];

        if ($c) {
            $result['c'] = $c;
        }
        '@phan-debug-var $result';

        if (isset($result['c'])) {
            echo "Found\n";
        }
    }
}
