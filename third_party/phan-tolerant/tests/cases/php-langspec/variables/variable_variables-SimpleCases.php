/* Auto-generated from php/php-langspec tests */
<?php

$color = "red";
echo "\$color = $color\n";

$$color = 123;			// 2 consecutive $s
echo "\$red = $red\n";	// ==> $red = 123
var_dump($$color);

