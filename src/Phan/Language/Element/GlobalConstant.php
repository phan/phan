<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use InvalidArgumentException;
use Phan\AST\ASTReverter;
use Phan\Exception\FQSENException;
use Phan\Exception\IssueException;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FutureUnionType;
use Phan\Language\Type;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\FalseType;
use Phan\Language\Type\TrueType;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;

/**
 * Phan's representation of a global constant
 * @property FullyQualifiedGlobalConstantName $fqsen
 */
class GlobalConstant extends AddressableElement implements ConstantInterface
{
    use ConstantTrait;

    /** @var array<string,true> names of internal boolean constants whose values vary between builds */
    private const VOLATILE_BOOLEAN_CONSTANTS = [
        'PHP_ZTS' => true,
        'PHP_DEBUG' => true,
        'ZEND_THREAD_SAFE' => true,
        'ZEND_DEBUG_BUILD' => true,
    ];

    /**
     * @var array<string,UnionType|FutureUnionType>
     * Types of additional `define()` calls for this constant (other than the first one seen),
     * keyed by "file:line". Unresolved FutureUnionTypes are resolved lazily in getUnionType().
     *
     * PHP only allows a constant to be defined once, but when `define()` is used in mutually
     * exclusive branches (e.g. `if ($x) { define('C', 1); } else { define('C', true); }`),
     * any one of them may be the definition at runtime, so the union of all of them is used.
     */
    private array $alternate_definition_types = [];

    /**
     * @var UnionType|null the cached union of the primary type and all alternate definition types.
     */
    private ?UnionType $combined_union_type = null;

