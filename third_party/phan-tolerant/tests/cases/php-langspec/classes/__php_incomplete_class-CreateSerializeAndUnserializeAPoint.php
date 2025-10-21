/* Auto-generated from php/php-langspec tests */
<?php

$p = new Point(2, 5);
echo "Point \$p = $p\n";

$s = serialize($p);		// all instance properties get serialized
var_dump($s);

echo "------\n";

$v = unserialize($s);	// without a __wakeup method, any instance property present
						// in the string takes on its default value.
var_dump($v);

$s[5] = 'J';		// change class name, so a unserialize failure occurs
var_dump($s);
$v = unserialize($s);
var_dump($v);
print_r($v);
