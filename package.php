<?php

// add all files in the project
$dir = dirname(__FILE__);

if (!file_exists('build')) {
    echo "Creating build/\n";
    mkdir('build') || die('unable to ensure build/ directory exists');
}
// create with alias "project.phar"
$phar = new Phar('build/phan.phar', 0, 'phan.phar');

$phar->buildFromDirectory($dir . '/', '/\.php$/');
$phar->setStub("#!/usr/bin/env php\n" . $phar->createDefaultStub('src/phan.php'));
echo "Created phar in build/phan.phar\n";
