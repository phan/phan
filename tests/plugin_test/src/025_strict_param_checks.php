<?php

$maybeFalse = rand(0,2) ? 's' : false;
echo strlen($maybeFalse);
$maybeNull = rand(0,8) ?: null;
echo intdiv($maybeNull, 2);
$maybeInvalid = rand(0,8) ?: 'default';
echo intdiv($maybeInvalid, 2);
