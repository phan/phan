<?php

CONST PHAR_FILENAME = 'phan.phar';
CONST BUILD_PATH = 'build';

// add all files in the project
$dir = __DIR__;

// create with alias "project.phar"
$phar = new Phar($dir . DIRECTORY_SEPARATOR . BUILD_PATH . DIRECTORY_SEPARATOR . PHAR_FILENAME, 0, 'phan.phar');

$phar->buildFromDirectory($dir . '/', '/\.php$/');
$phar->addFile(
    $dir . '/src/Phan/Resources/config/default_config.yml',
    'src/Phan/Resources/config/default_config.yml'
);
$phar->setStub($phar::createDefaultStub('src/phan.php'));
