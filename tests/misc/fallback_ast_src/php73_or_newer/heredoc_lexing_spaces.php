<?php
$a = 'str';
$a = <<<"E"
$a
E;
$a = <<<"E"
$a\x32
E;
$a = <<<'E'
$zz\x32
E;
$a = <<<E
$zy\x32
E;
$a = <<<E
plain\x32
E;
$a = <<<'E'
plain\x32
E;
