<?php

$foo = new stdClass();
preg_match('/something/', 'something else', $foo->{'a' . 'Value741'});
echo strlen($foo->aValue741);
