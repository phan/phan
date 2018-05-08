<?php declare(strict_types=1);
namespace Phan;

use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;

use Closure;

use function strlen;
use function strtolower;

/**
 * Utilities to suggest fixes for emitted Issues
 *
 * Commonly used methods:
 *
 * self::suggestSimilarClass(CodeBase, Context, FullyQualifiedClassName, Closure $filter = null, string $prefix = 'Did you mean')
 * self::suggestVariableTypoFix(CodeBase, Context, string $variable_name, string $prefix = 'Did you mean')
 * self::suggestSimilarMethod(CodeBase, Clazz, string $wanted_method_name, bool $is_static)
 * self::suggestSimilarProperty(CodeBase, Clazz, string $wanted_property_name, bool $is_static)
 */
class IssueFixSuggester {
    /**
     * @param Closure(Clazz):bool $class_closure
     * @return Closure(FullyQualifiedClassName):bool
     */
    public static function createFQSENFilterFromClassFilter(CodeBase $code_base, Closure $class_closure) {
        return function(FullyQualifiedClassName $alternate_fqsen) use ($code_base, $class_closure) : bool {
            if (!$code_base->hasClassWithFQSEN($alternate_fqsen)) {
                return false;
            }
            return $class_closure($code_base->getClassByFQSEN($alternate_fqsen));
        };
    }

    /**
     * @return Closure(FullyQualifiedClassName):bool
     */
    public static function createFQSENFilterForClasslikeCategories(CodeBase $code_base, bool $allow_class, bool $allow_trait, bool $allow_interface)
    {
        return self::createFQSENFilterFromClassFilter($code_base, function(Clazz $class) use ($allow_class, $allow_trait, $allow_interface) : bool {
            if ($class->isTrait()) {
                return $allow_trait;
            } elseif ($class->isInterface()) {
                return $allow_interface;
            } else {
                return $allow_class;
            }
        });
    }

    /**
     * @return ?string
     */
    public static function suggestSimilarClassForMethod(CodeBase $code_base, Context $context, FullyQualifiedClassName $class_fqsen, string $method_name, bool $is_static)
    {
        $filter = null;
        if (strtolower($method_name) === '__construct') {
            // Constructed objects have to be classes
            $filter = self::createFQSENFilterForClasslikeCategories($code_base, true, false, false);
        } elseif ($is_static) {
            // Static methods can be parts of classes or traits, but not interfaces
            $filter = self::createFQSENFilterForClasslikeCategories($code_base, true, true, false);
        }
        return self::suggestSimilarClass($code_base, $context, $class_fqsen, $filter);
    }

    /**
     * Returns a message suggesting a class name that is similar to the provided undeclared class
     *
     * @param null|Closure(FullyQualifiedClassName):bool $filter
     * @return ?string
     */
    public static function suggestSimilarClass(CodeBase $code_base, Context $context, FullyQualifiedClassName $class_fqsen, $filter = null, string $prefix = 'Did you mean')
    {
        $suggested_fqsens = array_merge(
            $code_base->suggestSimilarClassInOtherNamespace($class_fqsen, $context),
            $code_base->suggestSimilarClassInSameNamespace($class_fqsen, $context)
        );
        if ($filter) {
            $suggested_fqsens = array_filter($suggested_fqsens, $filter);
        }
        if (count($suggested_fqsens) === 0) {
            return null;
        }
        return $prefix . ' ' . implode(' or ', array_map(function (FullyQualifiedClassName $fqsen) use ($code_base) : string {
            $category = 'classlike';
            if ($code_base->hasClassWithFQSEN($fqsen)) {
                $class = $code_base->getClassByFQSEN($fqsen);
                if ($class->isInterface()) {
                    $category = 'interface';
                } elseif ($class->isTrait()) {
                    $category = 'trait';
                } else {
                    $category = 'class';
                }
            }
            return $category . ' ' . $fqsen->__toString();
        }, $suggested_fqsens));
    }

    /**
     * @return ?string
     */
    public static function suggestSimilarMethod(CodeBase $code_base, Clazz $class, string $wanted_method_name, bool $is_static)
    {
        if (Config::getValue('disable_suggestions')) {
            return null;
        }
        $method_set = self::suggestSimilarMethodMap($code_base, $class, $wanted_method_name, $is_static);
        if (count($method_set) === 0) {
            return null;
        }
        uksort($method_set, 'strcmp');
        $suggestions = [];
        foreach ($method_set as $method) {
            // We lose the original casing of the method name in the array keys, so use $method->getName()
            $prefix = $method->isStatic() ? 'expr::' : 'expr->' ;
            $suggestions[] = $prefix . $method->getName() . '()';
        }
        return 'Did you mean ' . implode(' or ', $suggestions);
    }

    /**
     * @return array<string,Method>
     */
    public static function suggestSimilarMethodMap(CodeBase $code_base, Clazz $class, string $wanted_method_name, bool $is_static) : array
    {
        $methods = $class->getMethodMap($code_base);
        if (count($methods) > Config::getValue('suggestion_check_limit')) {
            return [];
        }
        $usable_methods = self::filterSimilarMethods($methods, $class, $is_static);
        return self::getSuggestionsForStringSet($wanted_method_name, $usable_methods);
    }

    /**
     * @param array<string,Method> $methods
     * @return array<string,Method> a subset of those methods
     */
    private static function filterSimilarMethods(array $methods, Clazz $class, bool $is_static) {
        $candidates = [];
        foreach ($methods as $method_name => $method) {
            if ($is_static && !$method->isStatic()) {
                // Don't suggest instance methods to replace static methods
                continue;
            }
            if ($method->isPrivate() && $method->getDefiningClassFQSEN() !== $class->getFQSEN()) {
                // Don't suggest inherited private methods
                continue;
            }
            $candidates[$method_name] = $method;
        }
        return $candidates;
    }

