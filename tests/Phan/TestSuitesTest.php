<?php

declare(strict_types=1);

/* @phan-file-suppress PhanAccessMethodInternal There doesn't seem to be a clean way of doing it. */

namespace Phan\Tests;

use PHPUnit\Framework\TestCase;
use PHPUnit\TextUI\Configuration\TestSuiteCollection;
use PHPUnit\TextUI\XmlConfiguration\Loader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SebastianBergmann\FileIterator\Facade;
use SplFileInfo;

/**
 * Test to verify that all PHPUnit test files are in exactly one test suite.
 * @coversNothing
 */
class TestSuitesTest extends TestCase
{
    public function testAllTestFilesAreInASuite(): void {
        $baseDirectory = __DIR__;

        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $baseDirectory ) );
        $testFiles = [];
        /** @var SplFileInfo $file */
        foreach ($it as $file) {
            '@phan-var SplFileInfo $file';
            if ($file->isDir()) {
                continue;
            }
            $file_name = $file->getFilename();
            if (
                str_ends_with($file_name, 'Test.php') ||
                preg_match( '/^PhanTest\d+\.php/', $file_name ) ||
                $file_name === 'PhanTestNew.php'
            ) {
                $testFiles[] = $file->getRealPath();
            }
        }

        $config = (new Loader)->load( __DIR__ . '/../../phpunit.xml' );
        $suiteFiles = self::getSuiteFiles($config->testSuite());

        sort($testFiles);
        sort($suiteFiles);

        $this->assertSame($testFiles, $suiteFiles, 'All PHPUnit test files should be part of exactly one suite');
    }

    /**
     * Modified version of TestSuiteMapper::map that doesn't actually load test files, to avoid side effects (we only
     * need file names, not classes).
     * @return list<string>
     */
    private static function getSuiteFiles(TestSuiteCollection $configuration): array {
        $suiteFilesMap = [];

        foreach ($configuration as $testSuiteConfiguration) {
            $exclude = [];

            foreach ($testSuiteConfiguration->exclude()->asArray() as $file) {
                '@phan-var \PHPUnit\TextUI\Configuration\File $file';
                $exclude[] = $file->path();
            }

            foreach ($testSuiteConfiguration->directories() as $directory) {
                if (!version_compare(
                    PHP_VERSION,
                    $directory->phpVersion(),
                    $directory->phpVersionOperator()->asString()
                )) {
                    continue;
                }

                $files = (new Facade)->getFilesAsArray(
                    $directory->path(),
                    $directory->suffix(),
                    $directory->prefix(),
                    $exclude,
                );

                if (!empty($files)) {
                    $suiteFilesMap += array_flip($files);
                }
            }

            foreach ($testSuiteConfiguration->files() as $file) {
                if (!version_compare(PHP_VERSION, $file->phpVersion(), $file->phpVersionOperator()->asString())) {
                    continue;
                }

                $suiteFilesMap[$file->path()] = 1;
            }
        }
        '@phan-var array<string,1> $suiteFilesMap';

        // @phan-suppress-next-line PhanPartialTypeMismatchReturn These are all valid paths
        return array_map('realpath', array_keys($suiteFilesMap));
    }
}
