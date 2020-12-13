<?php
class X {
    /** @var array */
    public $config;
    public function main($body, $headers) {
        if (rand(0, 1)) {
            $this->config['offset'][1] = $body;
        }
        if (isset($this->config['offset'][1])) {
            $value = $this->config; '@phan-debug-var $value';
            $other = $value['offset']; '@phan-debug-var $other';
            // Regression test - this should not emit PhanImpossibleCondition
            if (! isset($this->config['offset'][2])) {
                throw new Exception();
            }
        }
    }
}
