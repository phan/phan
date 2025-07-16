<?php

class Demo {
	public function __construct(
		#[SensitiveParameter]
		#[InternalAttribForProperties]
		public string $password
	) {}

}

$d = new Demo("xyz");
var_dump($d->password);
