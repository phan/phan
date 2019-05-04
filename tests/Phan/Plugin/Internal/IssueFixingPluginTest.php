<?php declare(strict_types=1);

namespace Phan\Tests\Plugin\Internal;

use Phan\CodeBase;
use Phan\Issue;
use Phan\IssueInstance;
use Phan\Plugin\Internal\IssueFixingPlugin\FileEdit;
use Phan\Plugin\Internal\IssueFixingPlugin\IssueFixer;
use Phan\Tests\BaseTest;
use Phan\Tests\CodeBaseAwareTestInterface;

/**
 * Unit tests of fixes to issues
 */
final class IssueFixingPluginTest extends BaseTest implements CodeBaseAwareTestInterface
{
    /** @var CodeBase|null The code base within which this unit test is operating */
    private $code_base = null;

    public function setCodeBase(CodeBase $code_base = null) : void
    {
        $this->code_base = $code_base;
    }

    const FILE = 'fix_test.php';

    /**
     * @param IssueInstance[] $instances
     * @dataProvider computeAndApplyFixesProvider
     */
    public function testComputeAndApplyFixes(string $expected_contents, string $original_contents, array $instances) : void
    {
        $fixers = IssueFixer::computeFixersForInstances($instances);
        //var_export($instances);

        $this->assertCount(1, $fixers);
        $fixers_for_file = $fixers[self::FILE];
        // echo "Going to apply to \n$original_contents\n";
        // @phan-suppress-next-line PhanPossiblyNullTypeArgument, PhanPartialTypeMismatchArgument
        $new_contents = IssueFixer::computeNewContentForFixers($this->code_base, self::FILE, $original_contents, $fixers_for_file);
        $this->assertSame($expected_contents, $new_contents, 'unexpected contents after applying fixes');
    }

    /**
     * @return array<int,array{0:string,1:string,2:array<int,IssueInstance>}>
     */
    public function computeAndApplyFixesProvider() : array
    {
        /**
         * @param int|string ...$args
         */
        $make = static function (string $type, int $line, ...$args) : IssueInstance {
            return new IssueInstance(Issue::fromType($type), self::FILE, $line, $args);
        };
        return [
            [
                <<<'EOT'
<?php
namespace test;
use function strlen;
echo "Hello, world!";
echo strlen($argv[0]);
EOT
                ,
                <<<'EOT'
<?php
namespace test;
use ast\Node;
use function foo;
use function strlen;
use const ast\AST_VERSION;
echo "Hello, world!";
echo strlen($argv[0]);
EOT
                ,
                [
                    $make(Issue::UnreferencedUseNormal, 3, 'Node', '\ast\Node'),
                    $make(Issue::UnreferencedUseFunction, 4, 'foo', '\foo'),
                    $make(Issue::UnreferencedUseConstant, 6, 'AST_VERSION', '\ast\AST_VERSION'),
                ],
            ],
        ];
    }

    /**
     * @param ?string $expected_contents
     * @param string $contents
     * @param FileEdit[] $all_edits
     * @dataProvider computeNewContentsProvider
     */
    public function testComputeNewContents(?string $expected_contents, string $contents, array $all_edits) : void
    {
        $this->assertSame($expected_contents, IssueFixer::computeNewContents(self::FILE, $contents, $all_edits));
    }

    /**
     * @return array<int,array{0:?string,1:string,2:FileEdit[]}>
     */
    public function computeNewContentsProvider() : array
    {
        return [
            [
                //    5   10
                '<?php  // post',
                '<?php  // test',
                [
                    new FileEdit(10, 11, 'p'),
                    new FileEdit(11, 12, 'o'),
                ]
            ],
            [
                //    5   10
                '<?php  // post',
                '<?php  // test',
                [
                    new FileEdit(10, 12, ''),
                    new FileEdit(12, 12, 'po'),
                ]
            ],
            [
                //    5   10
                '<?php  // post',
                '<?php  // test',
                [
                    // should discard duplicate edits
                    new FileEdit(10, 12, ''),
                    new FileEdit(12, 12, 'po'),
                    new FileEdit(10, 12, ''),
                    new FileEdit(12, 12, 'po'),
                ]
            ],
            [
                //    5   10
                null,
                '<?php  // test',
                [
                    // should give up for conflicting edits
                    new FileEdit(10, 12, ''),
                    new FileEdit(12, 12, 'be'),
                    new FileEdit(12, 12, 'po'),
                ]
            ],
            [
                //    5   10
                null,
                '<?php  // test',
                [
                    // should give up for conflicting edits
                    new FileEdit(10, 13, 'ab'),
                    new FileEdit(12, 14, 'cd'),
                ]
            ],
        ];
    }
}
