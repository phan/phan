<?php

class Example436 {
    private $data;

    private $otherData = ['a1' => 'string', 'a2' => 33];

    public function __construct()  {
        $this->data = ['key' => 'value'];
    }

    public function addOtherKey()  {
        $this->data['otherKey'] = 'value';
    }

    public function getOriginalKeyLen() : int  {
		return strlen($this->data['key']);
    }

    public function misuseOriginalKey() : int  {
		return count($this->data['key']);
    }

    public function addOtherData() {
        $this->otherData['a1'] = 'key';
        $this->otherData['unrelatedKey'] = 33;
    }
}
