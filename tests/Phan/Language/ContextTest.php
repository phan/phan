<?php declare(strict_types=1);

namespace Phan\Tests\Language;

use Phan\CodeBase;
use Phan\Config;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope\ClassScope;
use Phan\Language\Scope\FunctionLikeScope;
use Phan\Parse\ParseVisitor;
use Phan\Tests\BaseTest;

/**
 * Unit tests of Context and scopes
 * @phan-file-suppress PhanThrowTypeAbsentForCall
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 */
final class ContextTest extends BaseTest
{

    /** @var CodeBase The code base within which this unit test is running */
    protected $code_base = null;

    protected function setUp()
    {
        // Deliberately not calling parent::setUp()
        $this->code_base = new CodeBase([], [], [], [], []);
    }

    protected function tearDown()
    {
        // Deliberately not calling parent::tearDown()
        // @phan-suppress-next-line PhanTypeMismatchProperty
        $this->code_base = null;
    }

    public function testSimple()
    {
        $context = new Context();

        $context_namespace =
            $context->withNamespace('\A');

        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\A\\B');
        $context_class = $context_namespace->withScope(
            new ClassScope(
                $context_namespace->getScope(),
                $class_fqsen,
                0
            )
        );

        $context_method = $context_namespace->withScope(
            new FunctionLikeScope(
                $context_namespace->getScope(),
                FullyQualifiedMethodName::fromFullyQualifiedString('\\A\\b::c')
            )
        );

        $this->assertNotEmpty($context);
        $this->assertNotEmpty($context_namespace);
        $this->assertNotEmpty($context_class);
        $this->assertNotEmpty($context_method);
    }

    public function testClassContext()
    {
        $code = "<?php
            class C {
                private function f() {
                    return 42;
                }
            }";

        $stmt_list_node = \ast\parse_code(
            $code,
            Config::AST_VERSION
        );

        $class_node = $stmt_list_node->children[0];

        $context = new Context();

        $context = (new ParseVisitor(
            $this->code_base,
            $context
        ))($class_node);

        $stmt_list_node = $class_node->children['stmts'];
        $method_node = $stmt_list_node->children[0];

        $context = (new ParseVisitor(
            $this->code_base,
            $context
        ))($method_node);

        $this->assertSame('\C::f', (string)$context->getScope()->getFQSEN());
    }
}
