<?php declare(strict_types=1);

namespace Phan\Language\Element;

use InvalidArgumentException;
use Phan\AST\ASTReverter;
use Phan\Exception\FQSENException;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\Type;
use Phan\Language\UnionType;

/**
 * Phan's representation of a global constant
 */
class GlobalConstant extends AddressableElement implements ConstantInterface
{
    use ConstantTrait;

    /**
     * Override the default getter to fill in a future
     * union type if available.
     *
     * @return UnionType
     */
    public function getUnionType() : UnionType
    {
        if (null !== ($union_type = $this->getFutureUnionType())) {
            $this->setUnionType(parent::getUnionType()->withUnionType($union_type));
        }

        return parent::getUnionType();
    }

    /**
     * @return FullyQualifiedGlobalConstantName
     * The fully-qualified structural element name of this
     * structural element
     */
    public function getFQSEN() : FullyQualifiedGlobalConstantName
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
    ) : GlobalConstant {
        if (!\defined($name)) {
            throw new InvalidArgumentException(\sprintf("This should not happen, defined(%s) is false, but the constant was returned by get_defined_constants()", \var_export($name, true)));
        }
        $value = \constant($name);
        $constant_fqsen = FullyQualifiedGlobalConstantName::fromFullyQualifiedString(
            '\\' . $name
        );
        $result = new self(
            new Context(),
            $name,
            Type::fromObject($value)->asUnionType(),
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
    public function toStub() : string
    {
        list($namespace, $string) = $this->toStubInfo();
        $namespace_text = $namespace === '' ? '' : "$namespace ";
        $string = \sprintf("namespace %s{\n%s}\n", $namespace_text, $string);
        return $string;
    }

    public function getMarkupDescription() : string
    {
        $string = 'const ' . $this->getName() . ' = ';
        $value_node = $this->getNodeForValue();
        $string .= ASTReverter::toShortString($value_node);
        return $string;
    }

    /** @return array{0:string,1:string} [string $namespace, string $text] */
    public function toStubInfo() : array
    {
        $fqsen = (string)$this->getFQSEN();
        $pos = \strrpos($fqsen, '\\');
        if ($pos !== false) {
            $name = (string)\substr($fqsen, $pos + 1);
        } else {
            $name = $fqsen;
        }

        $is_defined = \defined($fqsen);
        if ($is_defined) {
            $repr = \var_export(\constant($fqsen), true);
            $comment = '';
        } else {
            $repr = 'null';
            $comment = '  // could not find';
        }
        $namespace = \ltrim($this->getFQSEN()->getNamespace(), '\\');
        if (\preg_match('@^[a-zA-Z_\x7f-\xff\\\][a-zA-Z0-9_\x7f-\xff\\\]*$@', $name)) {
            $string = "const $name = $repr;$comment\n";
        } else {
            // Internal extension defined a constant with an invalid identifier.
            $string = \sprintf("define(%s, %s);%s\n", \var_export($name, true), $repr, $comment);
        }
        return [$namespace, $string];
    }
}
