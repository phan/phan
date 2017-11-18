<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Config;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use ast\Node;

trait FunctionTrait
{

    /**
     * @return int
     */
    abstract public function getPhanFlags() : int;

    /**
     * @param int $phan_flags
     *
     * @return void
     */
    abstract public function setPhanFlags(int $phan_flags);

    /**
     * @return string
     * The (not fully-qualified) name of this element.
     */
    abstract public function getName() : string;

    /**
     * @return FQSEN
     * The fully-qualified structural element name of this
     * structural element
     */
    public abstract function getFQSEN();

    /**
     * @var int
     * The number of required parameters for the method
     */
    private $number_of_required_parameters = 0;

    /**
     * @var int
     * The number of optional parameters for the method.
     * Note that this is set to a large number in methods using varargs or func_get_arg*()
     */
    private $number_of_optional_parameters = 0;

    /**
     * @var int
     * The number of required (real) parameters for the method declaration.
     * For internal methods, ignores phan's annotations.
     */
    private $number_of_required_real_parameters = 0;

    /**
     * @var int
     * The number of optional (real) parameters for the method declaration.
     * For internal methods, ignores phan's annotations.
     * For user-defined methods, ignores presence of func_get_arg*()
     */
    private $number_of_optional_real_parameters = 0;

    /**
     * @var Parameter[]
     * The list of parameters for this method
     * This will change while the method is being analyzed when the config quick_mode is false.
     */
    private $parameter_list = [];

    /**
     * @var ?int
     * The hash of the types for the list of parameters for this function/method.
     */
    private $parameter_list_hash = null;

    /**
     * @var ?bool
     * Whether or not this function/method has any pass by reference parameters.
     */
    private $has_pass_by_reference_parameters = null;

    /**
     * @var int[]
     * If the types for a parameter list were checked,
     * this contains the recursion depth (smaller is earlier in recursion)
     */
    private $checked_parameter_list_hashes = [];

    /**
     * @var Parameter[]
     * The list of *real* (not from phpdoc) parameters for this method.
     * This does not change after initialization.
     */
    private $real_parameter_list = [];

    /**
     * @var UnionType[]
     * The list of unmodified *phpdoc* parameter types for this method.
     * This does not change after initialization.
     */
    private $phpdoc_parameter_type_map = [];

    /**
     * @var true[]
     * A set of parameter names that are output-only references
     */
    private $phpdoc_output_references = [];

    /**
     * @var ?UnionType
     * The unmodified *phpdoc* union type for this method.
     * Will be null without any (at)return statements.
     */
    private $phpdoc_return_type;

    /**
     * @var UnionType
     * The *real* (not from phpdoc) return type from this method.
     * This does not change after initialization.
     */
    private $real_return_type;

    /**
     * @var \Closure|null (CodeBase, Context, Func|Method $func, Node[]|string[]|int[] $arg_list) => UnionType
     */
    private $return_type_callback = null;

    /**
     * @var \Closure|null (CodeBase, Context, Func|Method $func, Node[]|string[]|int[] $arg_list) => void
     */
    private $function_call_analyzer_callback = null;

    /**
     * @return int
     * The number of optional real parameters on this function/method.
     * May differ from getNumberOfOptionalParameters()
     * for internal modules lacking proper reflection info,
     * or if the installed module version's API changed from what Phan's stubs used,
     * or if a function/method uses variadics/func_get_arg*()
     */
    public function getNumberOfOptionalRealParameters() : int
    {
        return $this->number_of_optional_real_parameters;
    }

    /**
     * @return int
     * The number of optional parameters on this method
     */
    public function getNumberOfOptionalParameters() : int
    {
        return $this->number_of_optional_parameters;
    }

    /**
     * The number of optional parameters
     *
     * @return void
     */
    public function setNumberOfOptionalParameters(int $number)
    {
        $this->number_of_optional_parameters = $number;
    }

    /**
     * @return int
     * The number of parameters in this function/method declaration.
     * Variadic parameters are counted only once.
     * TODO: Specially handle variadic parameters, either here or in ParameterTypesAnalyzer::analyzeOverrideRealSignature
     */
    public function getNumberOfRealParameters() : int
    {
        return (
            $this->getNumberOfRequiredRealParameters()
            + $this->getNumberOfOptionalRealParameters()
        );
    }

    /**
     * @return int
     * The maximum number of parameters to this function/method
     */
    public function getNumberOfParameters() : int
    {
        return (
            $this->getNumberOfRequiredParameters()
            + $this->getNumberOfOptionalParameters()
        );
    }

