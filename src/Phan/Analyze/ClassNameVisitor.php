<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\AST\ContextNode;
use \Phan\AST\UnionTypeVisitor;
use \Phan\AST\Visitor\Element;
use \Phan\AST\Visitor\KindVisitorImplementation;
use \Phan\Analyze\ClassName\MethodCallVisitor;
use \Phan\Analyze\ClassName\ValidationVisitor;
use \Phan\CodeBase;
use \Phan\Debug;
use \Phan\Exception\AccessException;
use \Phan\Language\Context;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * A visitor that can extract a class name from a few
 * types of nodes
 */
class ClassNameVisitor extends KindVisitorImplementation {

    /**
     * @var Context
     * The context of the current execution
     */
    private $context;

    /**
     * @var CodeBase
     */
    private $code_base;

    /**
     * @param Context $context
     * The context of the current execution
     *
     * @param CodeBase $code_base
     */
    public function __construct(Context $context, CodeBase $code_base) {
        $this->context = $context;
        $this->code_base = $code_base;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visit(Node $node) : string {
        if (isset($node->children['class'])) {
            return $this->visitNew($node);
        }

        return '';
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitNew(Node $node) : string {

        // Things of the form `new $class_name();`
        if ($node->children['class']->kind == \ast\AST_VAR) {
            return '';
        }

        // Anonymous class
        // $v = new class { ... }
        if ($node->children['class']->kind == \ast\AST_CLASS
            && $node->children['class']->flags & \ast\flags\CLASS_ANONYMOUS
        ) {
            return (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            ))->getUnqualifiedNameForAnonymousClass();
        }

        // Things of the form `new $method->name()`
        if($node->children['class']->kind !== \ast\AST_NAME) {
            return '';
        }

        $class_name =
            $node->children['class']->children['name'];

        if(!in_array($class_name, ['self', 'static', 'parent'])) {
            return (string)UnionTypeVisitor::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $node->children['class']
            );
        }

        if (!$this->context->isInClassScope()) {
            Log::err(
                Log::ESTATIC,
                "Cannot access {$class_name}:: when no class scope is active",
                $this->context->getFile(),
                $node->lineno
            );

            return '';
        }

        if($class_name == 'static') {
            return (string)$this->context->getClassFQSEN();
        }

        if($class_name == 'self') {
            if ($this->context->isGlobalScope()) {
                assert(false, "Unimplemented branch is required for {$this->context}");
            } else {
                return (string)$this->context->getClassFQSEN();
            }
        }

        if($class_name == 'parent') {
            $clazz = $this->context->getClassInScope($this->code_base);

            if (!$clazz->hasParentClassFQSEN()) {
                return '';
            }

            return (string)$clazz->getParentClassFQSEN();
        }

        return '';
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitStaticCall(Node $node) : string {
        return $this->visitNew($node);
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitClassConst(Node $node) : string {
        return $this->visitNew($node);
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitInstanceOf(Node $node) : string {
        return $this->visitNew($node);
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitMethodCall(Node $node) : string {

        // Get the name of the method we're looking for
        $method_name = is_string($node->children['method'])
            ? $node->children['method'] : null;

        return (new Element($node->children['expr']))->acceptKindVisitor(
            new MethodCallVisitor(
                $this->context,
                $this->code_base,
                $method_name
            )
        );
    }

    /**
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return string
     * The class name represented by the given call
     */
    public function visitProp(Node $node) : string {
        return (new Element($node->children['expr']))->acceptKindVisitor(
            new MethodCallVisitor(
                $this->context,
                $this->code_base
            )
        );
    }

}
