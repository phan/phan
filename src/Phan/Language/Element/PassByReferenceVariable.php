<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\AST\ContextNode;
use Phan\CodeBase;
use Phan\Debug;
use Phan\Language\Context;
use Phan\Language\FileRef;
use Phan\Language\UnionType;
use ast\Node;

/**
 * This class wraps a parameter and a variable and proxies
 * calls to the variable but keeps the name of the parameter
 * allowing us to pass a variable into a method as a
 * pass-by-reference parameter so that its value can be
 * updated when re-analyzing the method.
 */
class PassByReferenceVariable extends Variable
{

    /** @var Parameter */
    private $parameter;

    /** @var Variable */
    private $variable;

    public function __construct(
        Parameter $parameter,
        Variable $variable
    ) {
        $this->parameter = $parameter;
        $this->variable = $variable;
    }

    public function getName() : string
    {
        return $this->parameter->getName();
    }

    public function getUnionType() : UnionType
    {
        return $this->variable->getUnionType();
    }

    public function setUnionType(UnionType $type)
    {
        $this->variable->setUnionType($type);
    }

    public function getFlags() : int
    {
        return $this->variable->getFlags();
    }

    public function setFlags(int $flags)
    {
        $this->variable->setFlags($flags);
    }

    public function getContext() : Context
    {
        return $this->variable->getContext();
    }

    public function getFileRef() : FileRef
    {
        return $this->variable->getFileRef();
    }

    public function isDeprecated() : bool
    {
        return $this->variable->isDeprecated();
    }

    public function setIsDeprecated(bool $is_deprecated)
    {
        $this->variable->setIsDeprecated($is_deprecated);
    }

    public function isInternal() : bool
    {
        return $this->variable->isInternal();
    }
}
