<?php

#[Attribute(Attribute::TARGET_PROPERTY)]
class MyPropAttribute {}

#[Attribute(Attribute::TARGET_PARAMETER)]
class MyParamAttribute {}

class Demo {
	
	#[MyPropAttribute]
	#[MyParamAttribute]
	#[SensitiveParameter]
	#[InternalAttribForProperties]
	public string $password;

	public function __construct(
		#[MyPropAttribute]
		#[MyParamAttribute]
		#[SensitiveParameter]
		#[InternalAttribForProperties]
		string $password
	) {
		$this->password = $password;
	}

}

$d = new Demo("xyz");
var_dump($d->password);
