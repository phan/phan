<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

return array_merge($config, [
    'dead_code_detection' => true,
    'use_project_composer_autoloader' => true,
//    'analyze_autoloaded_files' => true,
    'file_list' => [
//        'tests/Autoload/A.php',
//        'tests/Autoload/B.php',
//        'tests/Autoload/C.php',
//        'tests/Autoload/D.php',
//        'tests/Autoload/E.php',
//        'tests/Autoload/F.php',
//        'tests/Autoload/G.php',
//        'tests/Autoload/H.php',
//        'tests/Autoload/InterfaceA.php',
//        'tests/Autoload/InterfaceB.php',
    ],
    'directory_list' => [
//        'tests/Autoload',
//        'vendor/phpunit/phpunit/src/Framework',
    ],
    'exclude_analysis_directory_list' => [
        'vendor/',
        'tests/Autoload/Foo',
//        'tests/Autoload/Foo'
    ],
]);
