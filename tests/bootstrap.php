<?php
declare(strict_types=1);

use Phan\AST\TolerantASTConverter\Shim;

// Test output of failures is shorter with relative paths than absolute paths
const RASMUS_TEST_FILE_DIR = './tests/rasmus_files/src';
const RASMUS_EXPECTED_DIR = './tests/rasmus_files/expected';
const AST_TEST_FILE_DIR = './tests/misc/ast/src';
const AST_EXPECTED_DIR = './tests/misc/ast/expected';
const TEST_FILE_DIR = './tests/files/src';
const EXPECTED_DIR = './tests/files/expected';
const MULTI_FILE_DIR = './tests/multi_files/src';
const MULTI_EXPECTED_DIR = './tests/multi_files/expected';
const SOAP_TEST_FILE_DIR = './tests/misc/soap_files/src';
const SOAP_EXPECTED_DIR = './tests/misc/soap_files/expected';
const INTL_TEST_FILE_DIR = './tests/misc/intl_files/src';
const INTL_EXPECTED_DIR = './tests/misc/intl_files/expected';
const PHP82_TEST_FILE_DIR = './tests/php82_files/src';
const PHP82_EXPECTED_DIR = './tests/php82_files/expected';
const PHP83_TEST_FILE_DIR = './tests/php83_files/src';
const PHP83_EXPECTED_DIR = './tests/php83_files/expected';
const PHP84_TEST_FILE_DIR = './tests/php84_files/src';
const PHP84_EXPECTED_DIR = './tests/php84_files/expected';
const PHP85_TEST_FILE_DIR = './tests/php85_files/src';
const PHP85_EXPECTED_DIR = './tests/php85_files/expected';
const ASYMMETRIC_VISIBILITY_TEST_FILE_DIR = './tests/asymmetric_visibility/src';

require_once dirname(__DIR__) . '/src/Phan/Bootstrap.php';

// Need to declare newer constants such as PARAM_MODIFIER_PUBLIC when running some tests
Shim::load();
