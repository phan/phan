<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Microsoft\PhpParser\DiagnosticsProvider;
use Microsoft\PhpParser\Parser;

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/PrintTolerantAst.php <file.php>\n");
    exit(1);
}

$file = $argv[1];
if (!is_file($file)) {
    fwrite(STDERR, "File not found: {$file}\n");
    exit(1);
}

$code = file_get_contents($file);
$parser = new Parser();
$node = $parser->parseSourceFile($code);

fwrite(STDOUT, "===== AST =====\n");
echo json_encode($node, JSON_PRETTY_PRINT) ?: '';
fwrite(STDOUT, "\n\n===== Diagnostics =====\n");
echo json_encode(DiagnosticsProvider::getDiagnostics($node), JSON_PRETTY_PRINT) ?: '';
fwrite(STDOUT, "\n");
