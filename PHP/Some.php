<?php
declare(strict_types=1);
namespace php;

require_once(__DIR__.'/Option.php');

class Some extends Option {

    /**
     * @var mixed
     */
    private $value;

    /**
     * @param mixed $value
     */
    public function __construct($value) {
        $this->value = $value;
    }

    /**
     * @return bool
     */
    public function isEmpty() : bool {
        return false;
    }

    /**
     * @return bool
     */
    public function isDefined() : bool {
        return true;
    }

    /**
     * @return mixed
     */
    public function get() {
        return $this->value;
    }

    /**
     * @return mixed
     */
    public function getOrElse(mixed $else) {
        return $this->value;
    }
}
