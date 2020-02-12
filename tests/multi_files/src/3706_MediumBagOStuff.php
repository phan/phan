<?php

abstract class MediumSpecificBagOStuff extends BagOStuff {
	/** @var int ERR_* class constant */
	protected $lastError = self::ERR_NONE;
}
// Should have inferred the value to be an int
echo count(MediumSpecificBagOStuff::ERR_NONE);
