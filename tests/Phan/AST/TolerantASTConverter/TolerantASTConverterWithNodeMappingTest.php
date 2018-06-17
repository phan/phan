<?php declare(strict_types = 1);

namespace Phan\Tests\AST\TolerantASTConverter;

use Phan\AST\TolerantASTConverter\TolerantASTConverterWithNodeMapping;
use Phan\AST\TolerantASTConverter\TolerantASTConverter;
use Phan\Config;
use Phan\Tests\BaseTest;
use InvalidArgumentException;
use ast;
use ast\Node;

/**
 * Tests that the fallback works with ASTs, and can point an ast\Node to the original.
 *
 * @phan-file-suppress PhanThrowTypeAbsent it's a test
 */
class TolerantASTConverterWithNodeMappingTest extends BaseTest
{
    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();
        Config::reset();
    }

    /**
     * @param int $line 0-based
     * @param int $column 0-based
     * @dataProvider byteOffsetLookupProvider
     * @suppress PhanUndeclaredProperty isSelected
     */
    public function testByteOffsetLookup(int $line, int $column, string $file_contents, Node $expected_node)
    {
        $expected_node->isSelected = true;

        $byte_offset = self::computeOffset($line, $column, $file_contents);
        $ast = $this->parseASTWithDefaultOptions($file_contents, $byte_offset);
        // TODO: Create a reusable abstraction in Util to walk / filter nodes from the AST
        $selected_node = $this->findSelectedNode($ast);
        $this->assertEquals(\Phan\Debug::nodeToString($expected_node), \Phan\Debug::nodeToString($selected_node));
        $this->assertEquals($expected_node, $selected_node);
    }

    private function findSelectedNode($node) : Node
    {
        $candidates = [];
        $this->findSelectedNodeInner($node, $candidates);
        $this->assertCount(1, $candidates, 'expected one node to be marked with isSelected');
        return $candidates[0];
    }

    /**
     * @param array<int,Node> &$candidates
     * @return void
     * @suppress PhanUndeclaredProperty isSelected is dynamically added by Phan
     */
    private function findSelectedNodeInner($node, array &$candidates)
    {
        if ($node instanceof Node) {
            if (\property_exists($node, 'isSelected')) {
                $candidates[] = $node;
            }
            foreach ($node->children as $child_node) {
                $this->findSelectedNodeInner($child_node, $candidates);
            }
        }
        if (\is_array($node)) {
            foreach ($node as $child_node) {
                $this->findSelectedNodeInner($child_node, $candidates);
            }
            return;
        }
    }

    private function parseASTWithDefaultOptions(string $file_contents, int $byte_offset)
    {
        $converter = new TolerantASTConverterWithNodeMapping($byte_offset);
        $errors = [];
        $ast = $converter->parseCodeAsPHPAST($file_contents, TolerantASTConverter::AST_VERSION, $errors);
        if (count($errors) > 0) {
            throw new InvalidArgumentException("Unexpected errors: " . json_encode($errors));
        }
        return $ast;
    }

    // @param int $line 1-based
    private static function computeOffset(int $line, int $column, string $file_contents) : int
    {
        // TODO: Use a utility function instead?
        $byte_offset = 0;
        while ($line > 1) {
            $line--;
            $newline_pos = strpos($file_contents, "\n", $byte_offset);
            if ($newline_pos === false) {
                throw new \InvalidArgumentException("too many lines");
            }
            $byte_offset = $newline_pos + 1;
        }
        return $byte_offset + $column;
    }

    /**
     * @return array<int,array{0:int,1:int,2:string,3:Node}>
     */
    public function byteOffsetLookupProvider()
    {
        // using 1-based lines, 0-based columns
        $default_file = <<<'EOT'
<?php  // line 1

namespace {
    use ast\Node;
    use function ast\parse_code as ParseCode;  // line 5
    use const ast\AST_NEW;


    interface MyInterface { }
    class MyClass {  // line 10
        public function myMethod(MyInterface $param) : MyInterface {
        }
    }
    function global_function_using_node($node) : Node {
        throw new RuntimeException("not implemented");
    }



}  // end global namespace
EOT;

        $use_stmt_ast_node = new Node(ast\AST_USE, ast\flags\USE_NORMAL, [
            new Node(ast\AST_USE_ELEM, 0, ['name' => 'ast\Node', 'alias' => null], 4)
        ], 4);

        $use_stmt_ast_parse_code = new Node(ast\AST_USE, ast\flags\USE_FUNCTION, [
            new Node(ast\AST_USE_ELEM, 0, ['name' => 'ast\parse_code', 'alias' => 'ParseCode'], 5)
        ], 5);

        $use_stmt_ast_const_new = new Node(ast\AST_USE, ast\flags\USE_CONST, [
            new Node(ast\AST_USE_ELEM, 0, ['name' => 'ast\AST_NEW', 'alias' => null], 6)
        ], 6);

        $param_node = new Node(ast\AST_NAME, \ast\flags\NAME_NOT_FQ, [
            'name' => 'MyInterface',
        ], 11);

        return [
            [4, 9, $default_file, $use_stmt_ast_node],
            [5, 20, $default_file, $use_stmt_ast_parse_code],
            [5, 40, $default_file, $use_stmt_ast_parse_code],
            [6, 16, $default_file, $use_stmt_ast_const_new],
            [11, 40, $default_file, $param_node],
        ];
    }
}
