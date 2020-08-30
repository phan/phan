<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use AssertionError;
use ast\Node;
use Error;
use Microsoft\PhpParser;
use Microsoft\PhpParser\Node\SourceFileNode;
use Microsoft\PhpParser\Token;
use Phan\CLI;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Library\FileCache;
use Phan\Library\Paths;
use Phan\Plugin\Internal\PhantasmPlugin\PhantasmVisitor;
use Phan\PluginV3;
use Phan\PluginV3\AfterAnalyzeFileCapability;
use Phan\PluginV3\FinalizeProcessCapability;
use Phan\PluginV3\PostAnalyzeNodeCapability;

use function dirname;
use function file_put_contents;
use function gettype;
use function is_dir;
use function is_object;
use function is_string;
use function mkdir;
use function token_get_all;

use const PHP_INT_MAX;
use const TOKEN_PARSE;

/**
 * This internal plugin implements tool/phantasm, to aggressively simplify and minify code.
 *
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * @internal
 */
final class PhantasmPlugin extends PluginV3 implements
    PostAnalyzeNodeCapability,
    AfterAnalyzeFileCapability,
    FinalizeProcessCapability
{
    /** @var array<string, string> maps file paths to new contents to save */
    private $deferred_writes = [];

    public function __construct()
    {
        FileCache::raiseMaxCacheSize(PHP_INT_MAX);
    }

    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return PhantasmVisitor::class;
    }

    /**
     * Convert a php-parser node with modifications back to a string
     *
     * @param PhpParser\Node|Token|string $token
     * @suppress PhanUndeclaredProperty deliberately using dynamic properties
     */
    public static function convertToString($token, string $file_contents): string
    {
        if (is_object($token)) {
            if (isset($token->string_replacement)) {
                return $token->string_replacement;
            }
            if ($token instanceof PhpParser\Node) {
                $result = '';
                foreach ($token->getChildNodesAndTokens() as $child_node) {
                    $result .= self::convertToString($child_node, $file_contents);
                }
                return $result;
            } elseif ($token instanceof Token) {
                // @phan-suppress-next-line PhanPartialTypeMismatchReturn
                $token_text = $token->getFullText($file_contents);
                // echo "token_text='''$token_text'''\n";
                return $token_text;
            }
        } elseif (is_string($token)) {
            return $token;
        }
        throw new AssertionError("Unexpected node type " . gettype($token));
    }

    /**
     * @unused-param $code_base
     * @suppress PhanUndeclaredProperty
     */
    public function afterAnalyzeFile(CodeBase $code_base, Context $context, string $file_contents, Node $node): void
    {
        $path = $context->getFile();
        if (Paths::isAbsolutePath($path) ||  \strpos($path, '../') !== false) {
            CLI::printToStderr("phantasm: Skipping '$path': Only modifying files within the project directory\n");
            return;
        }
        $error = self::getParseError($file_contents);
        if (is_string($error)) {
            CLI::printToStderr("phantasm: Skipping '$path': Parse error: $error");
            return;
        }
        $tolerant_ast_node = $node->tolerant_ast_node ?? null;
        if (!$tolerant_ast_node instanceof SourceFileNode) {
            return;
        }
        $new_file_contents = self::convertToString($tolerant_ast_node, $file_contents);
        $new_error = self::getParseError($file_contents);
        if (is_string($new_error)) {
            CLI::printToStderr("phantasm: Skipping '$path': After optimizing the file, got parse error: $new_error (this should not happen)\nFile contents:\n'''\n$new_file_contents\n'''\n");
            return;
        }
        $phantasm_output_directory = $_ENV['PHANTASM_OUTPUT_DIRECTORY'] ?? null;
        // Always defer saving the new file contents, because Phan or its plugins may read the files from source
        // or pick up newly created directories during analysis.
        // (And because fatal errors would result in saving some but not all files)
        if (isset($phantasm_output_directory)) {
            $output_file = "$phantasm_output_directory/$path";
            $this->deferred_writes[$output_file] = $new_file_contents;
        } elseif ($_ENV['PHANTASM_MODIFY_IN_PLACE'] ?? false) {
            $this->deferred_writes[$path] = $new_file_contents;
        } else {
            throw new AssertionError("Should either pass --output-directory or --in-place to tool/phantasm");
        }
    }

    /**
     * @suppress PhanPluginUseReturnValueInternalKnown this is called for the error thrown
     */
    private static function getParseError(string $file_contents): ?string
    {
        try {
            token_get_all($file_contents, TOKEN_PARSE);
            return null;
        } catch (Error $e) {
            return $e->getMessage();
        }
    }

    /**
     * @unused-param $code_base
     */
    public function finalizeProcess(CodeBase $code_base): void
    {
        foreach ($this->deferred_writes as $output_file => $new_file_contents) {
            $output_file = (string)$output_file;
            $output_dir = dirname($output_file);
            if (!is_dir($output_dir)) {
                CLI::printToStderr("phantasm: Creating '$output_dir/'\n");
                mkdir($output_dir, 0777, true);
            }
            CLI::printToStderr("phantasm: Saving optimized contents to '$output_file'\n");
            file_put_contents($output_file, $new_file_contents);
        }
    }
}

return new PhantasmPlugin();
