/* Auto-generated from php/php-langspec tests */
<?php

$cp = new ColoredPoint(9, 8, ColoredPoint::BLUE);
echo "ColoredPoint \$cp = $cp\n";

$s = serialize($cp);
var_dump($s);

$v = unserialize($s);
var_dump($v);