    /**
     * @param ?\Closure $filter
     * @return ?string
     * TODO: Figure out why ?Closure(NS\X):bool can't cast to ?Closure(NS\X):bool
     */
    public static function suggestSimilarClassForGenericFQSEN(CodeBase $code_base, Context $context, FQSEN $fqsen, $filter = null, string $prefix = 'Did you mean')
    {
        if (Config::getValue('disable_suggestions')) {
            return null;
        }
        if (!($fqsen instanceof FullyQualifiedClassName)) {
            return null;
        }
        return self::suggestSimilarClass($code_base, $context, $fqsen, $filter, $prefix);
    }

    /**
     * @return ?string
     */
    public static function suggestSimilarProperty(CodeBase $code_base, Clazz $class, string $wanted_property_name, bool $is_static)
    {
        if (Config::getValue('disable_suggestions')) {
            return null;
        }
        $property_set = self::suggestSimilarPropertyMap($code_base, $class, $wanted_property_name, $is_static);
        if (count($property_set) === 0) {
            return null;
        }
        uksort($property_set, 'strcmp');
        $suggestions = [];
        foreach ($property_set as $property_name => $_) {
            $prefix = $is_static ? 'expr::$' : 'expr->' ;
            $suggestions[] = $prefix . $property_name;
        }
        return 'Did you mean ' . implode(' or ', $suggestions);
    }

    /**
     * @return array<string,Property>
     */
    public static function suggestSimilarPropertyMap(CodeBase $code_base, Clazz $class, string $wanted_property_name, bool $is_static) : array
    {
        $property_map = $class->getPropertyMap($code_base);
        if (count($property_map) > Config::getValue('suggestion_check_limit')) {
            return [];
        }
        $usable_property_map = self::filterSimilarProperties($property_map, $class, $is_static);
        return self::getSuggestionsForStringSet($wanted_property_name, $usable_property_map);
    }

    /**
     * @param array<string,Property> $property_map
     * @return array<string,Property> a subset of those methods
     */
    private static function filterSimilarProperties(array $property_map, Clazz $class, bool $is_static) {
        $candidates = [];
        foreach ($property_map as $property_name => $property) {
            if ($is_static !== $property->isStatic()) {
                // Don't suggest instance properties to replace static properties
                continue;
            }
            if ($property->isPrivate() && $property->getDefiningClassFQSEN() !== $class->getFQSEN()) {
                // Don't suggest inherited private properties
                continue;
            }
            if ($property->isDynamicProperty()) {
                // Skip dynamically added properties
                continue;
            }
            $candidates[$property_name] = $property;
        }
        return $candidates;
    }

    /**
     * @return ?string
     */
    public static function suggestVariableTypoFix(CodeBase $code_base, Context $context, string $variable_name, string $prefix = 'Did you mean')
    {
        if (Config::getValue('disable_suggestions')) {
            return null;
        }
        if ($variable_name === '') {
            return null;
        }
        if (!$context->isInFunctionLikeScope()) {
            // Don't bother suggesting globals for now
            return null;
        }
        $suggestions = [];
        if (strlen($variable_name) > 1) {
            $variable_candidates = $context->getScope()->getVariableMap();
            if (count($variable_candidates) <= Config::getValue('suggestion_check_limit')) {
                $variable_candidates = array_merge($variable_candidates, Variable::_BUILTIN_SUPERGLOBAL_TYPES);
                $variable_suggestions = self::getSuggestionsForStringSet($variable_name, $variable_candidates);

                foreach ($variable_suggestions as $suggested_variable_name => $_) {
                    $suggestions[] = '$' . $suggested_variable_name;
                }
            }
        }
        if ($context->isInClassScope()) {
            // TODO: Does this need to check for static closures
            $class_in_scope = $context->getClassInScope($code_base);
            if ($class_in_scope->hasPropertyWithName($code_base, $variable_name)) {
                $property = $class_in_scope->getPropertyByName($code_base, $variable_name);

                if (!$property->isDynamicProperty()) {
                    // Don't suggest inherited private properties that can't be accessed
                    if (!$property->isPrivate() || $property->getDefiningClassFQSEN() === $class_in_scope->getFQSEN()) {
                        $suggestion_prefix = $property->isStatic() ? 'self::$' : '$this->';
                        $suggestions[] = $suggestion_prefix . $variable_name;
                    }
                }
            }
        }
        if (count($suggestions) === 0) {
            return null;
        }
        sort($suggestions);

        return $prefix . ' ' . implode(' or ', $suggestions);
    }

    /**
     * A very simple way to get the closest case-insensitive string matches.
     *
     * @param array<string,mixed> $potential_candidates
     * @return array<string,mixed> a subset of $potential_candidates
     */
    public static function getSuggestionsForStringSet(string $target, array $potential_candidates)
    {
        if (count($potential_candidates) === 0) {
            return [];
        }
        $search_name = strtolower($target);
        $N = strlen($search_name);
        $max_levenshtein_distance = (int)(1 + strlen($search_name) / 6);
        $best_matches = [];
        $min_found_distance = $max_levenshtein_distance;

        foreach ($potential_candidates as $name => $_) {
            $name = (string)$name;

            if (\abs(strlen($name) - $N) > $max_levenshtein_distance) {
                continue;
            }
            $distance = levenshtein(strtolower($name), $search_name);
            if ($distance <= $min_found_distance) {
                if ($distance < $min_found_distance) {
                    $best_matches = [];
                }
                $best_matches[$name] = $_;
            }
        }
        return $best_matches;
    }
}
