<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\Analysis;
use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Library\Hasher\Consistent;
use Phan\Ordering;

use function array_keys;
use function array_merge;
use function array_sum;
use function array_values;
use function in_array;
use function sort;

/**
 * Unit tests of how Ordering assigns the files to analyze to analysis processes
 * (`process_file_assignment`, `consistent_hashing_file_order`).
 */
final class OrderingTest extends TestBase
{
    private const CONFIG_KEYS = [
        'process_file_assignment',
        'consistent_hashing_file_order',
        'randomize_file_order',
    ];

    public function tearDown(): void
    {
        parent::tearDown();
        foreach (self::CONFIG_KEYS as $key) {
            Config::setValue($key, Config::DEFAULT_CONFIGURATION[$key]);
        }
    }

    /**
     * Files of a small project with a few hierarchies (one rooted in an unanalyzed vendor class),
     * a file declaring two classes, an interface, a class with an undeclared parent and class-less files.
     * @return array<string,array{0:string,1:int}> maps file names to [code, size in bytes]
     */
    private static function mixedFiles(): array
    {
        return [
            'vendor/VendorBase.php' => ['class VendorBase {}', 100],
            'src/A.php' => ['class A {}', 300],
            'src/A1.php' => ['class A1 extends A {}', 200],
            'src/A2.php' => ['class A2 extends A {}', 900],
            'src/A11.php' => ['class A11 extends A1 {}', 150],
            'src/B.php' => ['class B {} class BHelper {}', 400],
            'src/B1.php' => ['class B1 extends B {}', 120],
            'src/BHelperChild.php' => ['class BHelperChild extends BHelper {}', 80],
            'src/Mid.php' => ['class Mid extends VendorBase {}', 250],
            'src/MidChild.php' => ['class MidChild extends Mid {}', 700],
            'src/T1.php' => ['class T1 extends VendorBase {}', 500],
            'src/T2.php' => ['class T2 extends VendorBase {}', 50],
            'src/T3.php' => ['class T3 extends VendorBase {}', 610],
            'src/T4.php' => ['class T4 extends VendorBase {}', 330],
            'src/I.php' => ['interface I {}', 60],
            'src/Missing.php' => ['class HasMissingParent extends UndeclaredParent {}', 90],
            'src/functions.php' => ['function helper() {}', 1200],
            'src/script.php' => ['echo "x";', 40],
            'src/empty.php' => ['', 0],
        ];
    }

    /**
     * The analyzed files of mixedFiles(), in the (unsorted) order of a file list.
     * @return list<string>
     */
    private static function mixedAnalyzedFileList(): array
    {
        return [
            'src/T3.php',
            'src/script.php',
            'src/B.php',
            'src/A2.php',
            'src/A.php',
            'src/MidChild.php',
            'src/functions.php',
            'src/A11.php',
            'src/T1.php',
            'src/A1.php',
            'src/B1.php',
            'src/empty.php',
            'src/BHelperChild.php',
            'src/Mid.php',
            'src/T2.php',
            'src/I.php',
            'src/T4.php',
            'src/Missing.php',
        ];
    }

    /**
     * 40 test classes extending a vendor class (whose file is not analyzed), plus a few unrelated classes.
     * @return array<string,array{0:string,1:int}>
     */
    private static function vendorRootedFiles(): array
    {
        $files = ['vendor/TestCase.php' => ['abstract class TestCase {}', 5000]];
        for ($i = 0; $i < 40; $i++) {
            $files["tests/Case{$i}Test.php"] = ["class Case{$i}Test extends TestCase {}", 1000 + (($i * 7919) % 4000)];
        }
        for ($i = 0; $i < 4; $i++) {
            $files["src/Other{$i}.php"] = ["class Other{$i} {}", 1500 + 100 * $i];
        }
        return $files;
    }

