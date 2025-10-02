<?php

#[Attribute(Attribute::TARGET_PARAMETER)]
class MyParamAttrib {}

/**
 * @property string $writtenViaPropertyPromotion
 * @property string $writtenManually
 */
class Demo51 {

	public function __construct(
		#[MyParamAttrib]
		public string $writtenViaPropertyPromotion,
		string $writtenManually
	) {
		$this->writtenManually = $writtenManually;
	}
}

$d = new Demo51("foo", "bar");
var_dump($d->writtenViaPropertyPromotion, $d->writtenManually);
