<?php declare(strict_types=1);
namespace Phan\Language\Element;

/**
 * Interface defining the behavior of both Methods
 *  and Functions
 */
interface FunctionInterface {

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
     * return value
     *
     * @return void
     */
    public function setHasReturn(bool $has_return);

    /**
     * @return Parameter[]
     * A list of parameters on the method
     */
    public function getParameterList();

    /**
     * @param Parameter[] $parameter_list
     * A list of parameters to set on this method
     *
     * @return void
     */
    public function setParameterList(array $parameter_list);

}
