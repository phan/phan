<?php

abstract class BaseSerializable implements Serializable {
    public function serialize() {
        return '';
    }
}
class BadSerializable1 extends BaseSerializable {
    public function unserialize(string $_) {
        return new self();
    }
}

class BadSerializable2 extends BaseSerializable {
    public function unserialize($x) : int {
        return strlen($x);
    }
}

class BadSerializable3 extends BaseSerializable {
    public function unserialize(&$_) {
        return new self();
    }
}

class BadSerializable4 extends BaseSerializable {
    public function unserialize(...$_) {
        return new self();
    }
}

class BadSerializable5 extends BaseSerializable {
    public function unserialize() {
        return new self();
    }
}

class BadSerializable6 extends BaseSerializable {
    public function unserialize($unused_1, $unused_2) {
        return new self();
    }
}
