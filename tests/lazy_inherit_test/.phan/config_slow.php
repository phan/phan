<?php

declare(strict_types=1);

/**
 * The quick_mode=false variant of the suite (see test.sh): the case of the inherited methods of untyped roots,
 * whose return type link is relaxed, analyzed with the re-analysis of methods for each call.
 */
$config = require __DIR__ . '/config.php';
$config['quick_mode'] = false;
$config['directory_list'] = ['external', 'src_slow'];
return $config;
