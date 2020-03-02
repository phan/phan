<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use ast\Node;
use Closure;
use Generator;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\AddressableElementInterface;
use Phan\Language\Element\Comment;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Parameter;
use Phan\Language\FileRef;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Scope\ClosedScope;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;

/**
 * Phan's base class for representations of `callable(MyClass):MyOtherClass` and `Closure(MyClass):MyOtherClass`
 * @phan-file-suppress PhanUnusedPublicMethodParameter
 * @phan-pure
 */
abstract class FunctionLikeDeclarationType extends Type implements FunctionInterface
{
    // Subclasses will override this
    public const NAME = '';

    /**
     * The file and location where this function-like Type was declared.
     * (e.g. in a doc comment, as a closure, etc).
     * @var FileRef
     */
    private $file_ref;

    /**
     * Describes information that was parsed about the parameters of this function-like Type.
     * (Name and UnionType)
     * @var list<ClosureDeclarationParameter>
     */
    private $params;

    /**
     * The return type of this function-like Type.
     * @var UnionType
     */
    private $return_type;

    /**
     * Does this function-like type return a reference?
     * Currently only possible for real closures, not for callable declarations declared in phpdoc.
     * @var bool
     */
    private $returns_reference;

    // computed properties

    /**
     * @var int
     * The number of required parameters for this callable/closure
     */
    private $required_param_count;

    /**
     * @var int
     * The number of optional parameters for this callable/closure.
     * Note that this is set to a large number in methods using varargs.
     */
    private $optional_param_count;

    /**
     * Is this a function declaration variadic?
     * @var bool
     */
    private $is_variadic;
    // end computed properties

    /**
     * @param list<ClosureDeclarationParameter> $params
     * @param UnionType $return_type
     * @suppress PhanPluginUnknownObjectMethodCall TODO: Figure out how the type is getting overridden in PostOrderAnalysisVisitor->analyzeCallToFunctionLike
     */
    public function __construct(FileRef $file_ref, array $params, UnionType $return_type, bool $returns_reference, bool $is_nullable)
    {
        parent::__construct('\\', static::NAME, [], $is_nullable);
        $this->file_ref = FileRef::copyFileRef($file_ref);
        $this->params = $params;
        $this->return_type = $return_type;
        $this->returns_reference = $returns_reference;

        $required_param_count = 0;
        $optional_param_count = 0;
        // TODO: Warn about required after optional
        foreach ($params as $param) {
            if ($param->isOptional()) {
                $optional_param_count++;
                if ($param->isVariadic()) {
                    $this->is_variadic = true;
                    $optional_param_count = FunctionInterface::INFINITE_PARAMETERS - $required_param_count;
                    break;
                }
            } else {
                $required_param_count++;
            }
        }
        $this->required_param_count = $required_param_count;
        $this->optional_param_count = $optional_param_count;
    }

    /**
     * Used when serializing this type in union types.
     * @return string (e.g. "Closure(int,string&...):string[]")
     */
    public function __toString(): string
    {
        return $this->memoize(__FUNCTION__, function (): string {
            $parts = [];
            foreach ($this->params as $value) {
                $parts[] = $value->__toString();
            }
            $return_type = $this->return_type;
            $return_type_string = $return_type->__toString();
            if ($return_type->typeCount() >= 2 || \substr($return_type_string, -1) === ']') {
                $return_type_string = "($return_type_string)";
            }
            return ($this->is_nullable ? '?' : '') . static::NAME . '(' . \implode(',', $parts) . '):' . $return_type_string;
        });
    }

    public function __clone()
    {
        throw new \AssertionError('Should not clone ClosureTypeDeclaration');
    }

    /**
     * @return bool
     * True if this type is a callable or a Closure or a FunctionLikeDeclarationType
     */
    public function isCallable(): bool
    {
        return true;
    }

