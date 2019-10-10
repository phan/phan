<?php declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\TypedElement;
use Phan\Language\Element\UnaddressableTypedElement;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Suggestion;

/**
 * Plugins can implement this to suppress issues in additional ways.
 *
 * @see \Phan\Plugin\Internal\BuiltinSuppressionPlugin for an example of how to implement a plugin with this functionality
 */
interface SuppressionCapability
{
    /**
     * This will be called if both of these conditions hold:
     *
     * 1. Phan's file and element-based suppressions did not suppress the issue
     * 2. Earlier plugins didn't suppress the issue.
     *
     * To get the file's current contents, the recommended method is:
     *
     *     $absolute_file_path = Config::projectPath($context->getFile());
     *     $file_contents = \Phan\Library\FileCache::getOrReadEntry($absolute_file_path)->getContents()
     *
     * @param CodeBase $code_base
     *
     * @param Context $context context near where the issue occurred
     *
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $lineno
     * The line number where the issue was found
     *
     * @param list<string|int|float|bool|Type|UnionType|FQSEN|TypedElement|UnaddressableTypedElement> $parameters
     *
     * @param ?Suggestion $suggestion Phan's suggestion for how to fix the issue, if any.
     *
     * @return bool true if the given issue instance should be suppressed, given the current file contents.
     */
    public function shouldSuppressIssue(
        CodeBase $code_base,
        Context $context,
        string $issue_type,
        int $lineno,
        array $parameters,
        ?Suggestion $suggestion
    ) : bool;

    /**
     * This method is used only by UnusedSuppressionPlugin.
     * It's optional to return lines for issues that were already suppressed.
     *
     * To get the file's current contents, the recommended method is:
     * $file_contents = \Phan\Library\FileCache::getOrReadEntry(Config::projectPath($file_path))->getContents()
     *
     * @param CodeBase $code_base
     *
     * @param string $file_path the file to check for suppressions of
     *
     * @return array<string,list<int>> Maps 0 or more issue types to a *map* of lines that this plugin is going to suppress.
     * The keys of the map are the lines being suppressed, and the values are the lines *causing* the suppressions (if extracted from comments or nodes)
     *
     * An empty array can be returned if this is unknown.
     */
    public function getIssueSuppressionList(
        CodeBase $code_base,
        string $file_path
    ) : array;
}
