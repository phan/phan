<?php

return [
    'target_php_version' => '8.1',
    'dead_code_detection' => true,
    'plugins' => [
        'CaseMismatchPlugin',
        'UseReturnValuePlugin',
    ],
    'directory_list' => ['src'],
    'analyzed_file_extensions' => ['php'],
];