    /**
     * Sets whether this is a global constant that was declared with `define()` instead of `const`.
     *
     * The real type of a dynamic constant is widened to the non-literal type (e.g. `false` becomes `bool`),
     * because `define()` is typically used for configuration values that vary between deployments,
     * and warning about redundant/impossible conditions on those would be noise.
     */
    public function setIsDynamicConstant(bool $dynamic_constant): void
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::IS_DYNAMIC_CONSTANT,
                $dynamic_constant
            )
        );
    }

    /**
     * @return bool
     * True if this is a global constant that was declared with `define()` instead of `const`.
     */
    public function isDynamicConstant(): bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_DYNAMIC_CONSTANT);
    }

    /**
     * Override the default getter to fill in a future
     * union type if available.
     */
    public function getUnionType(): UnionType
    {
        if (null !== ($union_type = $this->getFutureUnionType())) {
            $this->setUnionType($union_type);
        }
        if (!$this->alternate_definition_types) {
            return parent::getUnionType();
        }
        return $this->combined_union_type ??= $this->computeCombinedUnionType();
    }

    private function computeCombinedUnionType(): UnionType
    {
        $result = parent::getUnionType();
        foreach ($this->alternate_definition_types as $key => $type) {
            if ($type instanceof FutureUnionType) {
                try {
                    $type = self::widenDynamicConstantType($type->get());
                } catch (IssueException) {
                    unset($this->alternate_definition_types[$key]);
                    continue;
                }
                $this->alternate_definition_types[$key] = $type;
            }
            $result = $result->withUnionType($type);
        }
        return $result;
    }

    public function setUnionType(UnionType $type): void
    {
        if ($this->isDynamicConstant()) {
            $type = self::widenDynamicConstantType($type);
        }
        $this->combined_union_type = null;
        parent::setUnionType($type);
    }

    /**
     * Records the type of an additional `define()` call for this constant (in a different location from the first one).
     *
     * @param string $key a unique key for the location of the `define()` call, e.g. "file:line"
     */
    public function addAlternateDefinitionType(string $key, UnionType|FutureUnionType $type): void
    {
        if ($type instanceof UnionType) {
            $type = self::widenDynamicConstantType($type);
        }
        $this->alternate_definition_types[$key] = $type;
        $this->combined_union_type = null;
    }

    /**
     * Forgets the type recorded by addAlternateDefinitionType() for $key (used to undo parsing a file in daemon mode).
     */
    public function removeAlternateDefinitionType(string $key): void
    {
        unset($this->alternate_definition_types[$key]);
        $this->combined_union_type = null;
    }

    /**
     * Copies the alternate definition types from $other (e.g. when this constant replaces $other in the code base).
     */
    public function copyAlternateDefinitionTypesFrom(GlobalConstant $other): void
    {
        if ($other === $this || !$other->alternate_definition_types) {
            return;
        }
        $this->alternate_definition_types = $other->alternate_definition_types + $this->alternate_definition_types;
        $this->combined_union_type = null;
    }

    /**
     * Widens the real type set of a constant declared with `define()` to non-literal types
     * (e.g. `3` to `int`, `'name'` to `string`, `true` to `bool`).
     *
     * The phpdoc type keeps the literal value so that it can still be used e.g. for array shape keys.
     * The real type is widened because `define()` is typically used for values that vary between
     * deployments (feature flags, environment-specific settings, version constants),
     * where warnings about redundant/impossible conditions such as `if (DEBUG_MODE)` would be noise.
     * The non-literal real type is still enough to detect impossible comparisons between different types.
     */
    public static function widenDynamicConstantType(UnionType $type): UnionType
    {
        $real_type_set = $type->getRealTypeSet();
        if (!$real_type_set) {
            return $type;
        }
        $new_real_type_set = [];
        foreach ($real_type_set as $real_type) {
            if ($real_type instanceof TrueType || $real_type instanceof FalseType) {
                $real_type = BoolType::instance($real_type->isNullable());
            } else {
                $real_type = $real_type->asNonLiteralType();
            }
            $new_real_type_set[] = $real_type;
        }
        return $type->withRealTypeSet(UnionType::getUniqueTypes($new_real_type_set));
    }

    /**
     * @return FullyQualifiedGlobalConstantName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN(): FullyQualifiedGlobalConstantName
    {
        return $this->fqsen;
    }

    /**
     * @param string $name
     * The name of a builtin constant to build a new GlobalConstant structural
     * element from.
     *
     * @return GlobalConstant
     * A GlobalConstant structural element representing the given named
     * builtin constant.
     *
     * @throws InvalidArgumentException
     * If reflection could not locate the builtin constant.
     *
     * @throws FQSENException
     * If a module declares an invalid constant FQSEN
     */
    public static function fromGlobalConstantName(
        string $name
    ): GlobalConstant {
        if (!\defined($name)) {
            throw new InvalidArgumentException(\sprintf("This should not happen, defined(%s) is false, but the constant was returned by get_defined_constants()", \var_representation($name)));
        }
        $value = \constant($name);
        $constant_fqsen = FullyQualifiedGlobalConstantName::fromFullyQualifiedString(
            '\\' . $name
        );
        $type = Type::fromObject($value);
        $real_type = $type->asNonLiteralType();
        if (isset(self::VOLATILE_BOOLEAN_CONSTANTS[$name]) && is_bool($value)) {
            $type = $real_type = BoolType::instance(false);
        }
        $result = new self(
            new Context(),
            $name,
            UnionType::of([$type], [$real_type]),
            0,
            $constant_fqsen
        );
        $result->setNodeForValue($value);
        return $result;
    }

    /**
     * Returns a standalone stub of PHP code for this global constant.
     * @suppress PhanUnreferencedPublicMethod toStubInfo is used by callers instead
     */
    public function toStub(): string
    {
        [$namespace, $string] = $this->toStubInfo();
        $namespace_text = $namespace === '' ? '' : "$namespace ";
        $string = \sprintf("namespace %s{\n%s}\n", $namespace_text, $string);
        return $string;
    }

    public function getMarkupDescription(): string
    {
        $string = 'const ' . $this->name . ' = ';
        $value_node = $this->getNodeForValue();
        $string .= ASTReverter::toShortString($value_node);
        return $string;
    }

    /** @return array{0:string,1:string} [string $namespace, string $text] */
    public function toStubInfo(): array
    {
        $fqsen = (string)$this->fqsen;
        $pos = \strrpos($fqsen, '\\');
        if ($pos !== false) {
            $name = \substr($fqsen, $pos + 1);
        } else {
            $name = $fqsen;
        }

        $is_defined = \defined($fqsen);
        if ($is_defined) {
            $value = \constant($fqsen);
            // Resources (like STDIN, STDOUT, STDERR) cannot be represented in stubs
            if (\is_resource($value)) {
                $resource_type = \get_resource_type($value);
                $repr = 'null';
                $comment = "  // resource ($resource_type) - cannot be represented in stubs";
            } else {
                $repr = StringUtil::varExportPretty($value);
                $comment = '';
            }
        } else {
            $repr = 'null';
            $comment = '  // could not find';
        }
        $namespace = \ltrim($this->fqsen->getNamespace(), '\\');
        if (\preg_match('@^[a-zA-Z_\x7f-\xff\\\][a-zA-Z0-9_\x7f-\xff\\\]*$@D', $name)) {
            $string = "const $name = $repr;$comment\n";
        } else {
            // Internal extension defined a constant with an invalid identifier.
            $string = \sprintf("define(%s, %s);%s\n", \var_representation($name), $repr, $comment);
        }
        if (self::shouldAddDescriptionsToStubs()) {
            $description = (string)MarkupDescription::extractDescriptionFromDocComment($this);
            $string = MarkupDescription::convertStringToDocComment($description) . $string;
        }
        return [$namespace, $string];
    }

    /**
     * PHP does not support parsing attributes on global constants.
     *
     * @return list<Attribute>
     */
    public function getAttributeList(): array
    {
        return [];
    }
}
