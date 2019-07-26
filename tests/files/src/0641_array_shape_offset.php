<?php

abstract class PhanUndeclaredClassFailure
{
	private $fields = array();
	public function set($data, $value = null)
	{
        echo strlen($this->fields);
		$class = $this->fields[0]['opt']['class'];
		$test = new $class();
	}

	public function addField($field)
	{
		$this->fields[$field] = [
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
