<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\UnionType;

/**
 * Not a type, but used by ClosureDeclarationType
 */
final class ClosureDeclarationParameter
{
    /** @var UnionType */
    private $type;

    /** @var bool */
    private $is_variadic;

    /** @var bool */
    private $is_reference;

    /** @var bool */
    private $is_optional;

    public function __construct(UnionType $type, bool $is_variadic, bool $is_reference, bool $is_optional)
    {
        $this->type = $type;
        $this->is_variadic = $is_variadic;
        $this->is_reference = $is_reference;
        $this->is_optional = $is_optional || $is_variadic;
    }

    public function getNonVariadicUnionType() : UnionType
    {
        return $this->type;
    }

    public function isVariadic() : bool
    {
        return $this->is_variadic;
    }

    public function isPassByReference() : bool
    {
        return $this->is_reference;
    }

    public function isOptional() : bool
    {
        return $this->is_optional;
    }

    // for debugging
    public function __toString() : string
    {
        $repr = $this->type->__toString();
        if ($this->is_reference) {
            $repr .= '&';
        }
        if ($this->is_variadic) {
            $repr .= '...';
        }
        if ($this->is_optional && !$this->is_variadic) {
            $repr .= '=';
        }
        return $repr;
    }

    public function toStub(string $paramName) : string
    {
        $repr = $this->type->__toString() . ' ';
        if ($this->is_variadic) {
            $repr .= '...';
        }
        if ($this->is_reference) {
            $repr .= '&';
        }
        $repr .= '$' . $paramName;
        if ($this->is_optional && !$this->is_variadic) {
            $repr .= '=';
        }
        return $repr;
    }

    public function generateUniqueId() : string
    {
        $repr = (string)$this->type->generateUniqueId();
        if ($this->is_variadic) {
            $repr .= '...';
        }
        if ($this->is_reference) {
            $repr .= '&';
        }
        if ($this->is_optional && !$this->is_variadic) {
            $repr .= '=';
        }
        return $repr;
    }

    /**
     * @see \Phan\Analysis\ParameterTypesAnalyzer::analyzeOverrideSignatureForOverriddenMethod() - Similar logic using LSP
     */
    public function canCastToParameterIgnoringVariadic(ClosureDeclarationParameter $other) : bool
    {
        if ($this->is_reference !== $other->is_reference) {
            return false;
        }
        if (!$this->is_optional && $other->is_optional) {
            // We should have already checked this
            return false;
        }
        // TODO: stricter? (E.g. shouldn't allow int|string to cast to int)
        return $this->type->canCastToUnionType($other->getNonVariadicUnionType());
    }
}
