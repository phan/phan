#!/usr/bin/env php
<?php
/**
 * @throws ReflectionException
 */
function load_internal_function(string $function_name) : ReflectionFunctionAbstract
{
    if (strpos($function_name, '::') !== false) {
        list($class_name, $method_name) = explode('::', $function_name, 2);
        $class = new ReflectionClass($class_name);
        return $class->getMethod($method_name);
    } else {
        return new ReflectionFunction($function_name);
    }
}

/**
 * @throws InvalidArgumentException for invalid fields
 */
function getParametersCountsFromPhan(array $fields) : array
{
    $num_required = 0;
    unset($fields[0]);
    $num_optional = count($fields);
    $saw_optional = false;
    $saw_optional_after_required = false;
    foreach ($fields as $type => $_) {
        if (!is_string($type)) {
            throw new InvalidArgumentException("Invalid parameter description $type");
        }

        if (strpos($type, '...') !== false) {
            $num_optional = 10000;
            break;
        } elseif (strpos($type, '=') === false) {
            $num_required++;
            if ($saw_optional) {
                $saw_optional_after_required = true;
            }
        } else {
            $saw_optional = true;
        }
    }
    return [$num_required, $num_optional, $saw_optional_after_required];
}
/**
 * @param ReflectionParameter[] $args
 */
function getParameterCountsFromReflection(array $args) : array
{
    $num_required = 0;
    $num_optional = count($args);
    foreach ($args as $reflection_parameter) {
        if ($reflection_parameter->isVariadic()) {
            $num_optional = 10000;
            break;
        } elseif ($reflection_parameter->isOptional() || $reflection_parameter->isDefaultValueAvailable()) {
        } else {
            $num_required++;
        }
    }
    return [$num_required, $num_optional];
}

/**
 * This represents an entry in Phan's internal function signatures for a function/method parameter.
 *
 * (e.g. `'...args' => 'string|int'`)
 */
class PhanParameterInfo
{
    /** @var string the parameter name */
    public $name;

    /**
     * @var string the original spec string, e.g. `'...args'`
     * @suppress PhanWriteOnlyPublicProperty may use this in the future, e.g. for warnings
     */
    public $original_name_spec;

    /** @var bool is this parameter optional */
    public $is_optional;

    /** @var bool is this parameter passed by reference */
    public $is_by_reference;

    /** @var bool is this parameter variadic */
    public $is_variadic;

    /** @var string the union type string */
    public $value;

    public function __construct(string $original_name_spec, string $value_spec)
    {
        $this->original_name_spec = $original_name_spec;
        $name = $original_name_spec;
        $this->is_by_reference = ($name[0] ?? '') === '&';
        $name = ltrim($name, '&');
        $this->is_variadic = stripos($name, '...') !== false;
        $name = trim($name, '.');
        $this->is_optional = $name[strlen($name) - 1] === '=';
        $name = rtrim($name, '=');
        $this->value = $value_spec;
        $this->name = $name;
    }
}

/**
 * @param string[] $fields
 * @phan-param array{0:string}|array<string,string> $fields
 * @return array<int,PhanParameterInfo>
 */
function get_parameters_from_phan($fields)
{
    unset($fields[0]);
    $result = [];
    foreach ($fields as $original_name => $value) {
        $result[] = new PhanParameterInfo($original_name, $value);
    }
    return $result;
}

/**
 * @return void
 * @throws InvalidArgumentException for invalid return types
 * TODO: consistent naming
 */
