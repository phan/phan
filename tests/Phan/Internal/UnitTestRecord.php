<?php

declare(strict_types=1);

namespace Phan\Tests\Internal;

use RuntimeException;

use function explode;
use function trim;
use function count;
use function is_string;
use function file_get_contents;

/**
 * This represents a record of a unit test with a single source file and a single expectation file.
 */
class UnitTestRecord
{
    /** @var string the file name of the source of the unit test */
    public $src_filename;
    /** @var string the file name of the expected errors of the unit test */
    public $expected_filename;
    /** @var string the contents of the source of the unit test */
    public $src_contents;
    /** @var string the expected text errors of that unit test */
    public $expected_contents;

    public function __construct(string $src_filename, string $expected_filename)
    {
        $this->src_filename = $src_filename;
        $this->expected_filename = $expected_filename;
        $this->src_contents = self::getContents($src_filename);
        $this->expected_contents = self::getContents($expected_filename);
    }

    /**
     * @return array<int, array{0:string,1:string,2:string}> the issues parsed from this file.
     * Contains the file name, issue type, and issue description.
     * The array keys start with 1 (the line number of the expected file)
     */
    public function getIssues(): array
    {
        $issues = [];
        foreach (explode("\n", $this->expected_contents) as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $lineno = $i + 1;
            $details = explode(' ', $line, 3);
            if (count($details) !== 3) {
                continue;
            }
            $issues[$lineno] = $details;
        }
        return $issues;
    }

    private static function getContents(string $filename): string
    {
        $contents = file_get_contents($filename);
        if (!is_string($contents)) {
            throw new RuntimeException("Failed to read $filename");
        }
        return $contents;
    }
}
