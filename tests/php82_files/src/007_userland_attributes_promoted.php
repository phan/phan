<?php

#[Attribute(Attribute::TARGET_PROPERTY)]
class MyPropAttribute {}

#[Attribute(Attribute::TARGET_PARAMETER)]
class MyParamAttribute {}

class Demo {
	public function __construct(
		#[MyPropAttribute]
		#[MyParamAttribute]
		public string $password
	) {}

}

$d = new Demo("xyz");
var_dump($d->password);