function check_fields(string $function_name, array $fields, array $signatures)
{
    $return_type = $fields[0];  // TODO: Check type
    if (!is_string($return_type)) {
        throw new InvalidArgumentException("Invalid return type: " . json_encode($return_type));
    }

    $original_function_name = $function_name;
    $function_name = preg_replace("/'\\d+$/", "", $function_name);
    // echo $function_name . "\n";
    try {
        $function = load_internal_function($function_name);
    } catch (ReflectionException $_) {
        return;
    }
    $real_return_type = (string)$function->getReturnType();
    // TODO: Account for alternate signatures, check for $function_name . "\1"
    $has_alternate = isset($signatures[$function_name . "'1"]);
    if ($real_return_type !== '' && strtolower(ltrim($real_return_type)) !== strtolower($return_type)) {
        if ($has_alternate) {
            echo "(Has alternate): ";
        }
        echo "Found mismatch for $function_name: Reflection says return type is '$real_return_type', Phan says return type is '$return_type'\n";
    }

    $reflection_parameters = $function->getParameters();
    list($phan_required_count, $phan_optional_count, $saw_optional_after_required) = getParametersCountsFromPhan($fields);
    list($php_computed_required_count, $php_optional_count) = getParameterCountsFromReflection($reflection_parameters);
    $php_required_count = $function->getNumberOfRequiredParameters();
    if ($saw_optional_after_required) {
        echo "Saw optional after required for $original_function_name: " . json_encode($fields) . "\n";
    }
    if ($php_computed_required_count !== $php_required_count) {
        if ($has_alternate) {
            echo "(Has alternate): ";
        }
        echo "Found mismatch for $original_function_name: Computed required count ($php_computed_required_count) !== getNumberOfRequiredParameters ($php_required_count)\n";
    }

    if ($php_required_count < $phan_required_count) {
        if ($has_alternate) {
            echo "(Has alternate): ";
        }
        echo "Found mismatch for $original_function_name: PHP has fewer required parameters ($php_required_count) than phan does ($phan_required_count): " . json_encode($fields) . "\n";
    }
    if ($php_optional_count > $phan_optional_count) {
        if ($has_alternate) {
            echo "(Has alternate): ";
        }
        echo "Found mismatch for $original_function_name: PHP has more optional parameters ($php_optional_count) than phan does ($phan_optional_count): " . json_encode($fields) . "\n";
    }

    $phan_parameters = get_parameters_from_phan($fields);
    foreach ($reflection_parameters as $i => $reflection_parameter) {
        $phan_parameter = $phan_parameters[$i] ?? null;
        if ($phan_parameter instanceof PhanParameterInfo) {
            $reflection_is_by_reference = $reflection_parameter->isPassedByReference();
            if ($phan_parameter->is_by_reference !== $reflection_is_by_reference) {
                if ($has_alternate) {
                    echo "(Has alternate): ";
                }
                if ($reflection_is_by_reference) {
                    // TODO: Switch to printf
                    printf(
                        "Found mismatch for %s param %s and phan param %s: PHP says param is by reference, but phan doesn't: %s\n",
                        $original_function_name,
                        $reflection_parameter->getName(),
                        $phan_parameter->name,
                        json_encode($fields)
                    );
                } else {
                    printf(
                        "Found mismatch for %s param %s and phan param %s: PHP says param is not by reference, but phan does: %s\n",
                        $original_function_name,
                        $reflection_parameter->getName(),
                        $phan_parameter->name,
                        json_encode($fields)
                    );
                }
            }
            $reflection_is_variadic = $reflection_parameter->isVariadic();
            if ($phan_parameter->is_variadic !== $reflection_is_variadic) {
                if ($has_alternate) {
                    echo "(Has alternate): ";
                }
                if ($reflection_is_variadic) {
                    printf(
                        "Found mismatch for %s param %s and phan param %s: PHP says param is variadic, but phan doesn't: %s\n",
                        $original_function_name,
                        $reflection_parameter->getName(),
                        $phan_parameter->name,
                        json_encode($fields)
                    );
                } else {
                    printf(
                        "Found mismatch for %s param %s and phan param %s: PHP says param is not variadic, but phan does: %s\n",
                        $original_function_name,
                        $reflection_parameter->getName(),
                        $phan_parameter->name,
                        json_encode($fields)
                    );
                }
            }

            $reflection_is_optional = $reflection_parameter->isOptional() || $reflection_is_variadic;
            if ($phan_parameter->is_optional !== $reflection_is_optional) {
                if ($has_alternate) {
                    echo "(Has alternate): ";
                }
                if ($reflection_is_optional) {
                    printf(
                        "Found mismatch for %s param %s and phan param %s: PHP says param is optional, but phan doesn't: %s\n",
                        $original_function_name,
                        $reflection_parameter->getName(),
                        $phan_parameter->name,
                        json_encode($fields)
                    );
                } else {
                    printf(
                        "Found mismatch for %s param %s and phan param %s: PHP says param is not optional, but phan does: %s\n",
                        $original_function_name,
                        $reflection_parameter->getName(),
                        $phan_parameter->name,
                        json_encode($fields)
                    );
                }
            }

            if ($reflection_parameter->hasType()) {
                $reflection_representation = (string)$reflection_parameter->getType();
                $phan_representation = $phan_parameter->value;
                if (strcasecmp($reflection_representation, $phan_representation) !== 0) {
                    if ($reflection_representation === 'array' && stripos($phan_representation, '[]') !== false) {
                        // nothing to do
                    } else {
                        if ($has_alternate) {
                            echo "(Has alternate): ";
                        }
                        printf(
                            "Found mismatch for %s param %s and phan param %s: PHP says param is type '%s', but phan says '%s': %s\n",
                            $original_function_name,
                            $reflection_parameter->getName(),
                            $phan_parameter->name,
                            $reflection_representation,
                            $phan_representation,
                            json_encode($fields)
                        );
                    }
                }
            }
        }
    }
}

function main_check_fields()
{
    error_reporting(E_ALL);

    $signatures = require __DIR__ . '/../src/Phan/Language/Internal/FunctionSignatureMap.php';
    foreach ($signatures as $function_name => $fields) {
        check_fields($function_name, $fields, $signatures);
    }
    echo "Done sanity checks of function and method signatures\n";
}
main_check_fields();

/**
DOMDocument::createProcessingInstruction has wrong reflection, it is optional
DOMDocument::importNode has wrong reflection, it is optional
DOMImplementation::createDocument
DOMImplementation::createDocumentType
DOMNamedNodeMap::getNamedItemNS should say mandatory?
DOMNode::C14NFile should say optional
Incorrect reflectionfunction for fsockopen, port is optional
Incorrect reflectionfunction for getenv, returns array if 0 parameters are provided
get_resources has incorrect reflection info
gzgetss has incorrect reflection info
imageaffinematrixget should be optional? Not sure
 */
