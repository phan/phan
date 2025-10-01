<?php

declare(strict_types=1);

namespace Phan\Output\Filter;

use Phan\Config;
use Phan\IssueInstance;
use Phan\Output\IssueFilterInterface;

/**
 * SubdirectoryIssueFilter filters out issues from files outside the working directory.
 * Used when Phan is run from a subdirectory of a project with a config in a parent directory.
 */
final class SubdirectoryIssueFilter implements IssueFilterInterface
{
    /** @var string The working directory (where Phan was invoked from) */
    private $working_directory;

    /**
     * SubdirectoryIssueFilter constructor.
     * @param string $working_directory The working directory to filter by
     */
    public function __construct(string $working_directory)
    {
        // Normalize path with trailing separator for prefix matching
        $this->working_directory = \rtrim($working_directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * @return bool true if the issue's file is within or below the working directory
     */
    public function supports(IssueInstance $issue): bool
    {
        $issue_file = $issue->getFile();

        // Convert relative paths to absolute
        if (!self::isAbsolutePath($issue_file)) {
            $issue_file = Config::projectPath($issue_file);
        }

        // Normalize path for comparison
        $issue_file = \rtrim($issue_file, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        // Check if issue file is in or below working directory
        return \str_starts_with($issue_file, $this->working_directory);
    }

    /**
     * Check if a path is absolute (has leading / or drive letter on Windows)
     */
    private static function isAbsolutePath(string $path): bool
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            // Windows: Check for drive letter or UNC path
            return \strlen($path) >= 2 && (
                $path[1] === ':' ||
                ($path[0] === '\\' && $path[1] === '\\')
            );
        } else {
            // Unix: Check for leading slash
            return isset($path[0]) && $path[0] === '/';
        }
    }
}
