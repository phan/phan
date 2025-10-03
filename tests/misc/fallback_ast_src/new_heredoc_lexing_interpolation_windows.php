<?php
$a = 2;
$b = 3;
$Cx = 5;
$z = <<<EOT
Foo $a
$b$Cx
EOT;

$a = <<<"EOT"
  this is a string literal with no space before EOT
 one space
nesting $z
EOT;

$a = <<<"EOT"
		  this is a string literal with only tabs before EOT
		 one space
		nesting $z

		EOT;
$a = <<<"E"
  $z  this is a string literal with only spaces before EOT
   one space
  nesting $z

  E;
$a = <<<"E"
  E;
$a = <<<"E"
E;
