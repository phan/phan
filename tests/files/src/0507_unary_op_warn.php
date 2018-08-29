<?php
$x = ~false;
$y = ~true;
$x = ~null;
$x = ~'ab'; // should not warn.
$x = ~[];
$y = ~(rand(0,2) > 0);
$x = +false;
$y = +true;
$x = +null;
$y = +(rand(0,2) > 0);
$x = +[];
$x = -false;
$y = -true;
$y = -(rand(0,2) > 0);
$x = -null;
$x = -[];
