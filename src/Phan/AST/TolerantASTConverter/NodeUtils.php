<?php

declare(strict_types=1);

namespace Phan\AST\TolerantASTConverter;

use Microsoft\PhpParser\Node\QualifiedName;
use Microsoft\PhpParser\Token;
use Microsoft\PhpParser\TokenKind;

/**
 * Miscellaneous utilities for converting nodes to strings.
 *
 * This deliberately duplicates some functionality in TolerantASTConverter for use outside of TolerantASTConverter.
 * (static methods are faster inside of TolerantASTConverter)
 */
final class NodeUtils
{
    /** @var string */
    private $file_contents;

    public function __construct(string $file_contents)
    {
        $this->file_contents = $file_contents;
    }

    /**
     * Convert a token to the string it represents, without whitespace or `$`.
     *
     * @phan-suppress PhanPartialTypeMismatchArgumentInternal hopefully in range
     */
    public function tokenToString(Token $n): string
    {
        $result = \trim($n->getText($this->file_contents));
        $kind = $n->kind;
        if ($kind === TokenKind::VariableName) {
            return \trim($result, '$');
        }
        return $result;
    }

    /**
     * Converts a qualified name to the string it represents, combining name parts.
     */
    public function phpParserNameToString(QualifiedName $name): string
    {
        $name_parts = $name->nameParts;
        // TODO: Handle error case (can there be missing parts?)
        $result = '';
        foreach ($name_parts as $part) {
            $part_as_string = $this->tokenToString($part);
            if ($part_as_string !== '') {
                $result .= \trim($part_as_string);
            }
        }
        $result = \rtrim(\preg_replace('/\\\\{2,}/', '\\', $result), '\\');
        if ($result === '') {
            // Would lead to "The name cannot be empty" when parsing
            throw new InvalidNodeException();
        }
        return $result;
    }
}
