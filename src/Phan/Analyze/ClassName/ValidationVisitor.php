<?php declare(strict_types=1);
namespace Phan\Analyze\ClassName;

use \Phan\AST\Visitor\KindVisitorImplementation;
use \Phan\CodeBase;
use \Phan\Debug;
use \Phan\Issue;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * A visitor that can extract a class name from a few
 * types of nodes
 */
class ValidationVisitor
    extends KindVisitorImplementation
{
    /**
     * @var Context
     * The context of the current execution
     */
    private $context;

    /**
     * @var CodeBase
     * The entire code base
     */
    private $code_base;

    /**
     * @var string
     * The name of the class that we're verifying
     */
    private $class_name;

    /**
     * @var FQSEN
     * The fully qualified class name based on the
     * given context
     */
    private $class_fqsen;

    /**
     * @param Context $context
     * The context of the current execution
     *
     * @param CodeBase $code_base
     * The global code base
     *
     * @param string $class_name
     * The name we're trying to validate
     */
    public function __construct(
        Context $context,
        CodeBase $code_base,
        string $class_name
    ) {
        $this->context = $context;
        $this->code_base = $code_base;
        $this->class_name = $class_name;

        // Compute the FQSEN based on the current context
        $this->class_fqsen =
            FullyQualifiedClassName::fromStringInContext(
                $this->class_name,
                $this->context
            );
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     */
    public function visit(Node $node) : bool {
        if (isset($node->children['class'])) {
            if ($node->kind == \ast\AST_STATIC_PROP) {
                return $this->visitStaticProp($node);
            } else {
                return $this->visitNew($node);
            }
        }

        // TODO: Should be Issue::UnanalyzableNode
        Issue::emit(
            Issue::UnknownNodeType,
            $this->context->getFile(),
            $node->lineno ?? 0
        );

        return false;
    }

    public function visitNew(Node $node) : bool {
        if (!$this->classExists()) {
            return $this->classExistsOrIsNative($node);
        }

        $clazz =
            $this->code_base->getClassByFQSEN(
                $this->class_fqsen
            );

        if ($clazz->isAbstract()) {
            if (!$this->context->hasClassFQSEN()
                || $clazz->getFQSEN() != $this->context->getClassFQSEN()) {
                Log::err(
                    Log::ETYPE,
                    "Cannot instantiate abstract class {$this->class_name}",
                    $this->context->getFile(),
                    $node->lineno
                );

                return false;
            }

            return true;
        }

        if ($clazz->isInterface()) {
            if (!UnionType::fromStringInContext(
                $this->class_name,
                $this->context
            )->isNativeType()) {
                Log::err(
                    Log::ETYPE,
                    "Cannot instantiate interface {$this->class_name}",
                    $this->context->getFile(),
                    $node->lineno
                );
                return false;
            }
        }

        return true;
    }

    public function visitInstanceOf(Node $node) : bool {
        return $this->classExistsOrIsNative($node);
    }

    public function visitClassConst(Node $node) : bool {
        return $this->classExistsOrIsNative($node);
    }

    public function visitStaticCall(Node $node) : bool {
        return $this->classExistsOrIsNative($node);
    }

    public function visitMethodCall(Node $node) : bool {
        return $this->classExistsOrIsNative($node);
    }

    public function visitProp(Node $node) : bool {
        return $this->classExistsOrIsNative($node);
    }

    public function visitStaticProp(Node $node) : bool {
        if (!$this->classExists()) {
            return $this->classExistsOrIsNative($node);
        }

        $clazz =
            $this->code_base->getClassByFQSEN(
                $this->class_fqsen
            );

        $property_name = $node->children['prop'];

        if (is_string($node->children['prop'])
            && !$clazz->hasPropertyWithName(
                $this->code_base,
                $node->children['prop']
            )
        ) {
            Log::err(
                Log::ETYPE,
                "Access to undeclared static property {$node->children['prop']} on {$this->class_name}",
                $this->context->getFile(),
                $node->lineno
            );
            return false;
        }

        return true;
    }

    /**
     * @return bool
     * False if this class cannot be found
     */
    private function classExists() : bool {
        return
            $this->code_base->hasClassWithFQSEN(
                $this->class_fqsen
            );
    }

    /**
     * @return bool
     * False if the class name doesn't point to a known class
     */
    private function classExistsOrIsNative(Node $node) : bool {
        if ($this->classExists()) {
            return true;
        }

        $type = UnionType::fromStringInContext(
            $this->class_name,
            $this->context
        );

        if($type->isNativeType()) {
            return true;
        }

        Issue::emit(
            Issue::UndeclaredClassReference,
            $this->context->getFile(),
            $node->lineno ?? 0,
            (string)$this->class_fqsen
        );

        return false;
    }

}
