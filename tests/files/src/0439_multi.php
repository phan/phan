<?php
$x = ['first' => 2];
[$a, $x['offset']] = [2, 'value'];
echo $x['otherOffset'];
