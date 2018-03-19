<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Comment;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Parameter;
use Phan\Language\FileRef;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Scope\ClosedScope;
use Phan\Language\Type;
use Phan\Language\UnionType;
use Closure;
use ast\Node;

/**
 * @phan-file-suppress PhanPluginUnusedPublicMethodArgument
 */
abstract class FunctionLikeDeclarationType extends Type implements FunctionInterface
{
    // Subclasses will override this
    const NAME = '';

    /** @var FileRef */
    private $file_ref;

    /** @var array<int,ClosureDeclarationParameter> */
    private $params;

    /** @var UnionType */
    private $return_type;

    /** @var bool */
    private $returns_reference;

    // computed properties

    /** @var int see FunctionTrait */
    private $required_param_count;

    /** @var int see FunctionTrait */
    private $optional_param_count;

    private $is_variadic;
    // end computed properties

    /**
     * @param array<int,ClosureDeclarationParameter> $params
     * @param UnionType $return_type
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
    public function __toString() : string
    {
        return $this->memoize(__FUNCTION__, function () {
            $parts = [];
            foreach ($this->params as $value) {
                $parts[] = $value->__toString();
            }
            $return_type = $this->return_type;
            $return_type_string = $return_type->__toString();
            if ($return_type->typeCount() >= 2) {
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
    public function isCallable() : bool
    {
        return true;
    }

    /**
     * @return ?ClosureDeclarationParameter
     */
    public function getClosureParameterForArgument(int $i)
    {
        $result = $this->params[$i] ?? null;
        if (!$result) {
            return $this->is_variadic ? end($this->params) : null;
        }
        return $result;
    }

