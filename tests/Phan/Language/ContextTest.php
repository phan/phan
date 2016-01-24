<?php declare(strict_types=1);

namespace Phan\Tests\Language;

use \Phan\Parse\ParseVisitor;
use \Phan\CodeBase;
use \Phan\Config;
use \Phan\Debug;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;

class ContextTest extends \PHPUnit_Framework_TestCase {

    /** @var CodeBase|null */
    protected $code_base = null;

    protected function setUp() {
        $this->code_base = new CodeBase([], [], [], []);
    }

    public function tearDown() {
        $this->code_base = null;
    }

    public function testSimple() {
        $context = new Context();

        $context_namespace =
            $context->withNamespace('\A');

        $context_class =
            $context_namespace->withClassFQSEN(
                FullyQualifiedClassName::fromFullyQualifiedString('\\A\\B')
            );

        $context_method =
            $context_namespace->withMethodFQSEN(
                FullyQualifiedMethodName::fromFullyQualifiedString('\\A\\b::c')
            );

        $this->assertTrue(!empty($context));
        $this->assertTrue(!empty($context_namespace));
        $this->assertTrue(!empty($context_class));
        $this->assertTrue(!empty($context_method));
    }

    public function testClassContext() {
        $code = "<?php
            class C {
                private function f() {
                    return 42;
                }
            }";

        $stmt_list_node = \ast\parse_code(
            $code,
            Config::get()->ast_version
        );

        $class_node = $stmt_list_node->children[0];

        $context = new Context;

        $context = (new ParseVisitor(
            $this->code_base, $context
        ))($class_node);

        $stmt_list_node = $class_node->children['stmts'];
        $method_node = $stmt_list_node->children[0];

        $context = (new ParseVisitor(
            $this->code_base, $context
        ))($method_node);
    }

    public function testNamespaceMap() {
        // ...
    }

}