    /**
     * @return int
     * The number of required real parameters on this function/method.
     * May differ for internal modules lacking proper reflection info,
     * or if the installed module version's API changed from what Phan's stubs used.
     */
    public function getNumberOfRequiredRealParameters() : int
    {
        return $this->number_of_required_real_parameters;
    }

    /**
     * @return int
     * The number of required parameters on this function/method
     */
    public function getNumberOfRequiredParameters() : int
    {
        return $this->number_of_required_parameters;
    }

    /**
     *
     * The number of required parameters
     *
     * @return void
     */
    public function setNumberOfRequiredParameters(int $number)
    {
        $this->number_of_required_parameters = $number;
    }

    /**
     * @return bool
     * True if this method had no return type defined when it
     * was defined (either in the signature itself or in the
     * docblock).
     */
    public function isReturnTypeUndefined() : bool
    {
        return Flags::bitVectorHasState(
            $this->getPhanFlags(),
            Flags::IS_RETURN_TYPE_UNDEFINED
        );
    }

    /**
     * @param bool $is_return_type_undefined
     * True if this method had no return type defined when it
     * was defined (either in the signature itself or in the
     * docblock).
     *
     * @return void
     */
    public function setIsReturnTypeUndefined(
        bool $is_return_type_undefined
    ) {
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::IS_RETURN_TYPE_UNDEFINED,
            $is_return_type_undefined
        ));
    }

    /**
     * @return bool
     * True if this method returns a value
     * (i.e. it has a return with an expression)
     */
    public function getHasReturn() : bool
    {
        return Flags::bitVectorHasState(
            $this->getPhanFlags(),
            Flags::HAS_RETURN
        );
    }

    /**
     * @return bool
     * True if this method yields any value(i.e. it is a \Generator)
     */
    public function getHasYield() : bool
    {
        return Flags::bitVectorHasState(
            $this->getPhanFlags(),
            Flags::HAS_YIELD
        );
    }

    /**
     * @param bool $has_return
     * Set to true to mark this method as having a
     * return value
     *
     * @return void
     */
    public function setHasReturn(bool $has_return)
    {
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::HAS_RETURN,
            $has_return
        ));
    }

    /**
     * @param bool $has_yield
     * Set to true to mark this method as having a
     * yield value
     *
     * @return void
     */
    public function setHasYield(bool $has_yield)
    {
        // TODO: In a future release of php-ast, this information will be part of the function node's flags.
        // (PHP 7.1 only, not supported in PHP 7.0)
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::HAS_YIELD,
            $has_yield
        ));
    }

    /**
     * @return Parameter[]
     * A list of parameters on the method
     */
    public function getParameterList()
    {
        return $this->parameter_list;
    }

    /**
     * Gets the $ith parameter for the **caller**.
     * In the case of variadic arguments, an infinite number of parameters exist.
     * (The callee would see variadic arguments(T ...$args) as a single variable of type T[],
     * while the caller sees a place expecting an expression of type T.
     *
     * @param int $i - offset of the parameter.
     * @return Parameter|null The parameter type that the **caller** observes.
     */
    public function getParameterForCaller(int $i)
    {
        $list = $this->parameter_list;
        if (count($list) === 0) {
            return null;
        }
        $parameter = $list[$i] ?? null;
        if ($parameter) {
            return $parameter->asNonVariadic();
        }
        $lastParameter = $list[count($list) - 1];
        if ($lastParameter->isVariadic()) {
            return $lastParameter->asNonVariadic();
        }
        return null;
    }

    /**
     * @param Parameter[] $parameter_list
     * A list of parameters to set on this method
     * (When quick_mode is false, this is also called to temporarily
     * override parameter types, etc.)
     *
     * @return void
     */
    public function setParameterList(array $parameter_list)
    {
        $this->parameter_list = $parameter_list;
        if ($this->parameter_list_hash === null) {
            $this->initParameterListInfo();
        }
    }

    /**
     * Called to lazily initialize properties of $this derived from $this->parameter_list
     */
    private function initParameterListInfo()
    {
        $parameter_list = $this->parameter_list;
        $this->parameter_list_hash = self::computeParameterListHash($parameter_list);
        $has_pass_by_reference_parameters = false;
        foreach ($parameter_list as $param) {
            if ($param->isPassByReference()) {
                $has_pass_by_reference_parameters = true;
                break;
            }
        }
        $this->has_pass_by_reference_parameters = $has_pass_by_reference_parameters;
    }

    /**
     * Called to generate a hash of a given parameter list, to avoid calling this on the same parameter list twice.
     *
     * @return int 32-bit or 64-bit hash. Not likely to collide unless there are around 2^16 possible union types on 32-bit, or around 2^32 on 64-bit.
     *    (Collisions aren't a concern; The memory/runtime would probably be a bigger issue than collisions in non-quick mode.)
     */
    private static function computeParameterListHash(array $parameter_list) : int
    {
        // Choosing a small value to fit inside of a packed array.
        if (count($parameter_list) === 0) {
            return 0;
        }
        if (Config::get_quick_mode()) {
            return 0;
        }
        $param_repr = implode(',', array_map(function (Variable $param) {
            return (string)($param->getNonVariadicUnionType());
        }, $parameter_list));
        $raw_bytes = md5($param_repr, true);
        return unpack(PHP_INT_SIZE === 8 ? 'q' : 'l', $raw_bytes)[1];
    }

    /**
     * @return Parameter[] $parameter_list
     * A list of parameters (not from phpdoc) that were set on this method. The parameters will be cloned.
     */
    public function getRealParameterList()
    {
        // Excessive cloning, to ensure that this stays immutable.
        return array_map(function (Parameter $param) {
            return clone($param);
        }, $this->real_parameter_list);
    }

    /**
     * @param Parameter[] $parameter_list
     * A list of parameters (not from phpdoc) to set on this method. The parameters will be cloned.
     *
     * @return void
     */
    public function setRealParameterList(array $parameter_list)
    {
        $this->real_parameter_list = array_map(function (Parameter $param) {
            return clone($param);
        }, $parameter_list);

        $required_count = 0;
        $optional_count = 0;
        foreach ($parameter_list as $parameter) {
            if ($parameter->isOptional()) {
                $optional_count++;
            } else {
                $required_count++;
            }
        }
        $this->number_of_required_real_parameters = $required_count;
        $this->number_of_optional_real_parameters = $optional_count;
    }

    /**
     * @param UnionType
     * The real (non-phpdoc) return type of this method in its given context.
     *
     * @return void
     */
    public function setRealReturnType(UnionType $union_type)
    {
        // TODO: was `self` properly resolved already? What about in subclasses?
        // Clone it, since caller has a mutable version of this.
        $this->real_return_type = clone($union_type);
    }

    /**
     * @return UnionType
     * The type of this method in its given context.
     */
    public function getRealReturnType() : UnionType
    {
        if (!$this->real_return_type) {
            // Incomplete patch for https://github.com/phan/phan/issues/670
            return new UnionType();
            // throw new \Error(sprintf("Failed to get real return type in %s method %s", (string)$this->getClassFQSEN(), (string)$this));
        }
        // Clone the union type, to be certain it will remain immutable.
        $union_type = clone($this->real_return_type);
        return $union_type;
    }

    /**
     * @param Parameter $parameter
     * A parameter to append to the parameter list
     *
     * @return void
     */
    public function appendParameter(Parameter $parameter)
    {
        $this->parameter_list[] = $parameter;
    }

    /**
     * @return void
     *
     * Call this before calling appendParameter, if parameters were already added.
     */
    public function clearParameterList()
    {
        $this->parameter_list = [];
        $this->parameter_list_hash = null;
    }

    /**
     * Adds types from comments to the params of a user-defined function or method.
     * Also adds the types from defaults, and emits warnings for certain violations.
     *
     * Conceptually, Func and Method should have defaults/comments analyzed in the same way.
     *
     * This does nothing if $function is for an internal method.
     *
     * @param Context $context
     * The context in which the node appears
     *
     * @param CodeBase $code_base
     *
     * @param Node $node
     * An AST node representing a method
     *
     * @param FunctionInterface $function - A Func or Method to add params to the local scope of.
     *
     * @param Comment $comment - processed doc comment of $node, with params
     *
     * @return void
     */
    public static function addParamsToScopeOfFunctionOrMethod(
        Context $context,
        CodeBase $code_base,
        Node $node,
        FunctionInterface $function,
        Comment $comment
    ) {
        if ($function->isPHPInternal()) {
            return;
        }
        $parameter_offset = 0;
        $function_parameter_list = $function->getParameterList();
        $real_parameter_name_map = [];
        foreach ($function_parameter_list as $parameter) {
            $real_parameter_name_map[$parameter->getName()] = $parameter;
            self::addParamToScopeOfFunctionOrMethod(
                $context,
                $code_base,
                $node,
                $function,
                $comment,
                $parameter_offset,
                $parameter
            );
            ++$parameter_offset;
        }

        $valid_comment_parameter_type_map = [];
        foreach ($comment->getParameterMap() as $comment_parameter_name => $comment_parameter) {
            if (!\array_key_exists($comment_parameter_name, $real_parameter_name_map)) {
                Issue::maybeEmit(
                    $code_base,
                    $context,
                    count($real_parameter_name_map) > 0 ? Issue::CommentParamWithoutRealParam : Issue::CommentParamOnEmptyParamList,
                    $node->lineno ?? 0,
                    $comment_parameter_name,
                    (string)$function
                );
                continue;
            }
            // Record phpdoc types to check if they are narrower than real types, later.
            // Only keep non-empty types.
            $comment_parameter_type = $comment_parameter->getUnionType();
            if (!$comment_parameter_type->isEmpty()) {
                $valid_comment_parameter_type_map[$comment_parameter_name] = $comment_parameter_type;
            }
            if ($comment_parameter->isOutputReference()) {
                $real_parameter_name_map[$comment_parameter_name]->setIsOutputReference();
            }
        }
        $function->setPHPDocParameterTypeMap($valid_comment_parameter_type_map);
        // Special, for libraries which use this for to document variadic param lists.
    }

    /**
     * Internally used.
     * @return void
     */
    public static function addParamToScopeOfFunctionOrMethod(
        Context $context,
        CodeBase $code_base,
        Node $node,
        FunctionInterface $function,
        Comment $comment,
        int $parameter_offset,
        Parameter $parameter
    ) {
        if ($function->isPHPInternal()) {
            return;
        }
        $parameter_name = $parameter->getName();
        if ($parameter->getUnionType()->isEmpty()) {
            // If there is no type specified in PHP, check
            // for a docComment with @param declarations. We
            // assume order in the docComment matches the
            // parameter order in the code
            if ($comment->hasParameterWithNameOrOffset(
                $parameter_name,
                $parameter_offset
            )) {
                $comment_param = $comment->getParameterWithNameOrOffset(
                    $parameter_name,
                    $parameter_offset
                );
                $comment_param_type = $comment_param->getUnionType();
                if ($parameter->isVariadic() !== $comment_param->isVariadic()) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        $parameter->isVariadic() ? Issue::TypeMismatchVariadicParam : Issue::TypeMismatchVariadicComment,
                        $node->lineno ?? 0,
                        $comment_param->__toString(),
                        $parameter->__toString()
                    );
                }

                $parameter->addUnionType($comment_param_type);
            }
        }

        // If there's a default value on the parameter, check to
        // see if the type of the default is cool with the
        // specified type.
        if ($parameter->hasDefaultValue()) {
            $default_type = $parameter->getDefaultValueType();
            $default_is_null = $default_type->isType(NullType::instance(false));
            // If the default type isn't null and can't cast
            // to the parameter's declared type, emit an
            // issue.
            if (!$default_is_null) {
                if (!$default_type->canCastToUnionType(
                    $parameter->getUnionType()
                )) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::TypeMismatchDefault,
                        $node->lineno ?? 0,
                        (string)$parameter->getUnionType(),
                        $parameter_name,
                        (string)$default_type
                    );
                }
            }

            // If there are no types on the parameter, the
            // default shouldn't be treated as the one
            // and only allowable type.
            $wasEmpty = $parameter->getUnionType()->isEmpty();
            if ($wasEmpty) {
                // TODO: Errors on usage of ?mixed are poorly defined and greatly differ from phan's old behavior.
                // Consider passing $default_is_null once this is fixed.
                $parameter->addUnionType(
                    MixedType::instance(false)->asUnionType()
                );
            }

            // If we have no other type info about a parameter,
            // just because it has a default value of null
            // doesn't mean that is its type. Any type can default
            // to null
            if ($default_is_null) {
                // The parameter constructor or above check for wasEmpty already took care of null default case
            } else {
                if ($wasEmpty) {
                    $parameter->addUnionType($default_type);
                } else {
                    // Don't add both `int` and `?int` to the same set.
                    foreach ($default_type->getTypeSet() as $default_type_part) {
                        if (!$parameter->getNonvariadicUnionType()->hasType($default_type_part->withIsNullable(true))) {
                            $parameter->addType($default_type_part);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param UnionType[] maps a subset of param names to the unmodified phpdoc parameter types. May differ from real parameter types
     * @return void
     */
    public function setPHPDocParameterTypeMap(array $parameter_map)
    {
        $this->phpdoc_parameter_type_map = $parameter_map;
    }

    /**
     * Records the fact that $parameter_name is an output-only reference.
     * @param string $parameter_name
     * @return void
     */
    public function recordOutputReferenceParamName(string $parameter_name)
    {
        $this->phpdoc_output_references[] = $parameter_name;
    }

    /**
     * @return string[] list of output references. Usually empty.
     */
    public function getOutputReferenceParamNames() : array
    {
        return $this->phpdoc_output_references;
    }

    /**
     * @return UnionType[] maps a subset of param names to the unmodified phpdoc parameter types.
     */
    public function getPHPDocParameterTypeMap()
    {
        return $this->phpdoc_parameter_type_map;
    }

    /**
     * @param ?UnionType $type the raw phpdoc union type
     * @return void
     */
    public function setPHPDocReturnType($type)
    {
        $this->phpdoc_return_type = $type;
    }

    /**
     * @return ?UnionType the raw phpdoc union type
     */
    public function getPHPDocReturnType()
    {
        return $this->phpdoc_return_type;
    }

    /**
     * Returns true if the param list has an instance of PassByReferenceVariable
     * If it does, the method has to be analyzed even if the same parameter types were analyzed already
     */
    private function hasPassByReferenceVariable(array $parameter_list) : bool
    {
        // Common case: function doesn't have any references in parameter list
        if ($this->has_pass_by_reference_parameters === false) {
            return false;
        }
        foreach ($parameter_list as $param) {
            if ($param instanceof PassByReferenceVariable) {
                return true;
            }
        }
        return false;
    }

    /**
     * analyzeWithNewParams is called only when the quick_mode config is false.
     * The new types are inferred based on the caller's types.
     * As an optimization, this refrains from re-analyzing the method/function it has already been analyzed for those param types
     * (With an equal or larger remaining recursion depth)
     *
     * @param Variable[] $parameter_list
     */
    public function analyzeWithNewParams(Context $context, CodeBase $code_base, array $parameter_list) : Context
    {
        $hash = $this->computeParameterListHash($parameter_list);
        $has_pass_by_reference_variable = null;
        // Nothing to do, except if PassByReferenceVariable was used
        if ($hash === $this->parameter_list_hash) {
            if (!$this->hasPassByReferenceVariable($parameter_list)) {
                // Have to analyze pass by reference variables anyway
                return $context;
            }
            $has_pass_by_reference_variable = true;
        }
        // Check if we've already analyzed this method with those given types,
        // with as much or even more depth left in the recursion.
        // (getRecursionDepth() increases as the program recurses downward)
        $old_recursion_depth_for_hash = $this->checked_parameter_list_hashes[$hash] ?? null;
        $new_recursion_depth_for_hash = $this->getRecursionDepth();
        if ($old_recursion_depth_for_hash !== null) {
            if ($new_recursion_depth_for_hash >= $old_recursion_depth_for_hash) {
                if (!($has_pass_by_reference_variable ?? $this->hasPassByReferenceVariable($parameter_list))) {
                    return $context;
                }
                // Have to analyze pass by reference variables anyway
                $new_recursion_depth_for_hash = $old_recursion_depth_for_hash;
            }
        }
        // Record the fact that it has already been analyzed,
        // along with the depth of recursion so far.
        $this->checked_parameter_list_hashes[$hash] = $new_recursion_depth_for_hash;
        return $this->analyze($context, $code_base);
    }

    public abstract function analyze(Context $context, CodeBase $code_base) : Context;

    public abstract function getRecursionDepth() : int;

    /**
     * Returns true if the return type depends on the argument, and a plugin makes Phan aware of that.
     */
    public function hasDependentReturnType() : bool
    {
        return $this->return_type_callback !== null;
    }

    /**
     * Returns a union type based on $args_node and $context
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param \ast\Node[]|int[]|string[] $args
     */
    public function getDependentReturnType(CodeBase $code_base, Context $context, array $args) : UnionType
    {
        return ($this->return_type_callback)($code_base, $context, $this, $args);
    }

    /**
     * @return void
     */
    public function setDependentReturnTypeClosure(\Closure $closure)
    {
        $this->return_type_callback = $closure;
    }

    /**
     * Returns true if this function or method has additional analysis logic for invocations (From internal and user defined plugins)
     */
    public function hasFunctionCallAnalyzer() : bool
    {
        return $this->function_call_analyzer_callback !== null;
    }

    /**
     * Perform additional analysis logic for invocations (From internal and user defined plugins)
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param \ast\Node[]|int[]|string[] $args
     * @return void
     */
    public function analyzeFunctionCall(CodeBase $code_base, Context $context, array $args)
    {
        ($this->function_call_analyzer_callback)($code_base, $context, $this, $args);
    }

    /**
     * Make additional analysis logic of this function/method use $closure
     * If callers need to invoke multiple closures, they should pass in a closure to invoke multiple closures.
     * @return void
     */
    public function setFunctionCallAnalyzer(\Closure $closure)
    {
        $this->function_call_analyzer_callback = $closure;
    }
}
