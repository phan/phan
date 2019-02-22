<?php declare(strict_types=1);

namespace Phan\Language\Element;

use ast\Node;
use Closure;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope\ClosedScope;
use Phan\Language\Type;
use Phan\Language\Type\FunctionLikeDeclarationType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;

/**
 * Interface defining the behavior of both Methods and Functions
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
interface FunctionInterface extends AddressableElementInterface
{
    /**
     * An easy workaround to mark a function-like as accepting an infinite number of optional parameters
     * TODO: Distinguish between __call and __callStatic invoked manually and via magic (See uses of this constant)
     */
    const INFINITE_PARAMETERS = 999999;

    /**
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN();

    /**
     * @return string
     * The fully-qualified structural element name of this
     * structural element, or a string for FunctionLikeDeclarationType which lacks a real FQSEN
     */
    public function getRepresentationForIssue() : string;

    /**
     * @return string
     * The name of this structural element (without namespace/class),
     * or a string for FunctionLikeDeclarationType (or a closure) which lacks a real FQSEN
     */
    public function getNameForIssue() : string;

    /**
     * Sets the scope within this function-like element's body,
     * for tracking variables within the function-like.
     * @return void
     */
    public function setInternalScope(ClosedScope $internal_scope);

    /**
     * @return ClosedScope
     * The internal scope of this closed scope element
     */
    public function getInternalScope() : ClosedScope;

    /**
     * @return int
     * The number of optional parameters on this method
     */
    public function getNumberOfOptionalParameters() : int;

    /**
     * The number of optional parameters
     *
     * @return void
     */
    public function setNumberOfOptionalParameters(int $number);

    /**
     * @return int
     * The number of optional real parameters on this function/method.
     * This may differ from getNumberOfOptionalParameters()
     * for internal modules lacking proper reflection info,
     * or if the installed module version's API changed from what Phan's stubs used,
     * or if a function/method uses variadics/func_get_arg*()
     */
    public function getNumberOfOptionalRealParameters() : int;

    /**
     * @return int
     * The maximum number of parameters to this method
     */
    public function getNumberOfParameters() : int;

    /**
     * @return int
     * The number of required parameters on this method
     */
    public function getNumberOfRequiredParameters() : int;

    /**
     * The number of required parameters
     *
     * @return void
     */
    public function setNumberOfRequiredParameters(int $number);

    /**
     * @return int
     * The number of required real parameters on this function/method.
     * This may differ for internal modules lacking proper reflection info,
     * or if the installed module version's API changed from what Phan's stubs used.
     */
    public function getNumberOfRequiredRealParameters() : int;

    /**
     * @return bool
     * True if this method had no return type defined when it
     * was defined (either in the signature itself or in the
     * docblock).
     */
    public function isReturnTypeUndefined() : bool;

    /**
     * Sets whether this method had no return type defined when it
     * was defined (either in the signature itself or in the
     * docblock).
     *
     * @param bool $is_return_type_undefined
     * True if it was undefined
     *
     * @return void
     */
    public function setIsReturnTypeUndefined(
        bool $is_return_type_undefined
    );

    /**
     * @return bool
     * True if this method returns a value
     */
    public function getHasReturn() : bool;
    /**
     * @param bool $has_return
     * Set to true to mark this method as having a
     * return value (Only through `return`)
     *
     * @return void
     */
    public function setHasReturn(bool $has_return);

    /**
     * @param bool $has_yield
     * Set to true to mark this method as having a
     * yield statement (Only through `yield`)
     * This implies that it has a return value of \Generator.
     * (or a parent interface)
     *
     * @return void
     */
    public function setHasYield(bool $has_yield);

    /**
     * @return array<int,Parameter>
     * A list of parameters on the method
     */
    public function getParameterList();

    /**
     * Gets the $ith parameter for the **caller**.
     * In the case of variadic arguments, an infinite number of parameters exist.
     * (The callee would see variadic arguments(T ...$args) as a single variable of type T[],
     * while the caller sees a place expecting an expression of type T.
     *
     * @param int $i - offset of the parameter.
     * @return Parameter|null The parameter type that the **caller** observes.
     */
    public function getParameterForCaller(int $i);

    /**
     * Gets the $ith real parameter for the **caller**.
     * In the case of variadic arguments, an infinite number of parameters exist.
     * (The callee would see variadic arguments(T ...$args) as a single variable of type T[],
     * while the caller sees a place expecting an expression of type T.
     *
     * @param int $i - offset of the parameter.
     * @return Parameter|null The parameter type that the **caller** observes.
     */
    public function getRealParameterForCaller(int $i);

    /**
     * @param Parameter $parameter
     * A parameter to append to the parameter list
     *
     * @return void
     */
    public function appendParameter(Parameter $parameter);

    /**
     * @return void
     *
     * Call this before calling appendParameter, if parameters were already added.
     */
    public function clearParameterList();

    /**
     * Records the fact that $parameter_name is an output-only reference.
     * @param string $parameter_name
     * @return void
     */
    public function recordOutputReferenceParamName(string $parameter_name);

    /**
     * @return array<int,string> list of output references (annotated with (at)phan-output-reference. Usually empty.
     */
    public function getOutputReferenceParamNames() : array;

    /**
     * @return \Generator
     * The set of all alternates to this function
     */
    public function alternateGenerator(CodeBase $code_base) : \Generator;

    /**
     * @param CodeBase $code_base
     * The code base in which this element exists.
     *
     * @return bool
     * True if this is marked as an element internal to
     * its namespace
     */
    public function isNSInternal(CodeBase $code_base) : bool;

    /**
     * @param CodeBase $code_base
     * The code base in which this element exists.
     *
     * @return bool
     * True if this element is internal to the namespace
     */
    public function isNSInternalAccessFromContext(
        CodeBase $code_base,
        Context $context
    ) : bool;

    /**
     * @return Context
     * Analyze the node associated with this object
     * in the given context
     */
    public function analyze(Context $context, CodeBase $code_base) : Context;

    /**
     * @return Context
     * Analyze the node associated with this object
     * in the given context.
     * This function's parameter list may or may not have been modified.
     * @param array<int,Parameter> $parameter_list
     */
    public function analyzeWithNewParams(Context $context, CodeBase $code_base, array $parameter_list) : Context;

    /**
     * @return string the namespace in which this function interface was declared.
     *
     * Used for checking (at)internal annotations, etc.
     */
    public function getElementNamespace() : string;

    /**
     * @return UnionType
     * The type of this method in its given context.
     */
    public function getRealReturnType() : UnionType;

    /**
     * @return array<int,Parameter>
     * A list of parameters on the method, with types from the method signature.
     */
    public function getRealParameterList();

    /**
     * @param array<string,UnionType> $parameter_map maps a subset of param names to the unmodified phpdoc parameter types.
     * Will differ from real parameter types (ideally narrower)
     * @return void
     */
    public function setPHPDocParameterTypeMap(array $parameter_map);

    /**
     * @return array<string,UnionType> maps a subset of param names to the unmodified phpdoc parameter types.
     */
    public function getPHPDocParameterTypeMap();

    /**
     * @param ?UnionType $union_type the raw phpdoc union type
     * @return void
     */
    public function setPHPDocReturnType($union_type);

    /**
     * @return ?UnionType the raw phpdoc union type
     */
    public function getPHPDocReturnType();

    /**
     * @return bool
     * True if this function or method returns a reference
     */
    public function returnsRef() : bool;

    /**
     * Returns true if the return type depends on the argument, and a plugin makes Phan aware of that.
     */
    public function hasDependentReturnType() : bool;

    /**
     * Returns a union type based on $args_node and $context
     * @param CodeBase $code_base
     * @param Context $context
     * @param array<int,Node|int|string|float> $args
     */
    public function getDependentReturnType(CodeBase $code_base, Context $context, array $args) : UnionType;

    /**
     * Make calculation of the return type of this function/method use $closure
     * @return void
     */
    public function setDependentReturnTypeClosure(\Closure $closure);

    /**
     * Returns true if this function or method has additional analysis logic for invocations (From internal and user defined plugins)
     * @see getDependentReturnType
     */
    public function hasFunctionCallAnalyzer() : bool;

    /**
     * Perform additional analysis logic for invocations (From internal and user defined plugins)
     *
     * @param CodeBase $code_base
     * @param Context $context
     * @param array<int,Node|int|string> $args
     * @return void
     */
    public function analyzeFunctionCall(CodeBase $code_base, Context $context, array $args);

    /**
     * Make additional analysis logic of this function/method use $closure
     * If callers need to invoke multiple closures, they should pass in a closure to invoke multiple closures or use addFunctionCallAnalyzer.
     * @return void
     */
    public function setFunctionCallAnalyzer(\Closure $closure);

    /**
     * If callers need to invoke multiple closures, they should pass in a closure to invoke multiple closures.
     * @return void
     */
    public function addFunctionCallAnalyzer(\Closure $closure);

    /**
     * Initialize the inner scope of this method with variables created from the parameters.
     *
     * Deferred until the parse phase because getting the UnionType of parameter defaults requires having all class constants be known.
     *
     * @return void
     */
    public function ensureScopeInitialized(CodeBase $code_base);

    /** @return Node|null */
    public function getNode();

    /**
     * @return ?Comment - Not set for internal functions/methods
     */
    public function getComment();

    /**
     * @param Comment $comment
     * @return void
     */
    public function setComment(Comment $comment);

    /**
     * @return UnionType of 0 or more types from (at)throws annotations on this function-like
     */
    public function getThrowsUnionType() : UnionType;

    /**
     * @return bool
     * True if this is a magic phpdoc method (declared via (at)method on class declaration phpdoc)
     * Always false for global functions(Func).
     */
    public function isFromPHPDoc() : bool;

    /**
     * Clone the parameter list, so that modifying the parameters on the first call won't modify the others.
     * TODO: If parameters were changed to be immutable, they can be shared without cloning with less worry.
     * @internal
     * @return void
     */
    public function cloneParameterList();

    /**
     * @return bool - Does any parameter type possibly require recursive analysis if more specific types are provided?
     *
     * If this returns true, there is at least one parameter and at least one of those can be overridden with a more specific type.
     */
    public function needsRecursiveAnalysis() : bool;

    /**
     * Returns a FunctionLikeDeclarationType based on phpdoc+real types.
     * The return value is used for type casting rule checking.
     */
    public function asFunctionLikeDeclarationType() : FunctionLikeDeclarationType;

    /**
     * @return array<mixed,string> in the same format as FunctionSignatureMap.php
     */
    public function toFunctionSignatureArray() : array;

    /**
     * Precondition: This function is a generator type
     * Converts Generator|T[] to Generator<T>
     * Converts Generator|array<int,stdClass> to Generator<int,stdClass>, etc.
     */
    public function getReturnTypeAsGeneratorTemplateType() : Type;

    /**
     * Returns this function's union type without resolving `static` in the function declaration's context.
     */
    public function getUnionTypeWithUnmodifiedStatic() : UnionType;

    /**
     * Check this method's return types (phpdoc and real) to make sure they're valid,
     * and infer a return type from the combination of the signature and phpdoc return types.
     *
     * @return void
     */
    public function analyzeReturnTypes(CodeBase $code_base);

    /**
     * Does this function/method declare an (at)template type for this type?
     */
    public function declaresTemplateTypeInComment(TemplateType $template_type) : bool;

    /**
     * Create any plugins that exist due to doc comment annotations.
     * Must be called after adding this FunctionInterface to the $code_base, so that issues can be emitted if needed.
     * @return ?Closure(CodeBase, Context, array):UnionType
     * @internal
     */
    public function getCommentParamAssertionClosure(CodeBase $code_base);
}
