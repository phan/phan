<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope\ClosedScope;

/**
 * Interface defining the behavior of both Methods
 *  and Functions
 */
interface FunctionInterface extends AddressableElementInterface {

    /**
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN();

    /**
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
     * The maximum number of parameters to this method
     */
    public function getNumberOfParameters() : int;
    /**

     * @return int
     * The number of required parameters on this method
     */
    public function getNumberOfRequiredParameters() : int;

    /**
     *
     * The number of required parameters
     *
     * @return void
     */
    public function setNumberOfRequiredParameters(int $number);

    /**
     * @return bool
     * True if this method had no return type defined when it
     * was defined (either in the signature itself or in the
     * docblock).
     */
    public function isReturnTypeUndefined() : bool;

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
     * @return Parameter[]
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
     * @param Parameter[] $parameter_list
     * A list of parameters to set on this method
     *
     * @return void
     */
    public function setParameterList(array $parameter_list);

    /**
     * @param Parameter $parameter
     * A parameter to append to the parameter list
     *
     * @return void
     */
    public function appendParameter(Parameter $parameter);

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
     */
    public function analyzeWithNewParams(Context $context, CodeBase $code_base) : Context;
}