    /**
     * @return ?ClosureDeclarationParameter the parameter which the argument at the index $i would be passed in as
     */
    public function getClosureParameterForArgument(int $i): ?ClosureDeclarationParameter
    {
        $result = $this->params[$i] ?? null;
        if (!$result) {
            // @phan-suppress-next-line PhanPossiblyFalseTypeReturn is_variadic implies at least one parameter exists.
            return $this->is_variadic ? \end($this->params) : null;
        }
        return $result;
    }

    /**
     * Checks if this callable can cast to the other $type, ignoring whether these are nullable.
     *
     * It can be cast if this can be passed to any usage of $type and satisfy expectation about parameters and returned union types.
     *
     * -e.g. `Closure(mixed):SubClass` can be used when a `Closure(int):BaseClass` is expected.
     */
    public function canCastToNonNullableFunctionLikeDeclarationType(FunctionLikeDeclarationType $type): bool
    {
        if ($this->required_param_count > $type->required_param_count) {
            return false;
        }
        if ($this->getNumberOfParameters() < $type->getNumberOfParameters()) {
            return false;
        }
        if ($this->returns_reference !== $type->returns_reference) {
            return false;
        }
        // TODO: Allow nullable/null to cast to void?
        if (!$this->return_type->canCastToUnionType($type->return_type)) {
            return false;
        }
        foreach ($this->params as $i => $param) {
            $other_param = $type->getClosureParameterForArgument($i) ?? null;
            if (!$other_param) {
                break;
            }
            if (!$param->canCastToParameterIgnoringVariadic($other_param)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if this callable can cast to the other $type, ignoring whether these are nullable.
     *
     * It can be cast if this can be passed to any usage of $type and satisfy expectation about parameters and returned union types.
     *
     * -e.g. `Closure(mixed):T` can be used when a `Closure(int):\BaseClass` is expected.
     *
     * @see self::canCastToNonNullableType() - This is based on that.
     */
    protected function canCastToNonNullableTypeHandlingTemplates(Type $type, CodeBase $code_base): bool
    {
        if (parent::canCastToNonNullableTypeHandlingTemplates($type, $code_base)) {
            return true;
        }
        if (!($type instanceof FunctionLikeDeclarationType)) {
            return false;
        }
        if ($this->required_param_count > $type->required_param_count) {
            return false;
        }
        if ($this->getNumberOfParameters() < $type->getNumberOfParameters()) {
            return false;
        }
        if ($this->returns_reference !== $type->returns_reference) {
            return false;
        }
        // TODO: Allow nullable/null to cast to void?
        if (!$this->return_type->asExpandedTypes($code_base)->canCastToUnionTypeHandlingTemplates($type->return_type, $code_base)) {
            return false;
        }
        foreach ($this->params as $i => $param) {
            $other_param = $type->getClosureParameterForArgument($i) ?? null;
            if (!$other_param) {
                break;
            }
            if (!$param->canCastToParameterHandlingTemplatesIgnoringVariadic($other_param, $code_base)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @override (Don't include \Closure in the expanded types. It interferes with type casting checking)
     */
    public function asExpandedTypes(
        CodeBase $unused_code_base,
        int $unused_recursion_depth = 0
    ): UnionType {
        return $this->asPHPDocUnionType();
    }

    /**
     * @override (Don't include \Closure in the expanded types. It interferes with type casting checking)
     */
    public function asExpandedTypesPreservingTemplate(
        CodeBase $unused_code_base,
        int $unused_recursion_depth = 0
    ): UnionType {
        return $this->asPHPDocUnionType();
    }

    /**
     * @param bool $is_nullable
     * Set to true if the type should be nullable, else pass
     * false
     *
     * @return Type
     * A new type that is a copy of this type but with the
     * given nullability value.
     *
     * @override - Avoid calling make() , which is not compatible with FunctionLikeDeclarationType::__construct
     *             (E.g. from UnionType->asNormalizedTypes)
     */
    public function withIsNullable(bool $is_nullable): Type
    {
        if ($is_nullable === $this->is_nullable) {
            return $this;
        }
        return new static(
            $this->file_ref,
            $this->params,
            $this->return_type,
            $this->returns_reference,
            $is_nullable
        );
    }

    /**
     * Returns true if this contains a type that is definitely non-callable
     * e.g. returns true for false, array, int
     *      returns false for callable, array, object, iterable, T, etc.
     */
    public function isDefiniteNonCallableType(): bool
    {
        return false;
    }

    public function asFunctionInterfaceOrNull(CodeBase $unused_codebase, Context $unused_context): ?FunctionInterface
    {
        return $this;
    }

    /**
     * @return Generator<mixed,Type> (void => $inner_type)
     * @override
     */
    public function getReferencedClasses(): Generator
    {
        foreach ($this->params as $param) {
            yield from $param->getNonVariadicUnionType()->getReferencedClasses();
        }
        yield from $this->return_type->getReferencedClasses();
    }

    /**
     * Returns a generator that yields all types and subtypes in the phpdoc type set.
     *
     * For example, for the union type `MyClass[]|false`, 3 types will be generated: `MyClass[]`, `MyClass`, and `false`.
     * This does not deduplicate types.
     *
     * @return Generator<Type>
     */
    public function getTypesRecursively(): Generator
    {
        yield $this;
        foreach ($this->params as $param) {
            yield from $param->getNonVariadicUnionType()->getTypesRecursively();
        }

        yield from $this->return_type->getTypesRecursively();
    }

    ////////////////////////////////////////////////////////////////////////////////
    // Begin FunctionInterface overrides. Most of these are intentionally no-ops
    ////////////////////////////////////////////////////////////////////////////////

    /**
     * @override
     */
    public function addReference(FileRef $_): void
    {
    }

    /** @override */
    public function getReferenceCount(CodeBase $_): int
    {
        return 1;
    }

    /** @override */
    public function getReferenceList(): array
    {
        return [];
    }

    /** @override */
    public function isPrivate(): bool
    {
        return false;
    }

    /** @override */
    public function isProtected(): bool
    {
        return false;
    }

    /** @override */
    public function isPublic(): bool
    {
        return true;
    }

    /**
     * @return bool true if this element's visibility
     *                   is strictly more visible than $other (public > protected > private)
     */
    public function isStrictlyMoreVisibleThan(AddressableElementInterface $other): bool
    {
        return false;
    }

    /** @override */
    public function setFQSEN(FQSEN $_): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /**
     * @phan-return \Generator<static>
     * @override
     */
    public function alternateGenerator(CodeBase $_): Generator
    {
        yield $this;
    }

    /** @override */
    public function analyze(Context $context, CodeBase $_): Context
    {
        return $context;
    }

    /** @override */
    public function analyzeFunctionCall(CodeBase $code_base, Context $context, array $args, Node $node = null): void
    {
        throw new \AssertionError('should not call ' . __METHOD__);
    }

    /** @override */
    public function analyzeWithNewParams(Context $unused_context, CodeBase $unused_codebase, array $unused_parameter_list): Context
    {
        throw new \AssertionError('should not call ' . __METHOD__);
    }

    /** @override */
    public function appendParameter(Parameter $_): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @override */
    public function clearParameterList(): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @override */
    public function cloneParameterList(): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @override */
    public function ensureScopeInitialized(CodeBase $_): void
    {
    }

    /** @override */
    public function asFunctionLikeDeclarationType(): FunctionLikeDeclarationType
    {
        return $this;
    }

    /** @override */
    public function getComment(): ?Comment
    {
        return null;
    }

    /** @override */
    public function getDeprecationReason(): string
    {
        return '';
    }

    /** @override */
    public function getDependentReturnType(CodeBase $code_base, Context $context, array $args): UnionType
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @override */
    public function hasDependentReturnType(): bool
    {
        return false;
    }

    // TODO: Maybe create mock FQSENs for these instead.
    /** @override */
    public function getElementNamespace(): string
    {
        return '\\';
    }

    /** @override */
    public function getFQSEN()
    {
        $hash = \substr(\md5($this->__toString()), 0, 12);
        // @phan-suppress-next-line PhanThrowTypeAbsentForCall this is valid
        return FullyQualifiedFunctionName::fromFullyQualifiedString('\\closure_phpdoc' . $hash);
    }

    /** @override */
    public function getRepresentationForIssue(bool $unused_show_args = false): string
    {
        // Represent this as "Closure(int):void" in issue messages instead of \closure_phpdoc_abcd123456Df
        return $this->__toString();
    }

    /** @override */
    public function getNameForIssue(): string
    {
        // Represent this as "Closure(int):void" in issue messages instead of \closure_phpdoc_abcd123456Df
        return $this->__toString();
    }

    /** @override */
    public function hasReturn(): bool
    {
        return true;
    }

    /** @override */
    public function getInternalScope(): ClosedScope
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function getNode(): ?Node
    {
        return null;
    }

    /** @override */
    public function getNumberOfRequiredParameters(): int
    {
        return $this->required_param_count;
    }

    /** @override */
    public function getNumberOfOptionalParameters(): int
    {
        return $this->optional_param_count;
    }

    /** @override */
    public function getNumberOfRequiredRealParameters(): int
    {
        return $this->required_param_count;
    }

    /** @override */
    public function getNumberOfOptionalRealParameters(): int
    {
        return $this->optional_param_count;
    }

    /** @override */
    public function getNumberOfParameters(): int
    {
        return $this->optional_param_count + $this->required_param_count;
    }

    /** @override */
    public function getOutputReferenceParamNames(): array
    {
        return [];
    }

    /** @override */
    public function getPHPDocParameterTypeMap(): array
    {
        // Implement?
        return [];
    }

    /** @override */
    public function getPHPDocReturnType(): ?UnionType
    {
        return $this->return_type;
    }

    /**
     * @override
     */
    public function getParameterForCaller(int $i): ?Parameter
    {
        $list = $this->params;
        if (\count($list) === 0) {
            return null;
        }
        $parameter = $list[$i] ?? null;
        if ($parameter) {
            // This is already not variadic
            return $parameter->asNonVariadicRegularParameter($i);
        }
        return null;
    }

    /**
     * @override
     */
    public function getRealParameterForCaller(int $i): ?Parameter
    {
        // FunctionLikeDeclarationType doesn't know if the phpdoc type is the real union type.
        //
        // This could instead call setUnionType and setDefaultValueType to the empty union type to avoid false positives about passing in null,
        // but would miss some actual bugs.
        return $this->getParameterForCaller($i);
    }

    /**
     * @return list<Parameter>
     */
    public function getParameterList(): array
    {
        $result = [];
        foreach ($this->params as $i => $param) {
            $result[] = $param->asRegularParameter($i);
        }
        return $result;
    }

    public function getRealParameterList(): array
    {
        return $this->getParameterList();
    }

    public function getRealReturnType(): UnionType
    {
        return $this->return_type;
    }

    public function getThrowsUnionType(): UnionType
    {
        return UnionType::empty();
    }

    public function hasFunctionCallAnalyzer(): bool
    {
        return false;
    }

    public function isFromPHPDoc(): bool
    {
        return true;
    }

    public function isNSInternal(CodeBase $code_base): bool
    {
        return false;
    }

    public function isNSInternalAccessFromContext(CodeBase $code_base, Context $context): bool
    {
        return false;
    }

    public function isReturnTypeUndefined(): bool
    {
        return false;
    }

    public function needsRecursiveAnalysis(): bool
    {
        return false;
    }

    public function recordOutputReferenceParamName(string $parameter_name): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function returnsRef(): bool
    {
        return $this->returns_reference;
    }

    /**
     * @unused
     */
    public function setComment(Comment $comment): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setFunctionCallAnalyzer(Closure $analyzer): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function addFunctionCallAnalyzer(Closure $analyzer): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setDependentReturnTypeClosure(Closure $analyzer): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setHasReturn(bool $_): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setHasYield(bool $_): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function hasYield(): bool
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setInternalScope(ClosedScope $scope): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setIsReturnTypeUndefined(bool $_): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setNumberOfOptionalParameters(int $_): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setNumberOfRequiredParameters(int $_): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setPHPDocParameterTypeMap(array $parameter_map): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /**
     * @param ?UnionType $union_type the raw phpdoc union type
     */
    public function setPHPDocReturnType(?UnionType $union_type): void
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function getContext(): Context
    {
        return (new Context())
            ->withFile($this->file_ref->getFile())
            ->withLineNumberStart($this->file_ref->getLineNumberStart());
    }

    public function getUnionType(): UnionType
    {
        return $this->return_type;
    }

    public function getUnionTypeWithUnmodifiedStatic(): UnionType
    {
        return $this->return_type;
    }

    public function getOriginalReturnType(): UnionType
    {
        return $this->return_type;
    }

    public function getSuppressIssueList(): array
    {
        // TODO: Inherit suppress issue list from phpdoc declaring this?
        return [];
    }

    public function hasSuppressIssue(string $issue_type): bool
    {
        return \in_array($issue_type, $this->getSuppressIssueList(), true);
    }

    public function checkHasSuppressIssueAndIncrementCount(string $issue_type): bool
    {
        // helpers are no-ops right now
        return false;
    }

    public function hydrate(CodeBase $_): void
    {
    }

    public function incrementSuppressIssueCount(string $issue_name): void
    {
    }

    public function isDeprecated(): bool
    {
        return false;
    }

    public function getFileRef(): FileRef
    {
        return $this->file_ref;
    }

    public function isPHPInternal(): bool
    {
        return false;
    }

    public function setIsDeprecated(bool $_): void
    {
    }

    /**
     * @param list<string> $suppress_issue_list
     */
    public function setSuppressIssueSet(array $suppress_issue_list): void
    {
        throw new \AssertionError('should not call ' . __METHOD__);
    }

    public function setUnionType(UnionType $type): void
    {
        throw new \AssertionError('should not call ' . __METHOD__);
    }

    /**
     * @return array<mixed,string> in the same format as FunctionSignatureMap.php
     * @override (Unused, but part of the interface)
     */
    public function toFunctionSignatureArray(): array
    {
        // no need for returns ref yet
        $return_type = $this->return_type;
        $stub = [$return_type->__toString()];
        foreach ($this->params as $i => $parameter) {
            $name = "p$i";
            if ($parameter->isOptional()) {
                $name .= '=';
            }
            $type_string = $parameter->getNonVariadicUnionType()->__toString();
            if ($parameter->isPassByReference()) {
                $type_string .= '&';
            }
            if ($parameter->isVariadic()) {
                $type_string .= '...';
            }
            $stub[$name] = $type_string;
        }
        return $stub;
    }

    public function getReturnTypeAsGeneratorTemplateType(): Type
    {
        // Probably unused
        // @phan-suppress-next-line PhanThrowTypeAbsentForCall
        return Type::fromFullyQualifiedString('\Generator');
    }

    public function getDocComment(): ?string
    {
        return null;
    }

    public function getMarkupDescription(): string
    {
        $parts = $this->toFunctionSignatureArray();
        $return_type = $parts[0];
        unset($parts[0]);

        $fragments = [];
        foreach ($parts as $name => $signature) {
            $fragment = '\$' . $name;
            if (StringUtil::isNonZeroLengthString($signature)) {
                $fragment = "$signature $fragment";
            }
        }
        $signature = static::NAME . '(' . \implode(',', $fragments) . ')';
        if (StringUtil::isNonZeroLengthString($return_type)) {
            // TODO: Make this unambiguous
            $signature .= ':' . $return_type;
        }
        return $signature;
    }

    public function analyzeReturnTypes(CodeBase $unused_code_base): void
    {
        // do nothing
    }

    public function declaresTemplateTypeInComment(TemplateType $template_type): bool
    {
        // not supported yet
        return false;
    }

    /**
     * Returns true for `T` and `T[]` and `\MyClass<T>`, but not `\MyClass<\OtherClass>` or `false`
     */
    public function hasTemplateTypeRecursive(): bool
    {
        if ($this->return_type->hasTemplateTypeRecursive()) {
            return true;
        }
        foreach ($this->params as $param) {
            if ($param->getNonVariadicUnionType()->hasTemplateTypeRecursive()) {
                return true;
            }
        }
        return false;
    }

    public function getTemplateTypeExtractorClosure(CodeBase $code_base, TemplateType $template_type): ?Closure
    {
        // Create a closure to extract types for the template type from the return type and param types.
        $closure = $this->getReturnTemplateTypeExtractorClosure($code_base, $template_type);
        foreach ($this->params as $i => $param) {
            $param_closure = $param->getNonVariadicUnionType()->getTemplateTypeExtractorClosure($code_base, $template_type);
            if (!$param_closure) {
                continue;
            }
            $closure = TemplateType::combineParameterClosures(
                $closure,
                static function (UnionType $union_type, Context $context) use ($code_base, $i, $param_closure): UnionType {
                    $result = UnionType::empty();
                    foreach ($union_type->getTypeSet() as $type) {
                        $func = $type->asFunctionInterfaceOrNull($code_base, $context);
                        if (!$func) {
                            continue;
                        }
                        $param = $func->getParameterForCaller($i);
                        if ($param) {
                            $result = $result->withUnionType($param_closure(
                                $param->getNonVariadicUnionType(),
                                $context
                            ));
                        }
                    }
                    return $result;
                }
            );
        }
        return $closure;
    }

    /**
     * Extracts a closure to extract the template type from the return type, or returns null
     * @return ?Closure(UnionType,Context):UnionType
     */
    private function getReturnTemplateTypeExtractorClosure(CodeBase $code_base, TemplateType $template_type): ?Closure
    {
        $return_closure = $this->getUnionType()->getTemplateTypeExtractorClosure($code_base, $template_type);
        if (!$return_closure) {
            return null;
        }
        return static function (UnionType $union_type, Context $context) use ($code_base, $return_closure): UnionType {
            $result = UnionType::empty();
            foreach ($union_type->getTypeSet() as $type) {
                $func = $type->asFunctionInterfaceOrNull($code_base, $context);
                if ($func) {
                    $result = $result->withUnionType($return_closure($func->getUnionType(), $context));
                }
            }
            return $result;
        };
    }

    /**
     * @param array<string,UnionType> $template_parameter_type_map
     */
    public function withTemplateParameterTypeMap(
        array $template_parameter_type_map
    ): UnionType {
        $new_params = \array_map(static function (ClosureDeclarationParameter $param) use ($template_parameter_type_map): ClosureDeclarationParameter {
            return $param->withTemplateParameterTypeMap($template_parameter_type_map);
        }, $this->params);
        $new_return_type = $this->return_type->withTemplateParameterTypeMap($template_parameter_type_map);
        if ($new_params === $this->params && $new_return_type === $this->return_type) {
            // no change
            return $this->asPHPDocUnionType();
        }
        // Create ClosureDeclarationType or CallableDeclarationType
        return (new static($this->file_ref, $new_params, $new_return_type, $this->returns_reference, $this->is_nullable))->asPHPDocUnionType();
    }

    public function getCommentParamAssertionClosure(CodeBase $code_base): ?Closure
    {
        return null;
    }

    public function setIsPure(): void
    {
        // no-op
    }

    public function isPure(): bool
    {
        return false;
    }

    public function getVariableTypeFallbackMap(CodeBase $code_base): array
    {
        return [];
    }

    ////////////////////////////////////////////////////////////////////////////////
    // End FunctionInterface overrides
    ////////////////////////////////////////////////////////////////////////////////
}