    /**
     * An analyzed test base class with a large tree of subclasses:
     * TestBaseCase <- {DbCase <- 12 tests, WebCase <- {ApiCase <- 6 tests, 6 tests}, 4 tests}
     * @return array<string,array{0:string,1:int}>
     */
    private static function splitFiles(): array
    {
        $files = [
            'vendor/TestCase.php' => ['abstract class TestCase {}', 5000],
            'tests/TestBaseCase.php' => ['abstract class TestBaseCase extends TestCase {}', 2000],
            'tests/DbCase.php' => ['abstract class DbCase extends TestBaseCase {}', 1500],
            'tests/WebCase.php' => ['abstract class WebCase extends TestBaseCase {}', 1200],
            'tests/ApiCase.php' => ['abstract class ApiCase extends WebCase {}', 800],
        ];
        for ($i = 0; $i < 12; $i++) {
            $files["tests/Db{$i}Test.php"] = ["class Db{$i}Test extends DbCase {}", 900 + (($i * 337) % 600)];
        }
        for ($i = 0; $i < 6; $i++) {
            $files["tests/Api{$i}Test.php"] = ["class Api{$i}Test extends ApiCase {}", 700 + (($i * 211) % 500)];
            $files["tests/Web{$i}Test.php"] = ["class Web{$i}Test extends WebCase {}", 600 + (($i * 113) % 400)];
        }
        for ($i = 0; $i < 4; $i++) {
            $files["tests/Plain{$i}Test.php"] = ["class Plain{$i}Test extends TestBaseCase {}", 400 + 50 * $i];
        }
        for ($i = 0; $i < 3; $i++) {
            $files["src/Lib{$i}.php"] = ["class Lib{$i} {}", 1000 + 300 * $i];
        }
        return $files;
    }

    /**
     * @param array<string,array{0:string,1:int}> $files
     */
    private static function makeCodeBase(array $files): CodeBase
    {
        $code_base = new CodeBase([], [], [], [], []);
        foreach ($files as $file => [$code, $_]) {
            if ($code === '') {
                continue;
            }
            Analysis::parseNodeInContext(
                $code_base,
                (new Context())->withFile($file),
                \ast\parse_code('<?php ' . $code, Config::AST_VERSION)
            );
        }
        return $code_base;
    }

    /**
     * @param array<string,array{0:string,1:int}> $files
     * @param ?list<string> $analyzed_file_list (defaults to every file outside of vendor/, in declaration order)
     * @return array<int,list<string>>
     */
    private static function order(array $files, int $process_count, ?array $analyzed_file_list = null): array
    {
        if ($analyzed_file_list === null) {
            $analyzed_file_list = self::nonVendorFiles($files);
        }
        $sizes = [];
        foreach ($files as $file => [$_, $size]) {
            $sizes[$file] = $size;
        }
        $ordering = new Ordering(
            self::makeCodeBase($files),
            static function (string $file) use ($sizes): int {
                return $sizes[$file];
            }
        );
        return $ordering->orderForProcessCount($process_count, $analyzed_file_list);
    }

    /**
     * @param array<string,array{0:string,1:int}> $files
     * @return list<string>
     */
    private static function nonVendorFiles(array $files): array
    {
        $result = [];
        foreach (array_keys($files) as $file) {
            if (!\str_starts_with($file, 'vendor/')) {
                $result[] = $file;
            }
        }
        return $result;
    }

    /**
     * @param array<string,array{0:string,1:int}> $files
     * @param array<int,list<string>> $process_file_list_map
     * @return list<int> the total size of the files assigned to each process
     */
    private static function loads(array $files, array $process_file_list_map): array
    {
        $loads = [];
        foreach ($process_file_list_map as $file_list) {
            $load = 0;
            foreach ($file_list as $file) {
                $size = $files[$file][1];
                $load += $size > 0 ? $size : 1;
            }
            $loads[] = $load;
        }
        return $loads;
    }

