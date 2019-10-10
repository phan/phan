<?php declare(strict_types=1);

namespace Phan\PluginV2;

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
 * @deprecated Use PluginV3 instead.
 */
interface SuppressionCapability
{
    /**
     * @param list<string|int|float|bool|Type|UnionType|FQSEN|TypedElement|UnaddressableTypedElement> $parameters
     *
     * @param ?Suggestion $suggestion Phan's suggestion for how to fix the issue, if any.
     *
     * @return bool true if the given issue instance should be suppressed, given the current file contents.
     * @deprecated use PluginV3
     * @suppress PhanPluginCanUseNullableParamType provided for legacy PluginV2 plugins
     */
    public function shouldSuppressIssue(
        CodeBase $code_base,
        Context $context,
        string $issue_type,
        int $lineno,
        array $parameters,
        $suggestion
    ) : bool;
}
