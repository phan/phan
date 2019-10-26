<?php declare(strict_types=1);

namespace Phan\Plugin\Internal;

use Phan\CLI;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\TypedElement;
use Phan\Language\Element\UnaddressableTypedElement;
use Phan\Language\FQSEN;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\SuppressionCapability;
use Phan\Suggestion;

/**
 * Suppresses issues based on the contents of a baseline file.
 *
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 */
final class BaselineLoadingPlugin extends PluginV3 implements
    SuppressionCapability
{
    /**
     * @var array<string,list<string>>
     * Maps relative file paths to a list of issue kinds that are suppressed everywhere in the file by the baseline.
     */
    private $file_suppressions = [];

    public function __construct(string $baseline_path)
    {
        // Evaluate the php file with the baseline contents.
        // Other file formats or a safe evaluation may be implemented later.
        $baseline = require($baseline_path);
        if (!\is_array($baseline)) {
            CLI::printWarningToStderr("Phan read an invalid baseline from '$baseline_path' : Expected it to return an array, got " . \gettype($baseline_path) . "\n");
            return;
        }
        if (!\array_key_exists('file_suppressions', $baseline)) {
            CLI::printWarningToStderr("Phan read an invalid baseline from '$baseline_path' : Expected the returned array to contain the key 'file_suppressions' (new baselines can be generated with --save-baseline)\n");
            return;
        }
        // file_suppressions is currently the only way to suppress issues in a baseline. Other ways may be added later.
        $this->file_suppressions = $baseline['file_suppressions'];
    }

    /**
     * This will be called if both of these conditions hold:
     *
     * 1. Phan's file and element-based suppressions did not suppress the issue
     * 2. Earlier plugins didn't suppress the issue.
     *
     * @param CodeBase $code_base @phan-unused-param
     *
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $lineno @phan-unused-param
     * The line number where the issue was found
     *
     * @param list<string|int|float|bool|Type|UnionType|FQSEN|TypedElement|UnaddressableTypedElement> $parameters @phan-unused-param
     *
     * @param ?Suggestion $suggestion @phan-unused-param
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
    ) : bool {
        return \in_array($issue_type, $this->file_suppressions[$context->getFile()] ?? [], true);
    }

    /**
     * @return array{} the baseline plugin is not meant for use with UnusedSuppressionPlugin.
     *
     * This helper method is externally used only by UnusedSuppressionPlugin
     * @override
     */
    public function getIssueSuppressionList(
        CodeBase $unused_code_base,
        string $unused_file_path
    ) : array {
        return [];
    }
}
