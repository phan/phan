<?php declare(strict_types=1);

namespace Phan\Library;

use AssertionError;

/**
 * Represents the cached contents of a given file, and various ways to access that file.
 *
 * This is used under the circumstances such as the following:
 *
 * - Checking for (at)phan-suppress-line annotations at runtime - Many checks to the same file will often be in cache
 * - Checking the tokens/text of the file for purposes such as checking for expressions that are incompatible in PHP5.
 */
class FileCacheEntry
{
    /** @var string contents of the file */
    private $contents;

    /**
     * @var ?array<int,string> lines of the contents of the file. Lazily populated.
     */
    private $lines = null;

    public function __construct(string $contents)
    {
        $this->contents = $contents;
    }

    /** @return string contents of the file */
    public function getContents() : string
    {
        return $this->contents;
    }

    /**
     * @return array<int,string> a 1-based array of lines
     */
    public function getLines() : array
    {
        $lines = $this->lines;
        if (\is_array($lines)) {
            return $lines;
        }
        $lines = \preg_split("/^/m", $this->contents);
        // TODO: Use a better way to not include false when arguments are both valid
        if (!\is_array($lines)) {
            throw new AssertionError("Expected lines to be an array");
        }
        unset($lines[0]);
        $this->lines = $lines;
        return $lines;
    }


    /**
     * Helper method to get individual lines from a file.
     * This is more efficient than using \SplFileObject if multiple lines may need to be fetched.
     *
     * @param int $lineno - A line number, starting with line 1
     * @return ?string
     */
    public function getLine(int $lineno)
    {
        $lines = $this->getLines();
        return $lines[$lineno] ?? null;
    }
}
