<?php

declare(strict_types=1);

namespace Phan\Plugin\Internal;

use InvalidArgumentException;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Type;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\ObjectType;
use Phan\Language\UnionType;
use Phan\PluginV3;
use Phan\PluginV3\BeforeAnalyzeCapability;
use TypeError;

use function count;

/**
 * This internal plugin implements tool/phoogle, to search for functions/methods with a similar signature to what you search for.
 *
 * NOTE: This is automatically loaded by phan. Do not include it in a config.
 *
 * @phan-file-suppress PhanPluginRemoveDebugAny outputting is deliberate
 *
 * @internal
 */
final class MethodSearcherPlugin extends PluginV3 implements
    BeforeAnalyzeCapability
{
    /** @var list<UnionType> the param type we're looking for. */
    public static $param_types;

    /** @var UnionType the return type we're looking for. */
    public static $return_type;

    /** @var list<array{0:int, 1:string, 2:FunctionInterface}> */
    private $results;

    public function __construct()
    {
    }

    /**
     * Sets the search string that will be used once this plugin is invoked
     *
     * @throws InvalidArgumentException
     */
    public static function setSearchString(string $search_string): void
    {
        // XXX improve parsing this
        $parts = \array_map('trim', \explode('->', $search_string));
        $result = [];
        if (count($parts) === 0) {
            throw new InvalidArgumentException("Empty string passed in when searching for function/method signature");
        }
        foreach ($parts as $i => $part) {
            if ($part === '' && $i < count($parts) - 1) {
                continue;
            }
            if ($part === '') {
                $result[] = UnionType::empty();
                continue;
            }
            if (!\preg_match('(' . UnionType::union_type_regex . ')', $part)) {
                throw new InvalidArgumentException("Invalid union type '$part'");
            }
            $result[] = UnionType::fromStringInContext($part, new Context(), Type::FROM_PHPDOC);
        }
        // @phan-suppress-next-line PhanPossiblyNullTypeMismatchProperty
        self::$return_type = \array_pop($result);
        self::$param_types = $result;
        echo "Searching for function/method signatures similar to: " . \implode(' -> ', \array_merge(self::$param_types, [self::$return_type])) . "\n";
    }

    /**
     * Given a UnionType that may have references to regular class-like types that don't exist (e.g. `\Type`, `\Type[]`),
     * replace classes that don't exist (e.g. `\Type`) with ones that do exist in other namespaces (e.g. `\Phan\Language\Type`)
     */
    public static function addMissingNamespaces(CodeBase $code_base, UnionType $union_type): UnionType
    {
        foreach ($union_type->getUniqueFlattenedTypeSet() as $type) {
            if ($type->isObjectWithKnownFQSEN()) {
                $replacements = self::getReplacementTypesForFullyQualifiedClassName($code_base, $type);
                if ($replacements === [$type]) {
                    continue;
                }
                $union_type = $union_type->withoutType($type)->withUnionType(UnionType::of($replacements, []));
            } elseif ($type instanceof GenericArrayType) {
                $element_type = $type->genericArrayElementType();
                $replacement_element_types = self::addMissingNamespaces($code_base, $element_type->asPHPDocUnionType());
                if ($replacement_element_types->isType($element_type)) {
                    continue;
                }
                $union_type = $union_type->withoutType($type);
                foreach ($replacement_element_types->getTypeSet() as $element_type) {
                    $replacement_type = GenericArrayType::fromElementType(
                        $element_type,
                        $type->isNullable(),
                        $type->getKeyType()
                    );
                    $union_type = $union_type->withType($replacement_type);
                }
            }
            // TODO: Could also do this for generic arrays, etc.
        }
        return $union_type;
    }

    /**
     * @param Type $type a type with the name of a class
     * @return Type[] a list of types to replace $type with
     */
    public static function getReplacementTypesForFullyQualifiedClassName(
        CodeBase $code_base,
        Type $type
    ): array {
        $fqsen = FullyQualifiedClassName::fromType($type);
        if ($code_base->hasClassWithFQSEN($fqsen)) {
            return [$type];
        }
        $fqsens = $code_base->suggestSimilarClassInOtherNamespace($fqsen, new Context());
        if (!$fqsens) {
            \fwrite(\STDERR, "Phoogle could not find '$fqsen' in any namespace\n");
            exit(\EXIT_FAILURE);
        }
        return \array_map(static function (FullyQualifiedClassName $fqsen) use ($type): Type {
            return $fqsen->asType()->withIsNullable($type->isNullable());
        }, $fqsens);
    }

    private static function addMissingNamespacesToTypes(CodeBase $code_base): void
    {
        $original_param_types = self::$param_types;
        $original_return_type = self::$return_type;
        foreach (self::$param_types as $i => $type) {
            self::$param_types[$i] = self::addMissingNamespaces($code_base, $type);
        }
        self::$return_type = self::addMissingNamespaces($code_base, self::$return_type);

        if ($original_return_type !== self::$return_type || $original_param_types !== self::$param_types) {
            echo "Phoogle is searching for " . \implode(' -> ', \array_merge(self::$param_types, [self::$return_type])) . " instead (some classes had missing namespaces)\n";
        }
    }

    /**
     * Prints all function/method signatures that match the search input, and exits
     *
     * @return never
     */
    public function beforeAnalyze(CodeBase $code_base): void
    {
        self::addMissingNamespacesToTypes($code_base);

        $code_base->eagerlyLoadAllSignatures();
        foreach ($code_base->getFunctionMap() as $function) {
            if ($function->isClosure()) {
                continue;
            }
            if ($function->getFQSEN()->isAlternate()) {
                continue;
            }
            $this->checkFunction($code_base, $function);
        }
        foreach ($code_base->getMethodSet() as $function) {
            $this->checkFunction($code_base, $function);
        }
        $results = $this->results;
        \sort($results);
        $num_results = count($results);
        // This can be configured through --limit in phoogle
        $limit = ((int)$_ENV['PHOOGLE_LIMIT']) ?: 10;
        echo "Phoogle found $num_results result(s)\n";
        if ($limit < count($results)) {
            echo "(Showing $limit of $num_results results)\n";
        }
        foreach ($results as $i => [$unused_score, $fqsen, $function]) {
            echo "$fqsen\n";
            if ($function instanceof Method) {
                $return_type = $function->getUnionTypeWithUnmodifiedStatic();
            } else {
                $return_type = $function->getUnionType();
            }
            \printf(
                "    (%s)%s\n",
                \implode(', ', $function->getParameterList()),
                $return_type->isEmpty() ? '' : (' : ' . $return_type)
            );
            if ($i >= $limit) {
                break;
            }
        }
        exit(\EXIT_SUCCESS);
    }

    private function checkFunction(CodeBase $code_base, FunctionInterface $function): void
    {
        $result = $this->functionMatchesSignature($code_base, $function);
        if ($result) {
            $this->results[] = [-$result, (string)$function->getFQSEN(), $function];
        }
    }

    /**
     * @return float - This returns larger values for better matches
     */
    public function functionMatchesSignature(
        CodeBase $code_base,
        FunctionInterface $function
    ): float {
        // TODO: Account for visibility
        if ($function instanceof Method) {
            if ($function->getFQSEN() !== $function->getDefiningFQSEN()) {
                // Don't check inherited methods
                return 0;
            }
            if (!$function->isPublic()) {
                return 0;
            }
        }
        // TODO: Set strict type casting rules here?
        if ($function instanceof Method && \in_array(\strtolower($function->getName()), ['__construct', '__clone'], true)) {
            $return_type = $function->getFQSEN()->getFullyQualifiedClassName()->asType()->asPHPDocUnionType();
        } else {
            $return_type = $function->getUnionType();
        }
        if ($return_type->isEmpty()) {
            $return_type = $this->guessUnionType($function);
        }
        if (!$return_type->canCastToUnionType(self::$return_type, $code_base)) {
            return 0;
        }
        $signature_param_types = [];
        $adjustment = 0;
        foreach ($function->getParameterList() as $param) {
            if ($param->isPassByReference()) {
                // penalize functions with references from search results.
                $adjustment -= 0.5;
            }
            $signature_param_types[] = $param->getUnionType();
        }
        if ($function instanceof Method && !$function->isStatic()) {
            $signature_param_types[] = $function->getFQSEN()->getFullyQualifiedClassName()->asType()->asPHPDocUnionType();
        }
        if (count($signature_param_types) < count(self::$param_types)) {
            return 0;
        }
        $result = $this->matchesParamTypes($code_base, self::$param_types, $signature_param_types);
        if (!$result) {
            return 0;
        }
        return \max(0.1, $result + $adjustment + self::getTypeMatchingBonus($code_base, $return_type, self::$return_type));
    }

    private static function guessUnionType(FunctionInterface $function): UnionType
    {
        if ($function instanceof Method) {
            // convert __set to void, __sleep to string[], etc.
            $union_type = $function->getUnionTypeOfMagicIfKnown();
            if ($union_type) {
                return $union_type;
            }
            if (!$function->isAbstract() && !$function->isPHPInternal() && !$function->hasReturn()) {
                return UnionType::fromFullyQualifiedPHPDocString('void');
            }
        } else {
            if (!$function->isPHPInternal() && !$function->hasReturn()) {
                return UnionType::fromFullyQualifiedRealString('void');
            }
        }
        return UnionType::empty();
    }

    // TODO: Handle non-null-mixed/non-empty-mixed
    private static function isMixed(UnionType $union_type): bool
    {
        foreach ($union_type->getTypeSet() as $type) {
            if (!$type instanceof MixedType) {
                return false;
            }
        }
        return true;
    }
    /**
     * Get the bonus for using $actual_signature_type where we are looking for $desired_type
     */
    public static function getTypeMatchingBonus(CodeBase $code_base, UnionType $actual_signature_type, UnionType $desired_type): float
    {
        if (self::isMixed($desired_type) || self::isMixed($actual_signature_type)) {
            return 0;
        }
        $bonus = 0;
        if ($actual_signature_type->containsNullable() === $desired_type->containsNullable()) {
            $bonus += 0.1;
        }
        if ($desired_type->isEqualTo($actual_signature_type)) {
            return $bonus + 5;
        }
        $desired_type_normalized = $desired_type->nullableClone();
        $expanded_actual_signature_type = $actual_signature_type->asExpandedTypes($code_base);
        $result = 0;
        // TODO: This should handle Liskov Substitution Principle
        // TODO: Handle intersection types?
        foreach ($desired_type_normalized->getTypeSet() as $inner_type) {
            if ($expanded_actual_signature_type->hasType($inner_type) || $expanded_actual_signature_type->hasType($inner_type->withIsNullable(false))) {
                if ($inner_type->isObjectWithKnownFQSEN() && !$desired_type->objectTypesWithKnownFQSENs()->isEmpty()) {
                    $result += 5;
                } else {
                    if ($inner_type->isScalar() && !$actual_signature_type->canCastToUnionType($inner_type->asPHPDocUnionType(), $code_base)) {
                        $result += 0.5;
                        continue;
                    }
                    $result += 1;
                }
            } elseif ($actual_signature_type->canCastToUnionType($inner_type->asPHPDocUnionType(), $code_base)) {
                if (self::isCastableButNotSubtype($expanded_actual_signature_type, $inner_type)) {
                    continue;
                }
                $result += 0.5;
            }
        }
        return $bonus + ($result / \max($desired_type->typeCount(), $actual_signature_type->typeCount()));
    }

    private static function isCastableButNotSubtype(UnionType $actual_type, Type $inner_type): bool
    {
        if ($inner_type instanceof ObjectType) {
            foreach ($actual_type->getTypeSet() as $type) {
                if ($type->isPossiblyObject() && !$type->isObjectWithKnownFQSEN()) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Check if param_types contains unique types that can cast to search_param_types
     * @param array<int, UnionType> $search_param_types (array keys are removed from both params when this recursively calls itself)
     * @param array<int, UnionType> $signature_param_types
     */
    public static function matchesParamTypes(CodeBase $code_base, array $search_param_types, array $signature_param_types): float
    {
        if (\count($search_param_types) === 0) {
            // Award extra points for having the same number of matches
            return \max(1, 5 - count($signature_param_types)) / 2;
        }
        $best = 0;
        $desired_param_type = \array_pop($search_param_types);
        if (!($desired_param_type instanceof UnionType)) {
            // Phan can't tell this array is non-empty
            throw new TypeError("Expected signature_param_types to be an array of UnionType");
        }
        if ($desired_param_type->isEmpty()) {
            $desired_param_type_for_comparison = $desired_param_type;
        } else {
            $desired_param_type_for_comparison = $desired_param_type->nullableClone();
        }
        foreach ($signature_param_types as $i => $actual_type) {
            if ($actual_type->canCastToUnionType($desired_param_type_for_comparison, $code_base)) {
                $signature_subset = $signature_param_types;
                unset($signature_subset[$i]);
                $result = self::matchesParamTypes($code_base, $search_param_types, $signature_subset);
                if ($result > 0) {
                    $best = \max($best, $result + self::getTypeMatchingBonus($code_base, $actual_type, $desired_param_type));
                }
            }
        }
        if ($best === 0) {
            return 0;
        }
        return $best + 1 / (count($search_param_types) + 1);
    }
}
