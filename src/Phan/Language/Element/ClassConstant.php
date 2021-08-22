<?php

declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\AST\ASTReverter;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\UnionType;
use Phan\Library\StringUtil;

/**
 * ClassConstant represents the information Phan has
 * about the declaration of a class constant.
 */
class ClassConstant extends ClassElement implements ConstantInterface
{
    use ConstantTrait;
    use HasAttributesTrait;

    /** @var ?Comment the phpdoc comment associated with this declaration, if any exists. */
    private $comment;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfied by this
     * typed structural element.
     *
     * @param int $flags
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     *
     * @param FullyQualifiedClassConstantName $fqsen
     * A fully qualified name for the class constant
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedClassConstantName $fqsen
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        // Presume that this is the original definition
        // of this class constant, and let it be overwritten
        // if it isn't.
        $this->setDefiningFQSEN($fqsen);
    }

    /**
     * Override the default getter to fill in a future
     * union type if available.
     */
    public function getUnionType(): UnionType
    {
        $union_type = $this->getFutureUnionType();
        if (!\is_null($union_type)) {
            $this->setUnionType($union_type);
        }

        return parent::getUnionType();
    }

    /**
     * @return FullyQualifiedClassConstantName
     * The fully-qualified structural element name of this
     * structural element
     * @suppress PhanTypeMismatchReturn (FQSEN on declaration)
     */
    public function getFQSEN(): FQSEN
    {
        return $this->fqsen;
    }

    public function __toString(): string
    {
        return $this->getVisibilityName() . ' const ' . $this->name;
    }

    /**
     * Used for generating issue messages
     */
    public function asVisibilityAndFQSENString(): string
    {
        return $this->getVisibilityName() . ' ' .
            $this->getClassFQSEN()->__toString() .
            '::' .
            $this->name;
    }

    public function getMarkupDescription(): string
    {
        $string = '';

        if ($this->isProtected()) {
            $string .= 'protected ';
        } elseif ($this->isPrivate()) {
            $string .= 'private ';
        }
        if ($this->isFinal()) {
            $string .= 'final ';
        }

        $string .= 'const ' . $this->name . ' = ';
        $value_node = $this->getNodeForValue();
        $string .= ASTReverter::toShortString($value_node);
        return $string;
    }

    /**
     * Returns the visibility of this class constant
     * (either 'public', 'protected', or 'private')
     */
    public function getVisibilityName(): string
    {
        if ($this->isPrivate()) {
            return 'private';
        } elseif ($this->isProtected()) {
            return 'protected';
        } else {
            return 'public';
        }
    }

    /**
     * Returns true if this is a final element
     */
    public function isFinal(): bool
    {
        return $this->getFlagsHasState(\ast\flags\MODIFIER_FINAL);
    }

    /**
     * Converts this class constant to a stub php snippet that can be used by `tool/make_stubs`
     */
    public function toStub(): string
    {
        $string = '';
        if (self::shouldAddDescriptionsToStubs()) {
            $description = (string)MarkupDescription::extractDescriptionFromDocComment($this);
            $string .= MarkupDescription::convertStringToDocComment($description, '    ');
        }
        $string .= '    ';
        if ($this->isPrivate()) {
            $string .= 'private ';
        } elseif ($this->isProtected()) {
            $string .= 'protected ';
        }

        // For PHP 7.0 compatibility of stubs,
        // show public class constants as 'const', not 'public const'.
        // Also, PHP modules probably won't have private/protected constants.
        $string .= 'const ' . $this->name . ' = ';
        $fqsen = $this->fqsen->__toString();
        if (\defined($fqsen)) {
            // TODO: Could start using $this->getNodeForValue()?
            // NOTE: This is used by tool/make_stubs, which is why it uses reflection instead of getting a node.
            $string .= StringUtil::varExportPretty(\constant($fqsen)) . ';';
        } else {
            $string .= "null;  // could not find";
        }
        return $string;
    }

    /**
     * Set the phpdoc comment associated with this class comment.
     */
    public function setComment(?Comment $comment): void
    {
        $this->comment = $comment;
    }

    /**
     * Get the phpdoc comment associated with this class comment.
     */
    public function getComment(): ?Comment
    {
        return $this->comment;
    }
}