    /**
     * Asserts that every analyzed file is assigned to exactly one process.
     * @param list<string> $analyzed_file_list
     * @param array<int,list<string>> $process_file_list_map
     */
    private function assertIsPartition(array $analyzed_file_list, array $process_file_list_map): void
    {
        $assigned = array_merge(...array_values($process_file_list_map));
        sort($assigned);
        sort($analyzed_file_list);
        $this->assertSame($analyzed_file_list, $assigned);
    }

    /**
     * Asserts that within each process, every class's file is analyzed after the files of the ancestor classes on that process.
     * @param array<string,string> $parent_file_of maps a file to the file of its class's parent class
     * @param array<int,list<string>> $process_file_list_map
     */
    private function assertAncestorsFirst(array $parent_file_of, array $process_file_list_map): void
    {
        foreach ($process_file_list_map as $file_list) {
            $position = \array_flip($file_list);
            foreach ($file_list as $file) {
                $parent_file = $parent_file_of[$file] ?? null;
                if ($parent_file !== null && isset($position[$parent_file])) {
                    $this->assertLessThan($position[$file], $position[$parent_file], "$parent_file should be analyzed before $file");
                }
            }
        }
    }

    /**
     * Asserts that no process is assigned more than (1 + 15%) of an even share of the total weight.
     * @param list<int> $loads
     */
    private function assertLoadsWithinTolerance(int $process_count, array $loads): void
    {
        $this->assertCount($process_count, $loads);
        $total = array_sum($loads);
        foreach ($loads as $load) {
            $this->assertLessThanOrEqual(115 * $total, $load * $process_count * 100, 'loads: ' . \json_encode($loads));
        }
    }

    /**
     * @return list<array{0:bool}>
     */
    public static function consistentHashingProvider(): array
    {
        return [[false], [true]];
    }

    /**
     * The order of the default assignment (computed before `process_file_assignment` was added).
     * @return list<array{0:bool,1:int,2:array<int,list<string>>}>
     */
    public static function hierarchyAssignmentProvider(): array
    {
        return [
            [false, 1, [
                0 => [
                    'src/A.php', 'src/A1.php', 'src/A2.php', 'src/A11.php',
                    'src/B.php', 'src/B1.php', 'src/BHelperChild.php',
                    'src/Mid.php', 'src/T1.php', 'src/T2.php', 'src/T3.php', 'src/T4.php', 'src/MidChild.php',
                    'src/I.php', 'src/Missing.php',
                    'src/script.php', 'src/functions.php', 'src/empty.php',
                ],
            ]],
            [false, 3, [
                1 => [
                    'src/A.php', 'src/A1.php', 'src/A2.php', 'src/A11.php',
                    'src/Mid.php', 'src/T1.php', 'src/T2.php', 'src/T3.php', 'src/T4.php', 'src/MidChild.php',
                    'src/script.php',
                ],
                2 => ['src/B.php', 'src/B1.php', 'src/I.php', 'src/functions.php'],
                0 => ['src/BHelperChild.php', 'src/Missing.php', 'src/empty.php'],
            ]],
            [true, 1, [
                0 => [
                    'src/A.php', 'src/A1.php', 'src/A2.php', 'src/A11.php',
                    'src/B.php', 'src/B1.php', 'src/BHelperChild.php', 'src/I.php',
                    'src/Mid.php', 'src/T1.php', 'src/T2.php', 'src/T3.php', 'src/T4.php', 'src/MidChild.php',
                    'src/Missing.php',
                    'src/empty.php', 'src/functions.php', 'src/script.php',
                ],
            ]],
            [true, 3, [
                2 => ['src/A.php', 'src/A1.php', 'src/A2.php', 'src/A11.php', 'src/empty.php'],
                1 => ['src/B.php', 'src/B1.php', 'src/BHelperChild.php', 'src/functions.php'],
                0 => [
                    'src/I.php',
                    'src/Mid.php', 'src/T1.php', 'src/T2.php', 'src/T3.php', 'src/T4.php', 'src/MidChild.php',
                    'src/Missing.php', 'src/script.php',
                ],
            ]],
        ];
    }

