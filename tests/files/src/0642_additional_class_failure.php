<?php

abstract class PhanUndeclaredClassFailureString
{
    private $fields = array();
    public function set($data, $value = null)
    {
        $class = $this->fields[0]['opt']['class'];
        $test = new $class();
    }

    public function addField($field)
    {
        $this->fields[$field] = [
            'id' => 'xxx', // Additional value of type string
            'opt' => [
                'mode' => 'Foo'
            ]
        ];
    }

    public function findFirst()
    {
        $this->set([]);
    }
}
