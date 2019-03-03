<?php declare(strict_types=1);

namespace Phan\Plugin\Internal\IssueFixingPlugin;

/**
 * Represents a change to be made to file contents.
 * The structure of this will change.
 * @internal
 */
class FileEdit
{
    /** @var int the byte offset where the replacement will start */
    public $replace_start;
    /** @var int the byte offset where the replacement will end */
    public $replace_end;
    // TODO: Implement insertion
    /** @var string the contents to replace the range with */
    // public $new_text = '';

    /**
     * Create a new file edit (currently just supports deleting lines)
     */
    public function __construct(int $replace_start, int $replace_end)
    {
        $this->replace_start = $replace_start;
        $this->replace_end = $replace_end;
    }
}
