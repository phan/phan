<?php
$x = rand(0,1) ? '10' : false;
echo strlen($x);
$x && false;
echo strlen($x);
