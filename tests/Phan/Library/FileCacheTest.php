<?php declare(strict_types = 1);
namespace Phan\Tests\Library;

use Phan\Tests\BaseTest;
use Phan\Library\FileCache;

/**
 */
class FileCacheTest extends BaseTest
{
    const MOCK_PATH = '/path/to/a';
    const MOCK_CONTENTS = "Mock contents\nOther lines\n";

    public function setUp()
    {
        parent::setUp();
        FileCache::clear();
        FileCache::setMaxCacheSize(FileCache::MINIMUM_CACHE_SIZE);
    }

    public function tearDown()
    {
        parent::setUp();
        FileCache::clear();
    }

    public function testAddRead()
    {
        $entry = FileCache::addEntry(self::MOCK_PATH, self::MOCK_CONTENTS);
        $read_entry = FileCache::getEntry(self::MOCK_PATH);
        $this->assertSame($read_entry, $entry);
        $this->assertSame(self::MOCK_CONTENTS, $entry->getContents());
        $this->assertSame([1 => "Mock contents\n", 2 => "Other lines\n"], $entry->getLines());
        $this->assertSame("Other lines\n", $entry->getLine(2));
        $this->assertNull($entry->getLine(3));
        $this->assertNull($entry->getLine(0));
    }

    public function testLRU()
    {
        $expectedPaths = [];
        $this->assertGreaterThanOrEqual(5, FileCache::MINIMUM_CACHE_SIZE);
        for ($i = 0; $i < FileCache::MINIMUM_CACHE_SIZE; $i++) {
            $path = "/path/to/$i";
            $expectedPaths[] = $path;
            FileCache::addEntry($path, "contents of $i");
        }
        $this->assertSame($expectedPaths, FileCache::getCachedFileList());

        $this->assertSame("contents of 0", FileCache::getEntry("/path/to/0")->getContents());

        $firstEntry = array_shift($expectedPaths);
        $expectedPaths[] = $firstEntry;
        $this->assertSame($expectedPaths, FileCache::getCachedFileList());

        FileCache::addEntry("/path/to/extra", "Other contents");
        $this->assertNull(FileCache::getEntry("/path/to/1"), "Least recently used entry should be evicted");
        $this->assertSame("Other contents", FileCache::getEntry("/path/to/extra")->getContents(), "Most recently inserted entry should be kept");
        $this->assertSame("contents of 2", FileCache::getEntry("/path/to/2")->getContents(), "Second least recently used entry should be kept");
    }

    public function testGetOrReadEntry()
    {
        $this->assertNull(FileCache::getEntry(__FILE__));
        $line = __LINE__;  // Comment placeholder
        $expectedLineContents = "        \$line = __LINE__;  // Comment placeholder\n";
        $entry = FileCache::getOrReadEntry(__FILE__);
        $this->assertSame($expectedLineContents, $entry->getLine($line));
    }

    public function testGetOrReadEntryThrows()
    {
        try {
            FileCache::getOrReadEntry('/path/to/missingfile');
            $this->fail('should throw');
        } catch (\RuntimeException $e) {
            $this->assertContains('FileCache::getOrReadEntry: unable to find', $e->getMessage());
        }
    }
}
