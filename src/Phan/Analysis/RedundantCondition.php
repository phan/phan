<?php declare(strict_types=1);

namespace Phan\Analysis;

use ast\Node;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Parse\ParseVisitor;

/**
 * Contains miscellaneous utilities for warning about redundant and impossible conditions
 */
class RedundantCondition
{
    private const LOOP_ISSUE_NAMES = [
        Issue::RedundantCondition       => Issue::RedundantConditionInLoop,
        Issue::ImpossibleCondition      => Issue::ImpossibleConditionInLoop,
        Issue::ImpossibleTypeComparison => Issue::ImpossibleTypeComparisonInLoop,
        Issue::CoalescingNeverNull      => Issue::CoalescingNeverNullInLoop,
        Issue::CoalescingAlwaysNull     => Issue::CoalescingAlwaysNullInLoop,
    ];

    private const GLOBAL_ISSUE_NAMES = [
        Issue::RedundantCondition       => Issue::RedundantConditionInGlobalScope,
        Issue::ImpossibleCondition      => Issue::ImpossibleConditionInGlobalScope,
        Issue::ImpossibleTypeComparison => Issue::ImpossibleTypeComparisonInGlobalScope,
        Issue::CoalescingNeverNull      => Issue::CoalescingNeverNullInGlobalScope,
        Issue::CoalescingAlwaysNull     => Issue::CoalescingAlwaysNullInGlobalScope,
    ];

    /**
     * Choose a more specific issue name based on where the issue was emitted from.
     * In loops, Phan's checks have higher false positives.
     *
     * @param Node|int|float|string $node
     * @param string $issue_name
     */
    public static function chooseSpecificImpossibleOrRedundantIssueKind($node, Context $context, string $issue_name) : string
    {
        if (ParseVisitor::isConstExpr($node)) {
            return $issue_name;
        }
        if ($context->isInGlobalScope()) {
            return self::GLOBAL_ISSUE_NAMES[$issue_name] ?? $issue_name;
        }
        if ($context->isInLoop()) {
            return self::LOOP_ISSUE_NAMES[$issue_name] ?? $issue_name;
        }

        return $issue_name;
    }
}
