<?php declare(strict_types=1);

namespace Phan\Plugin\Internal\IssueFixingPlugin;

use InvalidArgumentException;

/**
 * Represents a change to be made to file contents.
 * The structure of this will change.
 * @phan-immutable
 */
class FileEdit
{
    /** @var int the byte offset where the replacement will start */
    public $replace_start;
    /** @var int the byte offset where the replacement will end. this is >= $replace_start */
    public $replace_end;
    /** @var string the contents to replace the range with. Make this empty to delete. */
    public $new_text;

    /**
     * Create a new file edit (currently just supports deleting lines)
     */
    public function __construct(int $replace_start, int $replace_end, string $new_text = '')
    {
        if ($replace_end < $replace_start) {
            throw new InvalidArgumentException("Out of order: end $replace_end < start $replace_start");
        }
        if ($replace_start < 0) {
            throw new InvalidArgumentException("Out of range: start $replace_start < 0");
        }
        $this->replace_start = $replace_start;
        $this->replace_end = $replace_end;
        $this->new_text = $new_text;
    }

    /**
     * Returns true if this has the same effect as $other
     */
    public function isEqualTo(FileEdit $other) : bool
    {
        return $this->replace_start === $other->replace_start &&
            $this->replace_end === $other->replace_end &&
            $this->new_text === $other->new_text;
    }
}
