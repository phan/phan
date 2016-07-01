<?php

// add all files in the project
$dir = dirname(__FILE__);

// create with alias "project.phar"
$phar = new Phar('build/phan.phar', 0, 'phan.phar');

$phar->buildFromDirectory($dir . '/', '/\.php$/');
$phar->setStub("#!/usr/bin/env php\n" . $phar->createDefaultStub('src/phan.php'));
