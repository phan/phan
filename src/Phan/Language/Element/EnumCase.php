<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\AST\ASTReverter;

/**
 * EnumCase represents the information Phan has
 * about the declaration of an enum case, which is a subtype of a class constant.
 */
class EnumCase extends ClassConstant
{
    /** @var string|int|null the case value, if known */
    private $enum_case_value;

    public function __toString(): string
    {
        return 'case ' . $this->name;
    }

    /**
     * If the enum case value exists and could be evaluated, this contains that value
     * @param int|string|null $value
     */
    public function setEnumCaseValue($value): void
    {
        $this->enum_case_value = $value;
    }

    /**
     * Gets the value for this enum case, if one existed AND could be evaluated.
     * @see ClassConstant::getNodeForValue() for checking if the enum case has a value
     * @return int|string|null
     * @suppress PhanUnreferencedPublicMethod made available for plugins. Can also be computed from getNodeForValue.
     */
    public function getEnumCaseValue()
    {
        return $this->enum_case_value;
    }

    public function getMarkupDescription(): string
    {
        $string = 'enum ' . $this->name;
        $value_node = $this->getNodeForValue();
        if ($value_node !== null) {
            $string .= ' = ' . ASTReverter::toShortString($value_node);
        }

        return $string;
    }

    /**
     * Converts this enum case to a stub php snippet that can be used by `tool/make_stubs`
     */
    public function toStub(): string
    {
        $string = '';
        if (self::shouldAddDescriptionsToStubs()) {
            $description = (string)MarkupDescription::extractDescriptionFromDocComment($this);
            $string .= MarkupDescription::convertStringToDocComment($description, '    ');
        }
        $string .= '    enum ' . $this->name;

        $value_node = $this->getNodeForValue();
        if ($value_node !== null) {
            $string .= ' = ' . ASTReverter::toShortString($value_node);
        }
        $string .= ';';

        return $string;
    }
}
