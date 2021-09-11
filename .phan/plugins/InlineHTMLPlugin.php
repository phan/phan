<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\Parser;
use Phan\CLI;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Library\StringUtil;
use Phan\PluginV3;
use Phan\PluginV3\AfterAnalyzeFileCapability;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;
use Phan\PluginV3\UnloadablePluginException;

/**
 * This plugin checks for accidental whitespace in regular php files.
 * Note that this is slow due to needing token_get_all.
 *
 * TODO: Cache and reuse the results
 */
class InlineHTMLPlugin extends PluginV3 implements
    AfterAnalyzeFileCapability,
    PostAnalyzeNodeCapability
{
    private const InlineHTML = 'PhanPluginInlineHTML';
    private const InlineHTMLLeading = 'PhanPluginInlineHTMLLeading';
    private const InlineHTMLTrailing = 'PhanPluginInlineHTMLTrailing';

    /** @var array<string,true> set of files that have echo statements */
    public static $file_set_to_analyze = [];

    /** @var ?string */
    private $whitelist_regex;
    /** @var ?string */
    private $blacklist_regex;

    public function __construct()
    {
        $plugin_config = Config::getValue('plugin_config');
        $this->whitelist_regex = $plugin_config['inline_html_whitelist_regex'] ?? null;
        $this->blacklist_regex = $plugin_config['inline_html_blacklist_regex'] ?? null;
    }

    private function shouldCheckFile(string $path): bool
    {
        if (is_string($this->blacklist_regex)) {
            if (CLI::isPathMatchedByRegex($this->blacklist_regex, $path)) {
                return false;
            }
        }
        if (is_string($this->whitelist_regex)) {
            return CLI::isPathMatchedByRegex($this->whitelist_regex, $path);
        }
        return true;
    }

    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context @phan-unused-param
     * A context with the file name for $file_contents and the scope after analyzing $node.
     *
     * @param string $file_contents the unmodified file contents @phan-unused-param
     * @param Node $node the node @phan-unused-param
     * @override
     * @throws Error if a process fails to shut down
     */
    public function afterAnalyzeFile(
        CodeBase $code_base,
        Context $context,
        string $file_contents,
        Node $node
    ): void {
        $file = $context->getFile();
        if (!isset(self::$file_set_to_analyze[$file])) {
            // token_get_all is noticeably slow when there are a lot of files, so we check for the existence of echo statements in the parsed AST as a heuristic to avoid calling token_get_all.
            return;
        }
        if (!self::shouldCheckFile($file)) {
            return;
        }
        $file_contents = Parser::removeShebang($file_contents);
        $tokens = token_get_all($file_contents);
        foreach ($tokens as $i => $token) {
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] !== T_INLINE_HTML) {
                continue;
            }
            $N = count($tokens);
            $this->warnAboutInlineHTML($code_base, $context, $token, $i, $N);
            if ($i < $N - 1) {
                // Make sure to always check if the last token is inline HTML
                $token = $tokens[$N - 1] ?? null;
                if (!is_array($token)) {
                    break;
                }
                if ($token[0] !== T_INLINE_HTML) {
                    break;
                }
                $this->warnAboutInlineHTML($code_base, $context, $token, $N - 1, $N);
            }
            break;
        }
    }

    /**
     * @param array{0:int,1:string,2:int} $token a token from token_get_all
     */
    private function warnAboutInlineHTML(CodeBase $code_base, Context $context, array $token, int $i, int $n): void
    {
        if ($i === 0) {
            $issue = self::InlineHTMLLeading;
            $message = 'Saw inline HTML at the start of the file: {STRING_LITERAL}';
        } elseif ($i >= $n - 1) {
            $issue = self::InlineHTMLTrailing;
            $message = 'Saw inline HTML at the end of the file: {STRING_LITERAL}';
        } else {
            $issue = self::InlineHTML;
            $message = 'Saw inline HTML between the first and last token: {STRING_LITERAL}';
        }
        $this->emitIssue(
            $code_base,
            (clone $context)->withLineNumberStart($token[2]),
            $issue,
            $message,
            [StringUtil::jsonEncode(self::truncate($token[1]))]
        );
    }

    private static function truncate(string $token): string
    {
        if (strlen($token) > 20) {
            return mb_substr($token, 0, 20) . "...";
        }
        return $token;
    }

    /**
     * @return string - name of PluginAwarePostAnalysisVisitor subclass
     *
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return InlineHTMLVisitor::class;
    }
}

/**
 * Records existence of AST_ECHO within a file, marking the file as one that should be checked.
 *
 * php-ast (and the underlying AST implementation) doesn't provide a way to distinguish inline HTML from other types of echos.
 */
class InlineHTMLVisitor extends PluginAwarePostAnalysisVisitor
{
    /**
     * @override
     * @param Node $node @unused-param
     * @return void
     */
    public function visitEcho(Node $node)
    {
        InlineHTMLPlugin::$file_set_to_analyze[$this->context->getFile()] = true;
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
if (!function_exists('token_get_all')) {
    throw new UnloadablePluginException("InlineHTMLPlugin requires the tokenizer extension, which is not enabled (this plugin uses token_get_all())");
}
return new InlineHTMLPlugin();
