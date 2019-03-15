<?php declare(strict_types=1);

namespace Phan\Exception;

use Exception;
use Phan\IssueInstance;

/**
 * # Example Usage
 * ```
 * throw new IssueException(
 *     Issue::fromType(
 *         Issue::UndeclaredClassReference
 *     )(
 *         $context->getFile(),
 *         $node->getLine() ?? 0
 *     )
 * );
 * ```
 */
class IssueException extends Exception
{

    /**
     * @var IssueInstance
     * An instance of an issue that was found but can't be
     * reported on immediately.
     */
    private $issue_instance;

    /**
     * @param IssueInstance $issue_instance
     * An instance of an issue that was found but can't be
     * reported on immediately.
     */
    public function __construct(
        IssueInstance $issue_instance
    ) {
        parent::__construct();
        $this->issue_instance = $issue_instance;
    }

    /**
     * @return IssueInstance
     * The issue that was found
     */
    public function getIssueInstance() : IssueInstance
    {
        return $this->issue_instance;
    }

    /**
     * @override
     */
    public function __toString()
    {
        return \sprintf(
            "IssueException at %s:%d: %s\n%s",
            $this->getFile(),
            $this->getLine(),
            (string)$this->issue_instance,
            $this->getTraceAsString()
        );
    }
}
