<?php declare(strict_Types=1);
namespace Phan\Library;

class FileCacheEntry {
    /** @var string contents of the file */
    private $contents;
    /**
     * @var ?array lines of the contents of the file. Lazily populated.
     */
    private $lines = null;

    public function __construct(string $contents) {
        $this->contents = $contents;
    }

    public function getContents() : string {
        return $this->contents;
    }

    /**
     * @return string[] a 1-based array of lines
     */
    public function getLines() : array {
        $lines = $this->lines;
        if (is_array($lines)) {
            return $lines;
        }
        $lines = preg_split("/^/m", $this->contents);
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
    public function getLine(int $lineno) {
        $lines = $this->getLines();
        return $lines[$lineno] ?? null;
    }
}
