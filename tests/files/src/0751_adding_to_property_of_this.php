<?php

class MyArrayBuilder {
    public $prop;
    public function create(array $values) {
        $this->prop = [];
        foreach ($values as $k => $_) {
            $this->prop[] = $k;
        }
        // should not emit false positive PhanRedundantCondition Redundant attempt to cast $this->prop of type array{} to empty
        if (empty($this->prop)) {
            echo "Had empty input\n";
        }
        return $this->prop;
    }
}