    /**
     * @dataProvider hierarchyAssignmentProvider
     * @param array<int,list<string>> $expected
     */
    public function testHierarchyAssignmentIsUnchanged(bool $consistent_hashing, int $process_count, array $expected): void
    {
        Config::setValue('consistent_hashing_file_order', $consistent_hashing);
        $this->assertSame('hierarchy', Config::getValue('process_file_assignment'));
        $this->assertSame($expected, self::order(self::mixedFiles(), $process_count, self::mixedAnalyzedFileList()));
    }

    /**
     * @dataProvider consistentHashingProvider
     */
    public function testBalancedAssignmentWithOneProcessIsUnchanged(bool $consistent_hashing): void
    {
        Config::setValue('consistent_hashing_file_order', $consistent_hashing);
        $scenarios = [
            [self::mixedFiles(), self::mixedAnalyzedFileList()],
            [self::vendorRootedFiles(), null],
            [self::splitFiles(), null],
        ];
        foreach ($scenarios as [$files, $analyzed_file_list]) {
            Config::setValue('process_file_assignment', 'hierarchy');
            $expected = self::order($files, 1, $analyzed_file_list);
            Config::setValue('process_file_assignment', 'balanced');
            $this->assertSame($expected, self::order($files, 1, $analyzed_file_list));
        }
    }

    /**
     * The balanced assignment of a small project (pinned to detect unintended changes).
     * @return list<array{0:bool,1:array<int,list<string>>}>
     */
    public static function balancedAssignmentProvider(): array
    {
        return [
            [false, [
                0 => ['src/A.php', 'src/A1.php', 'src/A2.php', 'src/A11.php', 'src/T1.php'],
                1 => ['src/B.php', 'src/B1.php', 'src/BHelperChild.php', 'src/I.php', 'src/Missing.php', 'src/functions.php', 'src/empty.php'],
                2 => ['src/Mid.php', 'src/T2.php', 'src/T3.php', 'src/T4.php', 'src/MidChild.php', 'src/script.php'],
            ]],
            [true, [
                0 => ['src/I.php', 'src/T1.php', 'src/T3.php', 'src/T4.php', 'src/Missing.php', 'src/script.php'],
                1 => ['src/BHelperChild.php', 'src/Mid.php', 'src/T2.php', 'src/MidChild.php', 'src/functions.php'],
                2 => ['src/A.php', 'src/A1.php', 'src/A2.php', 'src/A11.php', 'src/B.php', 'src/B1.php', 'src/empty.php'],
            ]],
        ];
    }

    /**
     * @dataProvider balancedAssignmentProvider
     * @param array<int,list<string>> $expected
     */
    public function testBalancedAssignment(bool $consistent_hashing, array $expected): void
    {
        Config::setValue('consistent_hashing_file_order', $consistent_hashing);
        Config::setValue('process_file_assignment', 'balanced');
        $files = self::mixedFiles();
        $actual = self::order($files, 3, self::mixedAnalyzedFileList());
        $this->assertSame($expected, $actual);
        $this->assertIsPartition(self::mixedAnalyzedFileList(), $actual);
        $this->assertLoadsWithinTolerance(3, self::loads($files, $actual));
        $this->assertAncestorsFirst([
            'src/A1.php' => 'src/A.php',
            'src/A2.php' => 'src/A.php',
            'src/A11.php' => 'src/A1.php',
            'src/B1.php' => 'src/B.php',
            'src/BHelperChild.php' => 'src/B.php',
            'src/MidChild.php' => 'src/Mid.php',
        ], $actual);
    }

