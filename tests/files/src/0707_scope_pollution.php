<?php

namespace NS705;

class Subclass {
    /**
     * @param string $name
     * @param mixed|null $default
     */
    public function getOption($name, $default = null) : string {
        var_export($default);
        $name = (string)$name;  // should not emit PhanRedundantCondition because this was a phpdoc type
        return $name;
    }
}
class Other extends Subclass {
    public function test() {
        $this->getOption('something');
    }
}
