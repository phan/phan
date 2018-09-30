#!/usr/bin/env php
<?php
declare(strict_types = 1);
/**
 * This checks that the function signatures are complete.
 * TODO: Expand to checking classes (methods, and properties)
 * TODO: Refactor the scripts in internal/ to reuse more code.
 * @phan-file-suppress PhanNativePHPSyntaxCheckPlugin
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Phan\Config;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\UnionType;

/**
 * Checks if Phan has internal signatures for all elements of PHP modules
 * (e.g. php-ast, redis)
 * that the running PHP binary has Reflection information for.
 */
class ReflectionCompletenessCheck
{
    const EXCLUDED_FUNCTIONS = [
        'zend_test_array_return' => true,
        'zend_test_nullable_array_return' => true,
        'zend_test_void_return' => true,
        'zend_create_unterminated_string' => true,
        'zend_terminate_string' => true,
        'zend_leak_bytes' => true,
        'zend_leak_variable' => true,
    ];

    const EXCLUDED_CLASS_NAMES = [
        '_zendtestclass' => true,
        '_zendtestchildclass' => true,
        '_zendtestclassalias' => true,
    ];

    private static function checkForUndeclaredTypeFunctions()
    {
        foreach (get_defined_functions() as $unused_ext => $group) {
            foreach ($group as $function_name) {
                $reflection_function = new ReflectionFunction($function_name);
                if (!$reflection_function->isInternal()) {
                    continue;
                }
                if (array_key_exists($function_name, self::EXCLUDED_FUNCTIONS)) {
                    continue;
                }
                $fqsen = FullyQualifiedFunctionName::fromFullyQualifiedString($function_name);
                $map_list = UnionType::internalFunctionSignatureMapForFQSEN($fqsen);
                if (!$map_list) {
                    $stub_signature = self::stubSignatureToString(self::createStubSignature($reflection_function));
                    echo "Missing signatures for $function_name : should be $stub_signature\n";
                }
            }
        }
    }

    /**
     * @return Generator<string,ReflectionClass>
     */
    private static function getInternalClasses() : Generator
    {
        $classes = array_merge(
            get_declared_classes(),
            get_declared_interfaces(),
            get_declared_traits()
        );
        sort($classes);
        foreach ($classes as $class_name) {
            if (array_key_exists(strtolower($class_name), self::EXCLUDED_CLASS_NAMES)) {
                continue;
            }
            $reflection_class = (new ReflectionClass($class_name));
            if (!$reflection_class->isInternal()) {
                continue;
            }
            yield $class_name => $reflection_class;
        }
    }

    private static function checkForUndeclaredTypeMethods()
    {
        foreach (self::getInternalClasses() as $class_name => $reflection_class) {
            foreach ($reflection_class->getMethods() as $reflection_method) {
                if ($reflection_method->getDeclaringClass()->getName() !== $class_name) {
                    continue;
                }
                $method_name = $reflection_method->getName();
                $method_fqsen = FullyQualifiedMethodName::fromFullyQualifiedString($class_name . '::' . $method_name);
                $map_list = UnionType::internalFunctionSignatureMapForFQSEN($method_fqsen);
                if (!$map_list) {
                    $stub_signature = self::stubSignatureToString(self::createStubSignature($reflection_method));
                    echo "Missing signatures for $method_fqsen : Should be $stub_signature\n";
                }
            }
        }
    }

    /**
     * @return string[]
     */
    public static function createStubSignature(ReflectionFunctionAbstract $reflection_method)
    {
        $signature = [];
        $signature[] = (string)UnionType::fromReflectionType($reflection_method->getReturnType());
        foreach ($reflection_method->getParameters() as $parameter) {
            $key = $parameter->getName();
            if ($parameter->isVariadic()) {
                $key = "...$key";
            } elseif ($parameter->isOptional()) {
                $key = "$key=";
            }
            if ($parameter->isPassedByReference()) {
                $key = "&$key";
            }
            $type = (string)UnionType::fromReflectionType($reflection_method->getReturnType());
            $signature[$key] = $type;
        }
        return $signature;
    }

    // TODO: Deduplicate this code.
    private static function stubSignatureToString(array $stub) : string
    {
        $result = "['$stub[0]'";
        unset($stub[0]);
        foreach ($stub as $key => $value) {
            $result .= ", '$key'=>'$value'";
        }

        $result .= ']';
        return $result;
    }

    private static function checkForUndeclaredTypeProperties()
    {
        foreach (self::getInternalClasses() as $class_name => $reflection_class) {
            $map_for_class = UnionType::internalPropertyMapForClassName($class_name);
            foreach ($reflection_class->getProperties(ReflectionProperty::IS_PUBLIC) as $reflection_property) {
                $property_name = $reflection_property->getName();
                if (!array_key_exists($property_name, $map_for_class)) {
                    printf(
                        "Failed to find signature for property %s%s%s.\n",
                        $class_name,
                        $reflection_property->isStatic() ? '::$' : '->',
                        $property_name
                    );
                }
            }
        }
    }
    /**
     * @return void
     */
    public static function main()
    {
        Config::setValue('target_php_version', sprintf("%d.%d", PHP_MAJOR_VERSION, PHP_MINOR_VERSION));
        error_reporting(E_ALL);

        self::checkForUndeclaredTypeFunctions();
        self::checkForUndeclaredTypeMethods();
        self::checkForUndeclaredTypeProperties();
    }
}
ReflectionCompletenessCheck::main();
