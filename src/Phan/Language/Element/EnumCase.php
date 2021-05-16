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
    public function __toString(): string
    {
        return 'case ' . $this->name;
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
