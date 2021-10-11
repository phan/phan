<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use AssertionError;
use ast;
use ast\flags;
use ast\Node;
use Phan\Analysis\Analyzable;
use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\ElementContext;
use Phan\Language\FileRef;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Scope\ClosureScope;
use Phan\Language\Scope\FunctionLikeScope;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;
use Phan\Memoize;

/**
 * Phan's representation of a closure or global function.
 *
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 * @property FullyQualifiedFunctionName $fqsen
 */
class Func extends AddressableElement implements FunctionInterface
{
    use Analyzable;
    use Memoize;
    use FunctionTrait {
        getRepresentationForIssue as private getRepresentationForIssueInternal;
    }
    use ClosedScopeElement;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfied by this
     * typed structural element.
     *
     * @param int $flags
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     *
     * @param ?list<Parameter> $parameter_list
     * A list of parameters to set on this method
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedFunctionName $fqsen,
        $parameter_list
    ) {
        if ($fqsen->isClosure()) {
            $internal_scope = new ClosureScope(
                $context->getScope(),
                $fqsen
            );
        } else {
            $internal_scope = new FunctionLikeScope(
                $context->getScope(),
                $fqsen
            );
        }
        $context = $context->withScope($internal_scope);
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        // TODO: Is internal scope even necessary to track separately??
        $this->setInternalScope($internal_scope);

        if ($parameter_list !== null) {
            $this->setParameterList($parameter_list);
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
        Type $closure_scope_type,
        Node $node
    ): ?FullyQualifiedClassName {
        if ($node->kind !== ast\AST_CLOSURE) {
            return null;
        }
        if ($closure_scope_type->isNativeType()) {
            // TODO: Handle final internal classes (Can't call bindTo on those)
            // TODO: What about 'null' (for code planning to bindTo(null))
            // Emit an error
            Issue::maybeEmit(
                $code_base,
                $context,
                Issue::TypeInvalidClosureScope,
                $node->lineno ?? 0,
                (string)$closure_scope_type
            );
            return null;
        } else {
            // TODO: handle 'parent'?
            // TODO: Check if isInClassScope
            if ($closure_scope_type->isSelfType() || $closure_scope_type->isStaticType()) {
                // nothing to do.
                return null;
            }
        }

        return FullyQualifiedClassName::fromType($closure_scope_type);
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
    ): Func {
        // Create the skeleton function object from what
        // we know so far
        $func = new Func(
            $context,
            (string)$node->children['name'],
            UnionType::empty(),
            $node->flags,
            $fqsen,
            null
        );
        $doc_comment = $node->children['docComment'] ?? '';
        $func->setDocComment($doc_comment);

        // Parse the comment above the function to get
        // extra meta information about the function.
        $comment = Comment::fromStringInContext(
            $doc_comment,
            $code_base,
            $context,
            $node->lineno ?? 0,
            Comment::ON_FUNCTION
        );
        $func->setPhanFlags($func->getPhanFlags() | $comment->getPhanFlagsForFunc());

        // Defer adding params to the local scope for user functions. (FunctionTrait::addParamsToScopeOfFunctionOrMethod)
        // See PreOrderAnalysisVisitor->visitFuncDecl and visitClosure
        $func->setComment($comment);

        $element_context = new ElementContext($func);

        // @var list<Parameter>
        // The list of parameters specified on the
        // method
        $parameter_list = Parameter::listFromNode(
            $element_context,
            $code_base,
            $node->children['params']
        );
        $func->setParameterList($parameter_list);
        $func->setAttributeList(Attribute::fromNodeForAttributeList(
            $code_base,
            $element_context,
            $node->children['attributes'] ?? null
        ));

        // Redefine the function's internal scope to point to the new class before adding any variables to the scope.

        $closure_scope_option = $comment->getClosureScopeOption();
        if ($closure_scope_option->isDefined()) {
            $override_class_fqsen = self::getClosureOverrideFQSEN($code_base, $context, $closure_scope_option->get(), $node);
            if ($override_class_fqsen !== null) {
                // TODO: Allow Null?
                $scope = $func->getInternalScope();
                if (!($scope instanceof ClosureScope)) {
                    throw new AssertionError('Expected scope of a closure to be a ClosureScope');
                }
                $scope->overrideClassFQSEN($override_class_fqsen);
                $func->getContext()->setScope($scope);
            }
        }

        // Add each parameter to the scope of the function
        // NOTE: it's important to clone this,
        // because we don't want anything to modify the original Parameter
        foreach ($parameter_list as $parameter) {
            $func->getInternalScope()->addVariable(
                $parameter->cloneAsNonVariadic()
            );
        }

        if (!$func->isPHPInternal()) {
            // If the function is Analyzable, set the node so that
            // we can come back to it whenever we like and
            // rescan it
            $func->setNode($node);
        }
        foreach ($comment->getTemplateTypeList() as $template_type) {
            $func->getInternalScope()->addTemplateType($template_type);
        }

        // Keep an copy of the original parameter list, to check for fatal errors later on.
        $func->setRealParameterList($parameter_list);

        $required_parameter_count = self::computeNumberOfRequiredParametersForList($parameter_list);
        $func->setNumberOfRequiredParameters($required_parameter_count);

        $func->setNumberOfOptionalParameters(\count($parameter_list) - $required_parameter_count);

        // Check to see if the comment specifies that the
        // function is deprecated
        $func->setIsDeprecated($comment->isDeprecated());

        // Set whether or not the function is internal to
        // the namespace.
        $func->setIsNSInternal($comment->isNSInternal());

        // Set whether this function is pure.
        if ($comment->isPure()) {
            $func->setIsPure();
        }

        $func->setSuppressIssueSet(
            $comment->getSuppressIssueSet()
        );

        // Take a look at function return types
        if ($node->children['returnType'] !== null) {
            // Get the type of the parameter
            $union_type = UnionTypeVisitor::unionTypeFromNode(
                $code_base,
                $context,
                $node->children['returnType']
            );
            $func->setRealReturnType($union_type);

            $func->setUnionType($func->getUnionType()->withUnionType($union_type)->withRealTypeSet($union_type->getTypeSet()));
        }

        if ($comment->hasReturnUnionType()) {
            // See if we have a return type specified in the comment
            $union_type = $comment->getReturnType();

            // FIXME properly handle self/static in closures declared within methods.
            if ($union_type->hasSelfType()) {
                $union_type = $union_type->makeFromFilter(static function (Type $type): bool {
                    return !$type->isSelfType();
                });
                if ($context->isInClassScope()) {
                    $union_type = $union_type->withType(
                        $context->getClassFQSEN()->asType()
                    );
                } else {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::ContextNotObjectUsingSelf,
                        $comment->getReturnLineno(),
                        'self',
                        $fqsen
                    );
                }
            }

            $new_type = $func->getUnionType()->withUnionType($union_type)->withRealTypeSet($func->getRealReturnType()->getTypeSet());
            if ($union_type->hasRealTypeSet() && !$new_type->hasRealTypeSet()) {
                $new_type = $new_type->withRealTypeSet($union_type->getRealTypeSet());
            }
            $func->setUnionType($new_type);
            $func->setPHPDocReturnType($union_type);
        }
        $element_context->freeElementReference();

        $func->setOriginalReturnType();

        return $func;
    }

    /**
     * @suppress PhanTypeMismatchReturn FunctionInterface->Method
     */
    public function getFQSEN(): FullyQualifiedFunctionName
    {
        return $this->fqsen;
    }

    /**
     * @return \Generator
     * @phan-return \Generator<Func>
     * The set of all alternates to this function
     */
    public function alternateGenerator(CodeBase $code_base): \Generator
    {
        $alternate_id = 0;
        $fqsen = $this->fqsen;

        while ($code_base->hasFunctionWithFQSEN($fqsen)) {
            yield $code_base->getFunctionByFQSEN($fqsen);
            $fqsen = $fqsen->withAlternateId(++$alternate_id);
        }
    }

    /**
     * @return string
     * A string representation of this function signature
     */
    public function __toString(): string
    {
        $string = '';

        $string .= 'function ' . $this->name;

        $string .= '(' . \implode(', ', $this->getParameterList()) . ')';

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
    public function returnsRef(): bool
    {
        return $this->getFlagsHasState(flags\FUNC_RETURNS_REF);
    }

    /**
     * @return bool
     * True if this is a static closure or arrow func, such as `static fn() => $x`
     */
    public function isStatic(): bool
    {
        return $this->getFlagsHasState(flags\MODIFIER_STATIC);
    }

    /**
     * @return bool Always false for global functions.
     */
    public function isFromPHPDoc(): bool
    {
        return false;
    }

    /**
     * True if this is a closure
     */
    public function isClosure(): bool
    {
        return $this->fqsen->isClosure();
    }

    /**
     * Returns a string that can be used as a standalone PHP stub for this global function.
     * @suppress PhanUnreferencedPublicMethod (toStubInfo is used by callers for more flexibility)
     */
    public function toStub(): string
    {
        [$namespace, $string] = $this->toStubInfo();
        $namespace_text = $namespace === '' ? '' : "$namespace ";
        $string = \sprintf("namespace %s{\n%s}\n", $namespace_text, $string);
        return $string;
    }

    public function getMarkupDescription(): string
    {
        $fqsen = $this->fqsen;
        $namespace = \ltrim($fqsen->getNamespace(), '\\');
        $stub = '';
        if (StringUtil::isNonZeroLengthString($namespace)) {
            $stub = "namespace $namespace;\n";
        }
        $stub .= 'function ';
        if ($this->returnsRef()) {
            $stub .= '&';
        }
        $stub .= $fqsen->getName();

        $stub .= '(' . $this->getParameterStubText() . ')';

        $return_type = $this->getUnionType();
        if (!$return_type->isEmpty()) {
            $stub .= ': ' . (string)$return_type;
        }
        return $stub;
    }

    /**
     * Returns stub info for `tool/make_stubs`
     * @return array{0:string,1:string} [string $namespace, string $text]
     */
    public function toStubInfo(): array
    {
        $fqsen = $this->fqsen;
        $stub = '';
        if (self::shouldAddDescriptionsToStubs()) {
            $description = (string)MarkupDescription::extractDescriptionFromDocComment($this);
            $stub .= MarkupDescription::convertStringToDocComment($description);
        }
        $stub .= 'function ';
        if ($this->returnsRef()) {
            $stub .= '&';
        }
        $stub .= $fqsen->getName();

        $stub .= '(' . $this->getRealParameterStubText() . ')';

        $return_type = $this->real_return_type;
        if ($return_type && !$return_type->isEmpty()) {
            $stub .= ' : ' . (string)$return_type;
        }
        $stub .= " {}\n";
        $namespace = \ltrim($fqsen->getNamespace(), '\\');
        return [$namespace, $stub];
    }

    public function getUnionTypeWithUnmodifiedStatic(): UnionType
    {
        return $this->getUnionType();
    }

    /**
     * @return string
     * The fully-qualified structural element name of this
     * structural element (or something else for closures and callables)
     * @override
     */
    public function getRepresentationForIssue(bool $show_args = false): string
    {
        if ($this->isClosure()) {
            return $this->getStubForClosure();
        }
        return $this->getRepresentationForIssueInternal($show_args);
    }

    private function getStubForClosure(): string
    {
        $stub = 'Closure';
        if ($this->returnsRef()) {
            $stub .= '&';
        }
        $stub .= '(' . \implode(', ', \array_map(static function (Parameter $parameter): string {
            return $parameter->toStubString();
        }, $this->getRealParameterList())) . ')';
        if ($this->real_return_type && !$this->getRealReturnType()->isEmpty()) {
            $stub .= ' : ' . (string)$this->getRealReturnType();
        }
        return $stub;
    }

    /**
     * @return string
     * The name of this structural element (without namespace/class),
     * or a string for FunctionLikeDeclarationType (or a closure) which lacks a real FQSEN
     */
    public function getNameForIssue(): string
    {
        if ($this->isClosure()) {
            return $this->getStubForClosure();
        }
        return $this->name . '()';
    }

    /**
     * @override
     */
    public function addReference(FileRef $file_ref): void
    {
        if (Config::get_track_references()) {
            // Currently, we don't need to track references to PHP-internal methods/functions/constants
            // such as PHP_VERSION, strlen(), Closure::bind(), etc.
            // This may change in the future.
            if ($this->isPHPInternal()) {
                return;
            }
            if ($file_ref instanceof Context && $file_ref->isInFunctionLikeScope() && $file_ref->getFunctionLikeFQSEN() === $this->fqsen) {
                // Don't track functions calling themselves
                return;
            }
            $this->reference_list[$file_ref->__toString()] = $file_ref;
        }
    }
}
