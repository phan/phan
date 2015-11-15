<?php declare(strict_types=1);
namespace Phan\Analyze;

use \Phan\Debug;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
use \Phan\Log;
use \ast\Node;

/**
 * A visitor that can extract a class name from a few
 * types of nodes
 */
class ClassNameValidationVisitor
    extends KindVisitorImplementation
{
    /**
     * @var Context
     * The context of the current execution
     */
    private $context;

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
     */
    public function __construct(
        Context $context,
        string $class_name
    ) {
        $this->context = $context;
        $this->class_name = $class_name;

        // Compute the FQSEN based on the current context
        $this->class_fqsen =
            $this->context->getScopeFQSEN()->withClassName(
                $this->context,
                $this->class_name
            );
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     */
    public function visit(Node $node) : bool {
        Log::err(
            Log::EUNDEF,
            "Unknown node type",
            $this->context->getFile(),
            $node->lineno
        );
        return false;
    }

    public function visitNew(Node $node) : bool {
        if (!$this->classExists()) {
            return $this->classExistsOrIsNative($node);
        }

        $clazz =
            $this->context->getCodeBase()->getClassByFQSEN(
                $this->class_fqsen
            );

        if ($clazz->isAbstract()) {
            if ($clazz->getFQSEN() != $this->context->getClassFQSEN()) {
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
            )::isNativeType()) {
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

    /**
     * @return bool
     * False if this class cannot be found
     */
    private function classExists() : bool {
        return
            $this->context->getCodeBase()->hasClassWithFQSEN(
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

        Log::err(
            Log::EUNDEF,
            "call to undeclared class {$this->class_fqsen}",
            $this->context->getFile(),
            $node->lineno
        );

        return false;
    }

}
