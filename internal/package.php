<?php
declare(strict_types = 1);

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
$phar->addFile('LICENSE');
$phar->addFile('LICENSE.LANGUAGE_SERVER');
$phar->addFile('LICENSE.PHP_PARSER');
foreach ($phar as $file) {
    echo $file->getFileName() . "\n";
}

// We don't want to use https://secure.php.net/manual/en/phar.interceptfilefuncs.php , which Phar does by default.
// That causes annoying bugs.
// Also, phan.phar is has no use cases to use as a web server, so don't include that, either.
// See https://github.com/composer/xdebug-handler/issues/46 and https://secure.php.net/manual/en/phar.createdefaultstub.php
$stub = <<<'EOT'
#!/usr/bin/env php
<?php

Phar::mapPhar('phan.phar');

require 'phar://phan.phar/src/phan.php';

__HALT_COMPILER();
EOT;
$phar->setStub($stub);

echo "Created phar in build/phan.phar\n";
