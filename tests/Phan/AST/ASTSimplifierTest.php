<?php declare(strict_types=1);

namespace Phan\Tests\AST;

use Phan\AST\ASTSimplifier;
use Phan\Tests\AbstractPhanFileTest;
use Phan\Config;
use Phan\Debug;

/**
 * Tests of ASTSimplifier converting one AST to the expected AST.
 */
final class ASTSimplifierTest extends AbstractPhanFileTest
{
    /**
     * @suppress PhanUndeclaredConstant
     */
    public function getTestFiles()
    {
        return $this->scanSourceFilesDir(AST_TEST_FILE_DIR, AST_EXPECTED_DIR);
    }

    /**
     * This reads all files in `tests/files/src`, runs
     * the analyzer on each and compares the output
     * to the ASTs of the counterparts in
     * `tests/files/expected`
     *
     * @param string[] $test_file_list @phan-unused-param
     * @param string $expected_file_path
     * @param ?string $config_file_path @phan-unused-param
     * @dataProvider getTestFiles
     * @override
     */
    public function testFiles($test_file_list, $expected_file_path, $config_file_path = null)
    {
        $this->assertCount(1, $test_file_list);
        list($original_file_path) = $test_file_list;
        // Read the expected output
        $original_src =
            file_get_contents($original_file_path);
        $expected_src =
            file_get_contents($expected_file_path);
        $this->assertNotEquals(false, $original_src);
        $this->assertNotEquals(false, $expected_src);

        $ast_version = Config::AST_VERSION;
        $expected = \ast\parse_code($expected_src, $ast_version);
        $before_transform = \ast\parse_code($original_src, $ast_version);

        // We use identical files for testing ASTs which aren't expected to change.
        if (trim($expected_src) !== trim($original_src)) {
            $this->assertNotEquals(Debug::astDump($expected), Debug::astDump($before_transform), 'Expected the input asts to be different');
        }
        $actual = ASTSimplifier::applyStatic($before_transform);
        $this->assertInstanceOf(\ast\Node::class, $actual, 'should return an AST');
        $this->assertSame(\ast\AST_STMT_LIST, $actual->kind, 'should return an AST of kind AST_STMT_LIST');
        $actual_repr = Debug::astDump($actual);
        $expected_repr = Debug::astDump($expected);
        $this->assertEquals($expected_repr, $actual_repr, 'Expected the AST representation to be the same as the expected source\'s after transformations');
    }
}
