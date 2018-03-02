<?php

class Example436 {
    private $data;

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
}
