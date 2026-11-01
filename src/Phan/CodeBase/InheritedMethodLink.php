<?php

declare(strict_types=1);

namespace Phan\CodeBase;

use Phan\Analysis\LazyInheritedMethods;
use Phan\Language\Element\Clazz;

/**
 * The value of a lazy slot of a class map: a method that a class inherits from its parent class,
 * whose copy has not been created yet (see LazyInheritedMethods).
 *
 * One object is shared by every lazy slot created by one import of a parent class (Clazz::importAncestorClass()).
 * The copy is created ("materialized") from the method that the parent class had under the same name
 * when this import ran, the first time the method is looked up by name in the class.
 *
 * This is not part of Phan's plugin API.
 * @phan-file-suppress PhanWriteOnlyPublicProperty
 */
final class InheritedMethodLink
{
    /** @var LazyInheritedMethods the registry that created this link (ClassMap materializes through it) */
    public $registry;

    /** @var Clazz the class that inherits the methods (the class of the slots) */
    public $class;

    /** @var ClassMap the class map of $class */
    public $class_map;

    /** @var ClassMap the class map of $parent (the map the slots resolve through) */
    public $parent_map;

    /** @var Clazz the parent class the methods are inherited from */
    public $parent;

    /** @var int the sequence number of the import (CodeBase::nextLazySeq()) */
    public $seq;

    /**
     * @var array<string,int> for each slot of this link that is still lazy, by class map key:
     * the rank that its copy would have had in the order in which methods are added to the code base
     * (CodeBase::nextEagerOrdinal())
     */
    public $ordinals = [];

    /** @var int the number of slots of this link that are still lazy */
    public $unresolved = 0;

    /** @var int the number of slot history records (ClassMap::entryAt()) of $class_map whose value is this link */
    public $held_by_history = 0;

    public function __construct(
        LazyInheritedMethods $registry,
        Clazz $class,
        ClassMap $class_map,
        ClassMap $parent_map,
        Clazz $parent,
        int $seq
    ) {
        $this->registry = $registry;
        $this->class = $class;
        $this->class_map = $class_map;
        $this->parent_map = $parent_map;
        $this->parent = $parent;
        $this->seq = $seq;
    }
}
