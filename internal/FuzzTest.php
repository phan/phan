<?php
declare(strict_types=1);

/**
 * Utilities to fuzz test Phan when tokens are missing
 */
class FuzzTest
{
    /** @var string */
    private static $basename;

    /**
     * @return array<string,string>
     */
    private static function readFileContents(string $basename) : array
    {
        $files = glob("$basename/*.php");
        $result = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if (!is_string($contents)) {
                throw new RuntimeException("Failed to read $file");
            }
            $result[$file] = $contents;
        }
        return $result;
    }

    /**
     * @param array<int,array|string> $tokens
     * @return ?array<int,array|string>
     */
    private static function mutateTokens(string $path, array $tokens, int $i)
    {
        $N = count($tokens);
        if ($i >= $N) {
            return null;
        }
        $j = ($i + crc32($path)) % count($tokens);
        unset($tokens[$j]);
        return array_values($tokens);
    }

    /**
     * @return void
     */
    public static function main()
    {
        self::$basename = dirname(realpath(__DIR__));
        $fileContents = self::readFileContents(self::$basename . '/tests/files/src');
        $fileTokens = array_map('token_get_all', $fileContents);
        for ($i = 0; true; $i++) {
            $newFileTokens = [];
            foreach ($fileTokens as $path => $tokens) {
                $newTokens = self::mutateTokens($path, $tokens, $i);
                if ($newTokens) {
                    $newFileTokens[$path] = $newTokens;
                }
            }
            if (!$newFileTokens) {
                // No mutations left to analyze
                return;
            }

            self::analyzeTemporaryDirectory($i, $newFileTokens);
        }
    }

    private static function tokensToString(array $tokens) : string
    {
        $result = '';
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $result .= $token[1];
            } else {
                $result .= $token;
            }
        }
        return $result;
    }

    /**
     * @return void
     */
    private static function analyzeTemporaryDirectory(int $i, array $newFileTokens)
    {
        $tmpDir = self::$basename . "/tmp/mutate$i";
        mkdir("$tmpDir/.phan", 0766, true);
        mkdir("$tmpDir/src", 0766, true);
        file_put_contents("$tmpDir/.phan/config.php", <<<'EOT'
<?php
return [
    'directory_list' => ['src'],

    'check_docblock_signature_return_type_match' => true,

    'check_docblock_signature_param_type_match' => true,

    'prefer_narrowed_phpdoc_param_type' => true,

    'unused_variable_detection' => true,
    'plugins' => [
        'AlwaysReturnPlugin',
        'DemoPlugin',
        'DollarDollarPlugin',
        'UnreachableCodePlugin',
        'DuplicateArrayKeyPlugin',
        'PregRegexCheckerPlugin',
        'PrintfCheckerPlugin',
        'UnknownElementTypePlugin',
        'DuplicateExpressionPlugin',
        'NoAssertPlugin',
        'HasPHPDocPlugin',
    ],
];
EOT
        );

        foreach ($newFileTokens as $file => $tokens) {
            $contents = self::tokensToString($tokens);
            $tmpPath = $tmpDir . '/src/' . basename($file);
            file_put_contents($tmpPath, $contents);
        }

        // TODO: Use proc_open
        $cmd = self::$basename . '/phan --use-fallback-parser --project-root-directory ' . $tmpDir;
        echo "Running $cmd\n";
        system($cmd);
    }
}
FuzzTest::main();
