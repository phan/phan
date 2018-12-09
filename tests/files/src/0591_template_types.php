<?php

/**
 * @template T
 * @property T $value
 * @property string $details
 * @method hasValueMagic(T):bool
 */
class SingletonList {
    private $_value;

    /**
     * @param T $value
     */
    public function __construct($value) {
        $this->_value = $value;
    }

    public function __call($method, array $args) {
        if ($method === 'hasValueMagic') {
            return $args[0] === $this->_value;
        }
    }

    public function __get($name) {
        switch ($name) {
            case 'value':
                return $this->_value;
            case 'details':
                return json_encode($this->_value);
        }
    }

    /**
     * @param T $v
     */
    public function hasValue($v) : bool {
        return $v === $this->_value;
    }

    /**
     * @return array<int,T>
     */
    public function toArray() : array {
        return [$this->_value];
    }
}

/**
 * @inherits SingletonList<ast\Node>
 */
class NodeSingletonList {
    public function __construct() {
        parent::__construct(new ast\Node());
    }
}

call_user_func(function () {
    // The error messages include the types
    $o = new stdClass();
    $l = new SingletonList($o);
    $l2 = new SingletonList(new ArrayObject());
    echo $l->details;
    echo $l->details->property;  // Phan should infer $l->details is a string and warn
    echo $l->value;  // should warn about stdClass
    echo $l2->value;  // Should warn about this being ArrayObject
    // Phan will warn if the type doesn't match the resolved template type.
    var_export($l->hasValue($o));  // does not warn
    var_export($l->hasValue(new ArrayObject()));  // does warn
    var_export($l->hasValueMagic($o));  // does not warn
    var_export($l->hasValueMagic(new ArrayObject()));  // should warn
    // Phan recursively changes the template types to real types, as seen in the error message
    echo strlen($l->toArray());
    echo strlen($l2->toArray());

    $n = new NodeSingletonList();
    var_export($n->hasValue($o));  // should warn
});
