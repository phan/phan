<?php

class OtherClass {}

/** @param OtherClass|mixed $o */
function testObjectOrMixed( $o ) {
	$o();
	new $o( 123 );
}
