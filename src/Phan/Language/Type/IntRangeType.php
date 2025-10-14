<?php

declare(strict_types=1);

namespace Phan\Language\Type;

use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Represents the utility type `int-range<min,max>`.
 */
final class IntRangeType extends IntType
{
    public const NAME = 'int-range';

    /**
     * @var ?int
     */
    private $lower_bound;

    /**
     * @var ?int
     */
    private $upper_bound;

    protected function __construct(
        string $namespace,
        string $name,
        array $template_parameter_type_list,
        bool $is_nullable
    ) {
        parent::__construct($namespace, $name, $template_parameter_type_list, $is_nullable);
        [$this->lower_bound, $this->upper_bound] = self::extractBounds($template_parameter_type_list);
    }

    /**
     * Creates an `int-range` type from phpdoc template parameters.
     *
     * @param list<UnionType> $template_parameter_type_list
     */
    public static function fromTemplateParameterTypeList(
        array $template_parameter_type_list,
        bool $is_nullable,
        int $source
    ): Type {
        $normalized_list = self::normalizeTemplateParameterTypeList($template_parameter_type_list);
        if ($normalized_list === null) {
            return IntType::instance($is_nullable);
        }
        return self::make('\\', self::NAME, $normalized_list, $is_nullable, $source);
    }

    /**
     * Returns the inclusive lower bound for this range if available.
     */
    public function getLowerBound(): ?int
    {
        return $this->lower_bound;
    }

    /**
     * Returns the inclusive upper bound for this range if available.
     */
    public function getUpperBound(): ?int
    {
        return $this->upper_bound;
    }

    /**
     * Checks whether a literal value is within this range.
     */
    public function containsValue(int $value): bool
    {
        if ($this->lower_bound !== null && $value < $this->lower_bound) {
            return false;
        }
        if ($this->upper_bound !== null && $value > $this->upper_bound) {
            return false;
        }
        return true;
    }

    protected function canCastToNonNullableType(Type $type, \Phan\CodeBase $code_base): bool
    {
        if ($type instanceof self) {
            if ($type->lower_bound === null || $type->upper_bound === null || $this->lower_bound === null || $this->upper_bound === null) {
                return true;
            }
            return $this->lower_bound >= $type->lower_bound && $this->upper_bound <= $type->upper_bound;
        }
        return parent::canCastToNonNullableType($type, $code_base);
    }

    public function isPossiblyFalsey(): bool
    {
        if ($this->is_nullable) {
            return true;
        }
        if ($this->lower_bound === null || $this->upper_bound === null) {
            return true;
        }
        return $this->lower_bound <= 0 && $this->upper_bound >= 0;
    }

    public function isAlwaysFalsey(): bool
    {
        return !$this->is_nullable && $this->lower_bound === 0 && $this->upper_bound === 0;
    }

    public function isPossiblyTruthy(): bool
    {
        if ($this->is_nullable) {
            return true;
        }
        if ($this->lower_bound === null || $this->upper_bound === null) {
            return true;
        }
        return !($this->lower_bound === 0 && $this->upper_bound === 0);
    }

    public function isAlwaysTruthy(): bool
    {
        if ($this->is_nullable) {
            return false;
        }
        if ($this->lower_bound === null || $this->upper_bound === null) {
            return false;
        }
        return $this->lower_bound > 0 || $this->upper_bound < 0;
    }

    public function asFQSENString(): string
    {
        return self::NAME;
    }

    /**
     * @return string
     * A human readable representation of this int-range type including the bounds
     */
    public function __toString(): string
    {
        return $this->memoize(__METHOD__, function (): string {
            $string = $this->asFQSENString();

            // Include the range bounds in the string representation for clearer error messages
            if ($this->lower_bound !== null && $this->upper_bound !== null) {
                $string .= '<' . $this->lower_bound . ', ' . $this->upper_bound . '>';
            } elseif (count($this->template_parameter_type_list) > 0) {
                $string .= $this->templateParameterTypeListAsString();
            }

            if ($this->is_nullable) {
                $string = '?' . $string;
            }

            return $string;
        });
    }

    /**
     * @param list<UnionType> $template_parameter_type_list
     * @return ?list<UnionType>
     */
    private static function normalizeTemplateParameterTypeList(array $template_parameter_type_list): ?array
    {
        if (\count($template_parameter_type_list) < 2) {
            return null;
        }
        $lower = self::literalIntFromUnion($template_parameter_type_list[0]);
        $upper = self::literalIntFromUnion($template_parameter_type_list[1]);
        if ($lower === null || $upper === null) {
            return null;
        }
        if ($lower > $upper) {
            [$lower, $upper] = [$upper, $lower];
        }
        return [
            LiteralIntType::instanceForValue($lower, false)->asPHPDocUnionType(),
            LiteralIntType::instanceForValue($upper, false)->asPHPDocUnionType(),
        ];
    }

    /**
     * @param list<UnionType> $template_parameter_type_list
     * @return array{?int,?int}
     */
    private static function extractBounds(array $template_parameter_type_list): array
    {
        $lower = isset($template_parameter_type_list[0]) ? self::literalIntFromUnion($template_parameter_type_list[0]) : null;
        $upper = isset($template_parameter_type_list[1]) ? self::literalIntFromUnion($template_parameter_type_list[1]) : null;
        if ($lower !== null && $upper !== null && $lower > $upper) {
            [$lower, $upper] = [$upper, $lower];
        }
        return [$lower, $upper];
    }

    private static function literalIntFromUnion(?UnionType $union_type): ?int
    {
        if (!$union_type || $union_type->isEmpty()) {
            return null;
        }
        $type_set = $union_type->getTypeSet();
        if (\count($type_set) !== 1) {
            return null;
        }
        $type = \reset($type_set);
        return $type instanceof LiteralIntType ? $type->getValue() : null;
    }
}
