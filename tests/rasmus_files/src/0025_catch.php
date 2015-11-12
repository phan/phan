<?php
function test() {
	try {
		throw new Ex("test");
	} catch(Exceptio $e) {
		echo $e->getMessage();
		print_r($e);
	}
}

test();
