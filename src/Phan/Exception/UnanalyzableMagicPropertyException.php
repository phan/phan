<?php

declare(strict_types=1);

namespace Phan\Exception;

use ast\Node;
use Phan\Language\Element\Clazz;

/**
 * Thrown when Phan unexpectedly fails to analyze a given magic property and cannot proceed.
 */
final class UnanalyzableMagicPropertyException extends UnanalyzableException
{
    /** @var Clazz */
    private $class;
    /** @var string */
    private $prop_name;

    public function __construct(Node $node, Clazz $class, string $prop_name, string $message)
    {
        parent::__construct($node, $message);
        $this->class = $class;
        $this->prop_name = $prop_name;
    }

    /**
     * Returns the class which the magic property belongs to
     */
    public function getClass(): Clazz
    {
        return $this->class;
    }

    /**
     * Returns the property name of the magic property
     * @suppress PhanUnreferencedPublicMethod added for API completeness
     */
    public function getPropName(): string
    {
        return $this->prop_name;
    }
}
