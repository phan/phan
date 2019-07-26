<?php declare(strict_types=1);

use ast\Node;
use Phan\CodeBase;
use Phan\IssueInstance;
use Phan\Language\Context;
use Phan\Library\FileCacheEntry;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEditSet;
use Phan\PluginV3;
use Phan\PluginV3\AfterAnalyzeFileCapability;
use Phan\PluginV3\AutomaticFixCapability;

/**
 * This plugin checks the whitespace in analyzed PHP files for (1) tabs, (2) windows newlines, and (3) trailing whitespace.
 */
class WhitespacePlugin extends PluginV3 implements
    AfterAnalyzeFileCapability,
    AutomaticFixCapability
{
    const CarriageReturn = 'PhanPluginWhitespaceCarriageReturn';
    const Tab = 'PhanPluginWhitespaceTab';
    const WhitespaceTrailing = 'PhanPluginWhitespaceTrailing';

    private static function calculateLine(string $contents, int $byte_offset) : int
    {
        return 1 + substr_count($contents, "\n", 0, $byte_offset);
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
    ) : void {
        if (!preg_match('/[\r\t]|[ \t]\r?$/m', $file_contents)) {
            // Typical case: no errors
            return;
        }
        $newline_position = strpos($file_contents, "\r");
        if ($newline_position !== false) {
            self::emitIssue(
                $code_base,
                clone($context)->withLineNumberStart(self::calculateLine($file_contents, $newline_position)),
                self::CarriageReturn,
                'The first occurrence of a carriage return ("\r") was seen here. Running "dos2unix" can fix that.'
            );
        }
        $tab_position = strpos($file_contents, "\t");
        if ($tab_position !== false) {
            self::emitIssue(
                $code_base,
                clone($context)->withLineNumberStart(self::calculateLine($file_contents, $tab_position)),
                self::Tab,
                'The first occurrence of a tab was seen here. Running "expand" can fix that.'
            );
        }
        if (preg_match('/[ \t]\r?$/m', $file_contents, $match, PREG_OFFSET_CAPTURE)) {
            self::emitIssue(
                $code_base,
                clone($context)->withLineNumberStart(self::calculateLine($file_contents, $match[0][1])),
                self::WhitespaceTrailing,
                'The first occurrence of trailing whitespace was seen here.'
            );
        }
    }

    /**
     * @return array<string,Closure(CodeBase,FileCacheEntry,IssueInstance):(?FileEditSet)>
     */
    public function getAutomaticFixers() : array
    {
        return require(__DIR__ . '/WhitespacePlugin/fixers.php');
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new WhitespacePlugin();
