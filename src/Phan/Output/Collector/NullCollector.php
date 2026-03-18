<?php

declare(strict_types=1);

namespace Phan\Output\Collector;

use Phan\IssueInstance;
use Phan\Output\IssueCollectorInterface;

/**
 * A no-op issue collector that discards all issues.
 * Used during type-gathering passes where issue emission is unnecessary.
 */
final class NullCollector implements IssueCollectorInterface
{
    /**
     * @suppress PhanUnusedPublicFinalMethodParameter
     */
    public function collectIssue(IssueInstance $issue): void
    {
    }

    /**
     * @return list<IssueInstance>
     */
    public function getCollectedIssues(): array
    {
        return [];
    }

    /**
     * @param list<string> $files
     * @suppress PhanUnusedPublicFinalMethodParameter
     */
    public function removeIssuesForFiles(array $files): void
    {
    }

    public function reset(): void
    {
    }
}
