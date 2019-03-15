#!/usr/bin/env php
<?php declare(strict_types=1);

// @phan-file-suppress PhanNativePHPSyntaxCheckPlugin caused by inline HTML before declare

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/lib/IncompatibleXMLSignatureDetector.php';

IncompatibleXMLSignatureDetector::main();