    public function canCastToNonNullableFunctionLikeDeclarationType(FunctionLikeDeclarationType $type) : bool
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
     * @override (Don't include \Closure in the expanded types. It interferes with type casting checking)
     */
    public function asExpandedTypes(
        CodeBase $unused_code_base,
        int $unused_recursion_depth = 0
    ) : UnionType {
        return $this->asUnionType();
    }

    // Begin FunctionInterface overrides. Most of these are intentionally no-ops
    /**
     * @override
     * @return void
     */
    public function addReference(FileRef $_)
    {
    }

    /** @override */
    public function getReferenceCount(CodeBase $_) : int
    {
        return 1;
    }

    /** @override */
    public function getReferenceList() : array
    {
        return [];
    }

    /** @override */
    public function isPrivate() : bool
    {
        return false;
    }

    /** @override */
    public function isProtected() : bool
    {
        return false;
    }

    /** @override */
    public function isPublic() : bool
    {
        return true;
    }

    /** @override */
    public function setFQSEN(FQSEN $_)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @override */
    public function alternateGenerator(CodeBase $_) : \Generator
    {
        yield $this;
    }

    /** @override */
    public function analyze(Context $context, CodeBase $_) : Context
    {
        return $context;
    }

    /** @override */
    public function analyzeFunctionCall(CodeBase $unused_code_base, Context $unused_context, array $_)
    {
        throw new \AssertionError('should not call ' . __METHOD__);
    }

    /** @override */
    public function analyzeWithNewParams(Context $unused_context, CodeBase $unused_codebase, array $unused_parameter_list) : Context
    {
        throw new \AssertionError('should not call ' . __METHOD__);
    }

    /** @override */
    public function appendParameter(Parameter $_)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @override */
    public function clearParameterList()
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @override */
    public function cloneParameterList()
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @override */
    public function ensureScopeInitialized(CodeBase $_)
    {
    }

    /** @override */
    public function asFunctionLikeDeclarationType() : FunctionLikeDeclarationType
    {
        return $this;
    }

    /** @override */
    public function getComment()
    {
        return null;
    }

    /** @override */
    public function getDependentReturnType(CodeBase $code_base, Context $context, array $args) : UnionType
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @override */
    public function hasDependentReturnType() : bool
    {
        return false;
    }

    // TODO: Maybe create mock FQSENs for these instead.
    /** @override */
    public function getElementNamespace() : string
    {
        return '\\';
    }

    /** @override */
    public function getFQSEN()
    {
        $hash = \substr(\md5($this->__toString()), 0, 12);
        return FullyQualifiedFunctionName::fromFullyQualifiedString('\\closure_phpdoc' . $hash);
    }

    /** @override */
    public function getRepresentationForIssue() : string
    {
        // Represent this as "Closure(int):void" in issue messages instead of \closure_phpdoc_abcd123456Df
        return $this->__toString();
    }

    /** @override */
    public function getHasReturn() : bool
    {
        return true;
    }

    /** @override */
    public function getInternalScope() : ClosedScope
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /** @return Node|null */
    public function getNode()
    {
        return null;
    }

    /** @override */
    public function getNumberOfRequiredParameters() : int
    {
        return $this->required_param_count;
    }

    /** @override */
    public function getNumberOfOptionalParameters() : int
    {
        return $this->optional_param_count;
    }

    /** @override */
    public function getNumberOfRequiredRealParameters() : int
    {
        return $this->required_param_count;
    }

    /** @override */
    public function getNumberOfOptionalRealParameters() : int
    {
        return $this->optional_param_count;
    }

    /** @override */
    public function getNumberOfParameters() : int
    {
        return $this->optional_param_count + $this->required_param_count;
    }

    /** @override */
    public function getOutputReferenceParamNames() : array
    {
        return [];
    }

    /** @override */
    public function getPHPDocParameterTypeMap()
    {
        // Implement?
        return [];
    }

    /** @override */
    public function getPHPDocReturnType()
    {
        return $this->return_type;
    }

    /**
     * @return Parameter|null
     * @override
     */
    public function getParameterForCaller(int $i)
    {
        $list = $this->params;
        if (count($list) === 0) {
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
     * @return array<int,Parameter>
     */
    public function getParameterList() : array
    {
        $result = [];
        foreach ($this->params as $i => $param) {
            $result[] = $param->asRegularParameter($i);
        }
        return $result;
    }

    public function getRealParameterList()
    {
        return $this->getParameterList();
    }

    public function getRealReturnType() : UnionType
    {
        return $this->return_type;
    }

    public function getThrowsUnionType() : UnionType
    {
        return UnionType::empty();
    }

    public function hasFunctionCallAnalyzer() : bool
    {
        return false;
    }

    public function isFromPHPDoc() : bool
    {
        return true;
    }

    public function isNSInternal(CodeBase $code_base) : bool
    {
        return false;
    }

    public function isNSInternalAccessFromContext(CodeBase $code_base, Context $context) : bool
    {
        return false;
    }

    public function isReturnTypeUndefined() : bool
    {
        return false;
    }

    public function needsRecursiveAnalysis() : bool
    {
        return false;
    }

    public function recordOutputReferenceParamName(string $parameter_name)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function returnsRef() : bool
    {
        return $this->returns_reference;
    }

    public function setComment(Comment $comment)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setFunctionCallAnalyzer(Closure $analyzer)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setDependentReturnTypeClosure(Closure $analyzer)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setHasReturn(bool $_)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setHasYield(bool $_)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setInternalScope(ClosedScope $scope)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setIsReturnTypeUndefined(bool $_)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setNumberOfOptionalParameters(int $_)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setNumberOfRequiredParameters(int $_)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function setPHPDocParameterTypeMap(array $parameter_map)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    /**
     * @param ?UnionType the raw phpdoc union type
     */
    public function setPHPDocReturnType($union_type)
    {
        throw new \AssertionError('unexpected call to ' . __METHOD__);
    }

    public function getContext() : Context
    {
        return (new Context())
            ->withFile($this->file_ref->getFile())
            ->withLineNumberStart($this->file_ref->getLineNumberStart());
    }

    public function getUnionType() : UnionType
    {
        return $this->return_type;
    }

    public function getSuppressIssueList() : array
    {
        // TODO: Inherit suppress issue list from phpdoc declaring this?
        return [];
    }

    public function hasSuppressIssue(string $issue_type) : bool
    {
        return in_array($issue_type, $this->getSuppressIssueList());
    }

    public function hydrate(CodeBase $_)
    {
    }

    public function incrementSuppressIssueCount(string $issue_name)
    {
    }

    public function isDeprecated() : bool
    {
        return false;
    }

    public function getFileRef() : FileRef
    {
        return $this->file_ref;
    }

    public function isPHPInternal() : bool
    {
        return false;
    }

    public function setIsDeprecated(bool $_)
    {
    }

    public function setSuppressIssueList(array $issues)
    {
        throw new \AssertionError('should not call ' . __METHOD__);
    }

    public function setUnionType(UnionType $type)
    {
        throw new \AssertionError('should not call ' . __METHOD__);
    }

    // End FunctionInterface overrides
}