    /**
     * @dataProvider consistentHashingProvider
     */
    public function testBalancedAssignmentSpreadsHierarchyWithUnanalyzedRoot(bool $consistent_hashing): void
    {
        Config::setValue('consistent_hashing_file_order', $consistent_hashing);
        $files = self::vendorRootedFiles();
        $analyzed_file_list = self::nonVendorFiles($files);
        $is_test_file = static function (string $file): bool {
            return \str_starts_with($file, 'tests/');
        };

        // The default assignment puts every subclass of the vendor class on one process.
        $hierarchy = self::order($files, 4);
        $processes_with_tests = 0;
        foreach ($hierarchy as $file_list) {
            if (\array_filter($file_list, $is_test_file)) {
                $processes_with_tests++;
            }
        }
        $this->assertSame(1, $processes_with_tests);

        Config::setValue('process_file_assignment', 'balanced');
        $balanced = self::order($files, 4);
        $this->assertIsPartition($analyzed_file_list, $balanced);
        $this->assertLoadsWithinTolerance(4, self::loads($files, $balanced));
        foreach ($balanced as $file_list) {
            $this->assertNotEmpty(\array_filter($file_list, $is_test_file));
        }
        // Deterministic
        $this->assertSame($balanced, self::order($files, 4));
    }

    /**
     * @dataProvider consistentHashingProvider
     */
    public function testBalancedAssignmentSplitsOversizedHierarchy(bool $consistent_hashing): void
    {
        Config::setValue('consistent_hashing_file_order', $consistent_hashing);
        Config::setValue('process_file_assignment', 'balanced');
        $files = self::splitFiles();
        $analyzed_file_list = self::nonVendorFiles($files);
        $balanced = self::order($files, 4);
        $this->assertIsPartition($analyzed_file_list, $balanced);
        $this->assertLoadsWithinTolerance(4, self::loads($files, $balanced));

        $parent_file_of = [
            'tests/DbCase.php' => 'tests/TestBaseCase.php',
            'tests/WebCase.php' => 'tests/TestBaseCase.php',
            'tests/ApiCase.php' => 'tests/WebCase.php',
        ];
        foreach ($files as $file => [$code, $_]) {
            if (\preg_match('/extends (\w+)Case\b/', $code, $matches) && \str_contains($file, 'Test.php')) {
                $parent_file_of[$file] = "tests/{$matches[1]}Case.php";
            }
        }
        $this->assertAncestorsFirst($parent_file_of, $balanced);

        // TestBaseCase (more than an even share) is split by subtree. ApiCase's subtree is the heaviest part,
        // so the files of its ancestors WebCase and TestBaseCase are analyzed first on the same process.
        foreach ($balanced as $file_list) {
            if (in_array('tests/ApiCase.php', $file_list, true)) {
                $this->assertSame(['tests/TestBaseCase.php', 'tests/WebCase.php', 'tests/ApiCase.php'], \array_slice($file_list, 0, 3));
            }
        }
        // DbCase's subtree is larger than an even share, so its tests are spread over several processes.
        $processes_with_db_tests = 0;
        foreach ($balanced as $file_list) {
            if (\preg_grep('@^tests/Db\d+Test\.php$@D', $file_list)) {
                $processes_with_db_tests++;
            }
        }
        $this->assertGreaterThan(1, $processes_with_db_tests);
        // Deterministic
        $this->assertSame($balanced, self::order($files, 4));
    }

    /**
     * @dataProvider consistentHashingProvider
     */
    public function testBalancedAssignmentOmitsIdleProcesses(bool $consistent_hashing): void
    {
        Config::setValue('consistent_hashing_file_order', $consistent_hashing);
        Config::setValue('process_file_assignment', 'balanced');
        $result = self::order(self::mixedFiles(), 4, ['src/A1.php', 'src/A.php']);
        $this->assertCount(1, $result);
        $this->assertSame(['src/A.php', 'src/A1.php'], array_values($result)[0]);
    }

    public function testGroupsInRingOrder(): void
    {
        $hasher = new Consistent(4);
        foreach (['\\A', '\\B\\C', 'src/file.php', ''] as $key) {
            $groups = $hasher->getGroupsInRingOrder($key);
            $this->assertSame($hasher->getGroup($key), $groups[0]);
            $sorted = $groups;
            sort($sorted);
            $this->assertSame([0, 1, 2, 3], $sorted);
        }
    }
}
