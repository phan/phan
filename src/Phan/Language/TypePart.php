<?php

declare(strict_types=1);

namespace Phan\Language;

/**
 * A part of a type extracted from phpdoc
 * @phan-pure
 */
class TypePart
{
    /** @var string the type string */
    public $type;
    /** @var string the separator before the type string */
    public $separator;
    public function __construct(string $type, string $separator)
    {
        $this->type = $type;
        $this->separator = $separator;
    }

    /**
     * Combine a list of type parts into a new type part instance.
     * @param non-empty-list<TypePart> $parts
     */
    public static function combine(array $parts): TypePart
    {
        $result = null;
        $separator = '';
        foreach ($parts as $part) {
            if ($result === null) {
                $result = $part->type;
                $separator = $part->separator;
                continue;
            }
            $result .= $part->separator . $part->type;
        }
        return new TypePart($result, $separator);
    }
}
