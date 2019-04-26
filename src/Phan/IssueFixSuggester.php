<?php declare(strict_types=1);

namespace Phan;

use Closure;
use Phan\Language\Context;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use function count;
use function strlen;
use function strtolower;

/**
 * Utilities to suggest fixes for emitted Issues
 *
 * Commonly used methods:
 *
 * self::suggestSimilarClass(CodeBase, Context, FullyQualifiedClassName, Closure $filter = null, string $prefix = 'Did you mean')
 * self::suggestSimilarGlobalFunction(
 *     CodeBase $code_base,
 *     Context $context,
 *     FullyQualifiedFunctionName $function_fqsen,
 *     bool $suggest_in_global_namespace = true,
 *     string $prefix = 'Did you mean'
 * )
 * self::suggestVariableTypoFix(CodeBase, Context, string $variable_name, string $prefix = 'Did you mean')
 * self::suggestSimilarMethod(CodeBase, Context, Clazz, string $wanted_method_name, bool $is_static)
 * self::suggestSimilarProperty(CodeBase, Context, Clazz, string $wanted_property_name, bool $is_static)
 * self::suggestSimilarClassConstant(CodeBase, Context, FullyQualifiedClassConstantName)
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class IssueFixSuggester
{
    /**
     * @param Closure(Clazz):bool $class_closure
     * @return Closure(FullyQualifiedClassName):bool
     */
    public static function createFQSENFilterFromClassFilter(CodeBase $code_base, Closure $class_closure)
    {
        /**
         * @param FullyQualifiedClassName $alternate_fqsen
         */
        return static function ($alternate_fqsen) use ($code_base, $class_closure) : bool {
            if (!($alternate_fqsen instanceof FullyQualifiedClassName)) {
                return false;
            }
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
        return self::createFQSENFilterFromClassFilter($code_base, static function (Clazz $class) use ($allow_class, $allow_trait, $allow_interface) : bool {
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
     * @return ?Suggestion
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
     * @return ?Suggestion
     */
    public static function suggestSimilarGlobalFunction(
        CodeBase $code_base,
        Context $context,
        FullyQualifiedFunctionName $function_fqsen,
        bool $suggest_in_global_namespace = true,
        string $prefix = ""
    ) {
        if (!$prefix) {
            $prefix = self::DEFAULT_FUNCTION_SUGGESTION_PREFIX;
        }
        $namespace = $function_fqsen->getNamespace();
        $name = $function_fqsen->getName();
        $suggested_fqsens = \array_merge(
            $code_base->suggestSimilarGlobalFunctionInOtherNamespace($namespace, $name, $context),
            $code_base->suggestSimilarGlobalFunctionInSameNamespace($namespace, $name, $context, $suggest_in_global_namespace)
        );
        if (count($suggested_fqsens) === 0) {
            return null;
        }

        /**
         * @param string|FullyQualifiedFunctionName $fqsen
         */
        $generate_type_representation = static function ($fqsen) : string {
            return $fqsen . '()';
        };
        $suggestion_text = $prefix . ' ' . \implode(' or ', \array_map($generate_type_representation, $suggested_fqsens));

        return Suggestion::fromString($suggestion_text);
    }

    const DEFAULT_CLASS_SUGGESTION_PREFIX = 'Did you mean';
    const DEFAULT_FUNCTION_SUGGESTION_PREFIX = 'Did you mean';

    const CLASS_SUGGEST_ONLY_CLASSES = 0;
    const CLASS_SUGGEST_CLASSES_AND_TYPES = 1;
    const CLASS_SUGGEST_CLASSES_AND_TYPES_AND_VOID = 2;

    /**
     * Returns a message suggesting a class name that is similar to the provided undeclared class
     *
     * @param ?Closure(FullyQualifiedClassName):bool $filter
     * @param int $class_suggest_type whether to include non-classes such as 'int', 'callable', etc.
     * @return ?Suggestion
     */
    public static function suggestSimilarClass(
        CodeBase $code_base,
        Context $context,
        FullyQualifiedClassName $class_fqsen,
        $filter = null,
        string $prefix = null,
        int $class_suggest_type = self::CLASS_SUGGEST_ONLY_CLASSES
    ) {
        if (!$prefix) {
            $prefix = self::DEFAULT_CLASS_SUGGESTION_PREFIX;
        }
        $suggested_fqsens = \array_merge(
            $code_base->suggestSimilarClassInOtherNamespace($class_fqsen, $context),
            $code_base->suggestSimilarClassInSameNamespace($class_fqsen, $context, $class_suggest_type)
        );
        if ($filter) {
            $suggested_fqsens = \array_filter($suggested_fqsens, $filter);
        }
        if (count($suggested_fqsens) === 0) {
            return null;
        }

        /**
         * @param FullyQualifiedClassName|string $fqsen
         */
        $generate_type_representation = static function ($fqsen) use ($code_base) : string {
            if (\is_string($fqsen)) {
                return $fqsen;  // Not a class name, e.g. 'int', 'callable', etc.
            }
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
        };
        $suggestion_text = $prefix . ' ' . \implode(' or ', \array_map($generate_type_representation, $suggested_fqsens));

        return Suggestion::fromString($suggestion_text);
    }

    /**
     * @return ?Suggestion
     */
    public static function suggestSimilarMethod(CodeBase $code_base, Context $context, Clazz $class, string $wanted_method_name, bool $is_static)
    {
        if (Config::getValue('disable_suggestions')) {
            return null;
        }
        $method_set = self::suggestSimilarMethodMap($code_base, $context, $class, $wanted_method_name, $is_static);
        if (count($method_set) === 0) {
            return null;
        }
        \uksort($method_set, 'strcmp');
        $suggestions = [];
        foreach ($method_set as $method) {
            // We lose the original casing of the method name in the array keys, so use $method->getName()
            $prefix = $method->isStatic() ? 'expr::' : 'expr->' ;
            $suggestions[] = $prefix . $method->getName() . '()';
        }
        return Suggestion::fromString(
            'Did you mean ' . \implode(' or ', $suggestions)
        );
    }

    /**
     * @return array<string,Method>
     */
    public static function suggestSimilarMethodMap(CodeBase $code_base, Context $context, Clazz $class, string $wanted_method_name, bool $is_static) : array
    {
        $methods = $class->getMethodMap($code_base);
        if (count($methods) > Config::getValue('suggestion_check_limit')) {
            return [];
        }
        $usable_methods = self::filterSimilarMethods($code_base, $context, $methods, $is_static);
        return self::getSuggestionsForStringSet($wanted_method_name, $usable_methods);
    }

    /**
     * @return ?FullyQualifiedClassName
     * @internal
     */
    public static function maybeGetClassInCurrentScope(Context $context)
    {
        if ($context->isInClassScope()) {
            return $context->getClassFQSEN();
        }
        return null;
    }

    /**
     * @param array<string,Method> $methods
     * @return array<string,Method> a subset of those methods
     * @internal
     */
    public static function filterSimilarMethods(CodeBase $code_base, Context $context, array $methods, bool $is_static)
    {
        $class_fqsen_in_current_scope = self::maybeGetClassInCurrentScope($context);

        $candidates = [];
        foreach ($methods as $method_name => $method) {
            if ($is_static && !$method->isStatic()) {
                // Don't suggest instance methods to replace static methods
                continue;
            }
            if (!$method->isAccessibleFromClass($code_base, $class_fqsen_in_current_scope)) {
                // Don't suggest inaccessible private or protected methods.
                continue;
            }
            $candidates[$method_name] = $method;
        }
        return $candidates;
    }

    /**
     * @param ?\Closure(FullyQualifiedClassName):bool $filter
     * @return ?Suggestion
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
     * @return ?Suggestion
     */
    public static function suggestSimilarProperty(CodeBase $code_base, Context $context, Clazz $class, string $wanted_property_name, bool $is_static)
    {
        if (Config::getValue('disable_suggestions')) {
            return null;
        }
        if (strlen($wanted_property_name) <= 1) {
            return null;
        }
        $property_set = self::suggestSimilarPropertyMap($code_base, $context, $class, $wanted_property_name, $is_static);
        $suggestions = [];
        if ($is_static) {
            if ($class->hasConstantWithName($code_base, $wanted_property_name)) {
                $suggestions[] = $class->getFQSEN() . '::' . $wanted_property_name;
            }
        }
        if ($class->hasMethodWithName($code_base, $wanted_property_name)) {
            $method = $class->getMethodByName($code_base, $wanted_property_name);
            $suggestions[] = $class->getFQSEN() . ($method->isStatic() ? '::' : '->') . $wanted_property_name . '()';
        }
        foreach ($property_set as $property_name => $_) {
            $prefix = $is_static ? 'expr::$' : 'expr->' ;
            $suggestions[] = $prefix . $property_name;
        }
        foreach (self::getVariableNamesInScopeWithSimilarName($context, $wanted_property_name) as $variable_name) {
            $suggestions[] = $variable_name;
        }

        if (count($suggestions) === 0) {
            return null;
        }
        \uksort($suggestions, 'strcmp');
        return Suggestion::fromString(
            'Did you mean ' . \implode(' or ', $suggestions)
        );
    }

    /**
     * @return array<string,Property>
     */
    public static function suggestSimilarPropertyMap(CodeBase $code_base, Context $context, Clazz $class, string $wanted_property_name, bool $is_static) : array
    {
        $property_map = $class->getPropertyMap($code_base);
        if (count($property_map) > Config::getValue('suggestion_check_limit')) {
            return [];
        }
        $usable_property_map = self::filterSimilarProperties($code_base, $context, $property_map, $is_static);
        return self::getSuggestionsForStringSet($wanted_property_name, $usable_property_map);
    }

    /**
     * @param array<string,Property> $property_map
     * @return array<string,Property> a subset of those methods
     * @internal
     */
    public static function filterSimilarProperties(CodeBase $code_base, Context $context, array $property_map, bool $is_static)
    {
        $class_fqsen_in_current_scope = self::maybeGetClassInCurrentScope($context);
        $candidates = [];
        foreach ($property_map as $property_name => $property) {
            if ($is_static !== $property->isStatic()) {
                // Don't suggest instance properties to replace static properties
                continue;
            }
            if (!$property->isAccessibleFromClass($code_base, $class_fqsen_in_current_scope)) {
                // Don't suggest inaccessible private or protected properties.
                continue;
            }
            // TODO: Check for access to protected outside of a class
            if ($property->isDynamicProperty()) {
                // Skip dynamically added properties
                continue;
            }
            $candidates[$property_name] = $property;
        }
        return $candidates;
    }

    /**
     * @return ?Suggestion
     */
    public static function suggestSimilarClassConstant(CodeBase $code_base, Context $context, FullyQualifiedClassConstantName $class_constant_fqsen)
    {
        if (Config::getValue('disable_suggestions')) {
            return null;
        }
        $constant_name = $class_constant_fqsen->getName();
        if (strlen($constant_name) <= 1) {
            return null;
        }
        $class_fqsen = $class_constant_fqsen->getFullyQualifiedClassName();
        if (!$code_base->hasClassWithFQSEN($class_fqsen)) {
            return null;
        }
        $class = $code_base->getClassByFQSEN($class_fqsen);
        $class_constant_map = self::suggestSimilarClassConstantMap($code_base, $context, $class, $constant_name);
        if (count($class_constant_map) === 0) {
            return null;
        }
        \uksort($class_constant_map, 'strcmp');
        $suggestions = [];
        foreach ($class_constant_map as $constant_name => $_) {
            $suggestions[] = $class_fqsen . '::' . $constant_name;
        }
        return Suggestion::fromString(
            'Did you mean ' . \implode(' or ', $suggestions)
        );
    }

    /**
     * @return ?Suggestion with values similar to the given constant
     */
    public static function suggestSimilarGlobalConstant(CodeBase $code_base, Context $context, FullyQualifiedGlobalConstantName $fqsen)
    {
        if (Config::getValue('disable_suggestions')) {
            return null;
        }
        $constant_name = $fqsen->getName();
        if (strlen($constant_name) <= 1) {
            return null;
        }
        $suggestions = \array_merge(
            self::suggestSimilarFunctionsToConstant($code_base, $context, $fqsen),
            self::suggestSimilarClassConstantsToGlobalConstant($code_base, $context, $fqsen),
            self::suggestSimilarClassPropertiesToGlobalConstant($code_base, $context, $fqsen),
            $code_base->suggestSimilarConstantsToConstant($constant_name),
            self::suggestSimilarVariablesToGlobalConstant($context, $fqsen)
        );
        if (count($suggestions) === 0) {
            return null;
        }
        $suggestions = \array_map('strval', $suggestions);
        return Suggestion::fromString(
            'Did you mean ' . \implode(' or ', $suggestions)
        );
    }

    /**
     * @return array<int,string>
     */
    private static function suggestSimilarFunctionsToConstant(CodeBase $code_base, Context $context, FullyQualifiedGlobalConstantName $fqsen) : array
    {
        $suggested_fqsens = $code_base->suggestSimilarGlobalFunctionInOtherNamespace(
            $fqsen->getNamespace(),
            $fqsen->getName(),
            $context,
            true
        );
        return \array_map(static function (FullyQualifiedFunctionName $fqsen) : string {
            return $fqsen . '()';
        }, $suggested_fqsens);
    }

    /**
     * Suggests accessible class constants of the current class that are similar to the passed in global constant FQSEN
     * @return array<int,string>
     */
    private static function suggestSimilarClassConstantsToGlobalConstant(CodeBase $code_base, Context $context, FullyQualifiedGlobalConstantName $fqsen) : array
    {
        if (!$context->isInClassScope()) {
            return [];
        }
        if (\ltrim($fqsen->getNamespace(), '\\') !== '') {
            return [];
        }
        try {
            $class = $context->getClassInScope($code_base);
            $name = $fqsen->getName();
            if ($class->hasConstantWithName($code_base, $name)) {
                return ["self::$name"];
            }
        } catch (\Exception $_) {
            // ignore
        }
        return [];
    }

    /**
     * Suggests accessible class properties of the current class that are similar to the passed in global constant FQSEN
     * @return array<int,string>
     */
    private static function suggestSimilarClassPropertiesToGlobalConstant(CodeBase $code_base, Context $context, FullyQualifiedGlobalConstantName $fqsen) : array
    {
        if (!$context->isInClassScope()) {
            return [];
        }
        if (\ltrim($fqsen->getNamespace(), '\\') !== '') {
            return [];
        }
        $name = $fqsen->getName();
        try {
            $class = $context->getClassInScope($code_base);
            if (!$class->hasPropertyWithName($code_base, $name)) {
                return [];
            }
            $property = $class->getPropertyByName($code_base, $name);
            if (!$property->isAccessibleFromClass($code_base, $class->getFQSEN())) {
                return [];
            }
            if ($property->isStatic()) {
                return ['self::$' . $name];
            } else {
                return ['$this->' . $name];
            }
        } catch (\Exception $_) {
            // ignore
        }
        return [];
    }

    /**
     * @return array<int,string> returns array variable names prefixed with '$' with a similar name, or an empty array if that wouldn't make sense or there would be too many suggestions
     */
    private static function suggestSimilarVariablesToGlobalConstant(Context $context, FullyQualifiedGlobalConstantName $fqsen) : array
    {
        if ($context->isInGlobalScope()) {
            return [];
        }
        if (\ltrim($fqsen->getNamespace(), '\\') !== '') {
            // Give up if requesting a namespaced constant
            // TODO: Better heuristics
            return [];
        }
        return self::getVariableNamesInScopeWithSimilarName($context, $fqsen->getName());
    }

    /**
     * @return array<string,ClassConstant>
     */
    private static function suggestSimilarClassConstantMap(CodeBase $code_base, Context $context, Clazz $class, string $constant_name) : array
    {
        $constant_map = $class->getConstantMap($code_base);
        if (count($constant_map) > Config::getValue('suggestion_check_limit')) {
            return [];
        }
        $usable_constant_map = self::filterSimilarConstants($code_base, $context, $constant_map);
        $result = self::getSuggestionsForStringSet($constant_name, $usable_constant_map);
        return $result;
    }

    /**
     * @param array<string,ClassConstant> $constant_map
     * @return array<string,ClassConstant> a subset of those methods
     * @internal
     */
    public static function filterSimilarConstants(CodeBase $code_base, Context $context, array $constant_map) : array
    {
        $class_fqsen_in_current_scope = self::maybeGetClassInCurrentScope($context);

        $candidates = [];
        foreach ($constant_map as $constant_name => $constant) {
            if (!$constant->isAccessibleFromClass($code_base, $class_fqsen_in_current_scope)) {
                // Don't suggest inherited private properties
                continue;
            }
            // TODO: Check for access to protected outside of a class
            $candidates[$constant_name] = $constant;
        }
        return $candidates;
    }

    /**
     * @return ?Suggestion
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
        $suggestions = self::getVariableNamesInScopeWithSimilarName($context, $variable_name);
        if ($context->isInClassScope()) {
            // TODO: Does this need to check for static closures
            $class_in_scope = $context->getClassInScope($code_base);
            if ($class_in_scope->hasPropertyWithName($code_base, $variable_name)) {
                $property = $class_in_scope->getPropertyByName($code_base, $variable_name);

                if (!$property->isDynamicProperty()) {
                    // Don't suggest inherited private properties that can't be accessed
                    // - This doesn't need to be checking if the visibility is protected,
                    //   because it's looking for properties of the current class
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
        \sort($suggestions);

        return Suggestion::fromString(
            $prefix . ' ' . \implode(' or ', $suggestions)
        );
    }

    /**
     * @return array<int,string> Suggestions for variable names, prefixed with "$"
     */
    private static function getVariableNamesInScopeWithSimilarName(Context $context, string $variable_name) : array
    {
        $suggestions = [];
        if (strlen($variable_name) > 1) {
            $variable_candidates = $context->getScope()->getVariableMap();
            if (count($variable_candidates) <= Config::getValue('suggestion_check_limit')) {
                $variable_candidates = \array_merge($variable_candidates, Variable::_BUILTIN_SUPERGLOBAL_TYPES);
                $variable_suggestions = self::getSuggestionsForStringSet($variable_name, $variable_candidates);

                foreach ($variable_suggestions as $suggested_variable_name => $_) {
                    $suggestions[] = '$' . $suggested_variable_name;
                }
            }
        }
        return $suggestions;
    }
    /**
     * A very simple way to get the closest case-insensitive string matches.
     *
     * @param array<string,mixed> $potential_candidates
     * @return array<string,mixed> a subset of $potential_candidates
     */
    public static function getSuggestionsForStringSet(string $target, array $potential_candidates) : array
    {
        if (count($potential_candidates) === 0) {
            return [];
        }
        $search_name = strtolower($target);
        $target_length = strlen($search_name);
        $max_levenshtein_distance = (int)(1 + strlen($search_name) / 6);
        $best_matches = [];
        $min_found_distance = $max_levenshtein_distance;

        foreach ($potential_candidates as $name => $_) {
            $name = (string)$name;

            if (\abs(strlen($name) - $target_length) > $max_levenshtein_distance) {
                continue;
            }
            $distance = \levenshtein(strtolower($name), $search_name);
            if ($distance <= $min_found_distance) {
                if ($distance < $min_found_distance) {
                    $min_found_distance = $distance;
                    $best_matches = [];
                }
                $best_matches[$name] = $_;
            }
        }
        return $best_matches;
    }
}
