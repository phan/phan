<?php declare(strict_types=1);

namespace Phan\Plugin\Internal\IssueFixingPlugin;

/**
 * Represents a set of changes to be made to file contents.
 * The structure of this will change.
 */
class FileEditSet
{
    /** @var FileEdit[] */
    public $edits;

    /**
     * @param FileEdit[] $edits
     */
    public function __construct(array $edits)
    {
        $this->edits = $edits;
    }
}
