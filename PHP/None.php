<?php
declare(strict_types=1);
namespace php;

require_once(__DIR__.'/Option.php');

class None extends Option {

    /**
     * @return bool
     */
    public function isEmpty() : bool {
        return true;
    }

    /**
     * @return bool
     */
    public function isDefined() : bool {
        return false;
    }

    /**
     * @return mixed
     */
    public function get() {
        throw new Exception('get called on None');
    }

    /**
     * @return mixed
     */
    public function getOrElse(mixed $else) {
        return $else;
    }

}
