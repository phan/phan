<?php
class Data335 {
    /** @var int $haystack */
    public $haystack;

    function __construct($data=1) {
        $this->haystack = $data;
    }
    function find($needle) {
        return in_array($needle, $this->haystack, true);  // should warn
    }
}
$storage335 = new Data335(1);
echo $storage335->find('apple');
