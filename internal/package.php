<?php

// add all files in the project
$dir = dirname(__DIR__);
chdir($dir);

if (!file_exists('build')) {
    echo "Creating build/\n";
    mkdir('build') || die('unable to ensure build/ directory exists');
}
// create with alias "project.phar"
$phar = new Phar('build/phan.phar', 0, 'phan.phar');

$iterators = new AppendIterator();
foreach (['src', 'vendor', '.phan'] as $subdir) {
    $iterators->append(
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $subdir,
                RecursiveDirectoryIterator::FOLLOW_SYMLINKS
            )
        )
    );
}

// Include all files with suffix .php, excluding those found in the tests folder.
$iterator = new CallbackFilterIterator(
    $iterators,
    function (\SplFileInfo $file_info) : bool {
        if ($file_info->getExtension() !== 'php') {
            return false;
        }
        if (preg_match('@^vendor/symfony/console/Tests/@i', str_replace('\\', '/', $file_info->getPathname()))) {
            return false;
        }
        return true;
    }
);
$phar->buildFromIterator($iterator, $dir);

$phar->setStub("#!/usr/bin/env php\n" . $phar->createDefaultStub('src/phan.php'));
echo "Created phar in build/phan.phar\n";
