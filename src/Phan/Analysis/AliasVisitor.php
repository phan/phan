<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use ast\Node;

/**
 * The class is a visitor for AST nodes that tracks class
 * aliases. Each visitor populates the $code_base with any
 * top-level class aliases.
 */
class AliasVisitor extends ScopeVisitor
{
    /**
     * Visit a node with kind `\ast\AST_CALL`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCall(Node $node) : Context
    {
        $expression = $node->children['expr'];

        if ($expression->kind !== \ast\AST_NAME
            || $expression->children['name'] !== 'class_alias'
            || !$this->context->isInGlobalScope()
        ) {
            return $this->context;
        }

        $args = $node->children['args'];
        if ($args->kind !== \ast\AST_ARG_LIST
            || !isset($args->children[0])
            || !isset($args->children[1])
        ) {
            return $this->context;
        }

        try {
            $original_fqsen = $this->resolveArgument($args->children[0]);
            $alias_fqsen = $this->resolveArgument($args->children[1]);
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
            return $this->context;
        }

        if ($original_fqsen === null || $alias_fqsen === null) {
            return $this->context;
        }

        if (!$this->code_base->hasClassWithFQSEN($original_fqsen)) {
            $this->emitIssue(
                Issue::UndeclaredClass,
                $node->lineno ?? 0,
                $args->children[0]
            );
        } else if ($this->code_base->hasClassWithFQSEN($alias_fqsen)) {
            $clazz = $this->code_base->getClassByFQSEN($alias_fqsen);
            $this->emitIssue(
                Issue::RedefineClass,
                $node->lineno ?? 0,
                $args->children[1],
                $this->context->getFile(),
                $node->lineno ?? 0,
                (string)$clazz,
                $clazz->getFileRef()->getFile(),
                $clazz->getFileRef()->getLineNumberStart()
            );
        } else {
            $this->code_base->addClassAlias($original_fqsen, $alias_fqsen);
        }

        return $this->context;
    }

    /**
     * @param mixed $arg
     * A function argument to resolve into an FQSEN
     *
     * @return ?FullyQualifiedClassName
     */
    private function resolveArgument($arg)
    {
        if (is_string($arg)) {
            return FullyQualifiedClassName::fromFullyQualifiedString($arg);
        }
        if ($arg instanceof Node
            && $arg->kind === \ast\AST_CLASS_CONST
        ) {
            $constant = (new ContextNode(
                $this->code_base,
                $this->context,
                $arg
            ))->getClassConst();

            if (strtolower($constant->getName()) === 'class') {
                return $constant->getClassFQSEN();
            }
        }

        return null;
    }
}
