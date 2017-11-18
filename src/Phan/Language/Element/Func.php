<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Scope\ClosureScope;
use Phan\Language\Scope\FunctionLikeScope;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Type;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use Phan\Library\None;
use Phan\Library\Option;
use ast\Node;

class Func extends AddressableElement implements FunctionInterface
{
    use \Phan\Analysis\Analyzable;
    use \Phan\Memoize;
    use FunctionTrait;
    use ClosedScopeElement;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedFunctionName $fqsen
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        if ($fqsen->isClosure()) {
            $this->setInternalScope(new ClosureScope(
                $context->getScope(),
                $fqsen
            ));
        } else {
            $this->setInternalScope(new FunctionLikeScope(
                $context->getScope(),
                $fqsen
            ));
        }
    }

    /**
     * If a Closure overrides the scope(class) it will be executed in (via doc comment)
     * then return a context with the new scope instead.
     *
     * @param CodeBase $code_base
     * @param Context $context - The outer context in which the closure was declared.
     *                           Either this (or a new context for the other class) will be returned.
     * @return ?FullyQualifiedClassName
     *
     * Postcondition: if return value !== null, then $Type is the type of a class which exists in the codebase.
     */
    private static function getClosureOverrideFQSEN(
        CodeBase $code_base,
        Context $context,
        Type $closure_scope,
        Node $node
    ) {
        if ($node->kind !== \ast\AST_CLOSURE) {
            return null;
        }
        if ($closure_scope->isNativeType()) {
            // TODO: Handle final internal classes (Can't call bindTo on those)
            // TODO: What about 'null' (for code planning to bindTo(null))
            // Emit an error
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::TypeInvalidClosureScope,
                $node->lineno ?? 0,
                (string)$closure_scope
            );
            return null;
        } else {
            // TODO: handle 'parent'?
            // TODO: Check if isInClassScope
            if ($closure_scope->isSelfType() || $closure_scope->isStaticType()) {
                // nothing to do.
                return null;
            }
        }

        $class_fqsen = $closure_scope->asFQSEN();
        if (!($class_fqsen instanceof FullyQualifiedClassName)) {
            // shouldn't happen
            return null;
        }

        return $class_fqsen;
    }


    /**
     * @param Context $context
     * The context in which the node appears
     *
     * @param CodeBase $code_base
     *
     * @param Node $node
     * An AST node representing a function
     *
     * @param FullyQualifiedFunctionName $fqsen
     * A fully qualified name for the function
     *
     * @return Func
     * A Func representing the AST node in the
     * given context
     */
    public static function fromNode(
        Context $context,
        CodeBase $code_base,
        Node $node,
        FullyQualifiedFunctionName $fqsen
    ) : Func {

        // Create the skeleton function object from what
        // we know so far
        $func = new Func(
            $context,
            (string)$node->children['name'],
            new UnionType(),
            $node->flags ?? 0,
            $fqsen
        );

        // Parse the comment above the function to get
        // extra meta information about the function.
        $comment = Comment::fromStringInContext(
            (string)$node->children['docComment'],
            $code_base,
            $context,
            $node->lineno ?? 0,
            Comment::ON_FUNCTION
        );

        // Redefine the function's internal scope to point to the new class before adding any variables to the scope.

        $closure_scope_option = $comment->getClosureScopeOption();
        if ($closure_scope_option->isDefined()) {
            $override_class_fqsen = self::getClosureOverrideFQSEN($code_base, $context, $closure_scope_option->get(), $node);
            if ($override_class_fqsen !== null) {
                // TODO: Allow Null?
                $scope = $func->getInternalScope();
                assert($scope instanceof ClosureScope);
                $scope->overrideClassFQSEN($override_class_fqsen);
                $func->getContext()->setScope($scope);
            }
        }


        // @var Parameter[]
        // The list of parameters specified on the
        // function
        $parameter_list = Parameter::listFromNode(
            $context,
            $code_base,
            $node->children['params']
        );

        // Add each parameter to the scope of the function
        foreach ($parameter_list as $parameter) {
            $func->getInternalScope()->addVariable(
                $parameter
            );
        }

        if (!$context->isPHPInternal()) {
            // If the function is Analyzable, set the node so that
            // we can come back to it whenever we like and
            // rescan it
            $func->setNode($node);
        }

        // Set the parameter list on the function
        $func->setParameterList($parameter_list);
        // Keep an copy of the original parameter list, to check for fatal errors later on.
        $func->setRealParameterList($parameter_list);

        $func->setNumberOfRequiredParameters(\array_reduce(
            $parameter_list,
            function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isRequired() ? 1 : 0));
            },
            0
        ));

        $func->setNumberOfOptionalParameters(\array_reduce(
            $parameter_list,
            function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isOptional() ? 1 : 0));
            },
            0
        ));

        // Check to see if the comment specifies that the
        // function is deprecated
        $func->setIsDeprecated($comment->isDeprecated());

        // Set whether or not the element is internal to
        // the namespace.
        $func->setIsNSInternal($comment->isNSInternal());

        $func->setSuppressIssueList(
            $comment->getSuppressIssueList()
        );

        // Take a look at function return types
        if ($node->children['returnType'] !== null) {
            // Get the type of the parameter
            $union_type = UnionType::fromNode(
                $context,
                $code_base,
                $node->children['returnType']
            );
            $func->setRealReturnType($union_type);

            $func->getUnionType()->addUnionType($union_type);
        }

        if ($comment->hasReturnUnionType()) {
            // See if we have a return type specified in the comment
            $union_type = $comment->getReturnType();

            \assert(
                !$union_type->hasSelfType(),
                "Function referencing self in $context"
            );

            $func->getUnionType()->addUnionType($union_type);
            $func->setPHPDocReturnType($union_type);
        }

        // Add params to local scope for user functions
        FunctionTrait::addParamsToScopeOfFunctionOrMethod($context, $code_base, $node, $func, $comment);

        return $func;
    }

    /**
     * @return FullyQualifiedFunctionName
     */
    public function getFQSEN() : FullyQualifiedFunctionName
    {
        return $this->fqsen;
    }

    /**
     * @return \Generator
     * The set of all alternates to this function
     */
    public function alternateGenerator(CodeBase $code_base) : \Generator
    {
        $alternate_id = 0;
        $fqsen = $this->getFQSEN();

        while ($code_base->hasFunctionWithFQSEN($fqsen)) {
            yield $code_base->getFunctionByFQSEN($fqsen);
            $fqsen = $fqsen->withAlternateId(++$alternate_id);
        }
    }

    /**
     * @return string
     * A string representation of this function signature
     */
    public function __toString() : string
    {
        $string = '';

        $string .= 'function ' . $this->getName();

        $string .= '(' . implode(', ', $this->getParameterList()) . ')';

        if (!$this->getUnionType()->isEmpty()) {
            $string .= ' : ' . (string)$this->getUnionType();
        }

        $string .= ';';

        return $string;
    }

    /**
     * @return bool
     * True if this function returns a reference
     */
    public function returnsRef() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\RETURNS_REF
        );
    }

    public function toStub() : string
    {
        list($namespace, $string) = $this->toStubInfo();
        $namespace_text = $namespace === '' ? '' : "$namespace ";
        $string = sprintf("namespace %s{\n%s}\n", $namespace_text, $string);
        return $string;
    }

    /** @return string[] [string $namespace, string $text] */
    public function toStubInfo() : array
    {
        $stub = 'function ';
        if ($this->returnsRef()) {
            $stub .= '&';
        }
        $stub .= $this->getName();
        $stub .= '(' . implode(', ', array_map(function (Parameter $parameter) : string {
            return $parameter->toStubString();
        }, $this->getRealParameterList())) . ')';
        if ($this->real_return_type && !$this->getRealReturnType()->isEmpty()) {
            $stub .= ' : ' . (string)$this->getRealReturnType();
        }

        $stub .= ' {}' . "\n";

        $namespace = ltrim($this->getFQSEN()->getNamespace(), '\\');
        return [$namespace, $stub];
    }
}
