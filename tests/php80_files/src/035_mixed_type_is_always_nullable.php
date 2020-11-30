<?php
declare(strict_types=1);

class C35{
	/** @var array */
	private $decodedJson;

	public function decodeJson(){
		$this->decodedJson = json_decode('{"this:"is not valid json"', true) ?? [];
	}
}
