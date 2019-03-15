<?php

$x = <<<EOT
    this is some string literal
    EOT;
echo $x;
$y = <<<'EOT'
		this is a string literal with tabs
		more tabs
		EOT;

$y = <<<"EOT"
	  this is a string literal with only tabs before EOT
	 one space
	EOT;
echo $y;
