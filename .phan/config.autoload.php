<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

return array_merge($config, [
    'use_project_composer_autoloader' => true,
    'file_list' => [],
    'directory_list' => [],
    'exclude_analysis_directory_list' => [
        'vendor/',
    ],
]);
