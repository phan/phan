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
            if (stripos($file, '0493_') !== false) {
                // TODO: Fix https://github.com/phan/phan/issues/1988
                continue;
            }
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
        if ($i >= count($tokens)) {
            return null;
        }
        $j = ($i + crc32($path) + 11155) % count($tokens);
        unset($tokens[$j]);
        unset($tokens[$j + 1]);
        unset($tokens[$j + 2]);
        unset($tokens[$j + 3]);
        return array_values($tokens);
    }

    /**
     * @return void
     */
    public static function main()
    {
        self::$basename = dirname(realpath(__DIR__));
        $file_contents = self::readFileContents(self::$basename . '/tests/files/src');
        $tokens_for_files = array_map('token_get_all', $file_contents);
        for ($i = 0; true; $i++) {
            $new_tokens_for_files = [];
            foreach ($tokens_for_files as $path => $tokens) {
                $new_tokens = self::mutateTokens($path, $tokens, $i);
                if ($new_tokens) {
                    $new_tokens_for_files[$path] = $new_tokens;
                }
            }
            if (!$new_tokens_for_files) {
                // No mutations left to analyze
                return;
            }

            self::analyzeTemporaryDirectory($i, $new_tokens_for_files);
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
    private static function analyzeTemporaryDirectory(int $i, array $new_tokens_for_files)
    {
        $tmp_dir = self::$basename . "/tmp/mutate$i";
        mkdir("$tmp_dir/.phan", 0766, true);
        mkdir("$tmp_dir/src", 0766, true);
        file_put_contents("$tmp_dir/.phan/config.php", <<<'EOT'
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

        foreach ($new_tokens_for_files as $file => $tokens) {
            $contents = self::tokensToString($tokens);
            $tmp_path = $tmp_dir . '/src/' . basename($file);
            file_put_contents($tmp_path, $contents);
        }

        // TODO: Use proc_open
        $cmd = self::$basename . '/phan --use-fallback-parser --project-root-directory ' . $tmp_dir;
        echo "Running $cmd\n";
        system($cmd);
    }
}
FuzzTest::main();
