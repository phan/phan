<?php
class NewSerialize {
    public $prop;

    public function __serialize() {
        return ['prop' => $this->prop];
    }

    public function __unserialize($data) {
        $this->prop = $data['prop'];
    }
}

// Phan should detect incorrect return statements for __serialize/__unserialize
class NewSerializeInvalid {
    public $prop;

    public function __serialize() {
        return 'data';
    }

    public function __unserialize($data) {
        $this->prop = $data['prop'];
        return [];
    }
}
