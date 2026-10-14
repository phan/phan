<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\Language\Element\Method;

/**
 * A pristine clone of a method that lazy entries of inherited methods are created from (see LazyInheritedMethods).
 *
 * An eager import of a parent class clones the method that the parent has under each name. A lazy entry instead
 * records the import, and its copy is created later from this template, which is a clone of the same method taken
 * while that method had the same state as at the import. The template is valid for the imports whose sequence
 * numbers are in [captured_seq, superseded_seq): if the method's state changes, the next import captures a new template.
 *
 * @internal
 * @phan-file-suppress PhanWriteOnlyPublicProperty
 */
final class LazyInheritedMethodTemplate
{
    /** @var Method the root: the method that eager imports would clone (kept so that its object id is not reused) */
    public $root;

    /** @var Method the clone of $root (with cloned parameters) that copies are cloned from; never modified */
    public $template;

    /**
     * @var ?Method the method whose return type a copy uses (Method::getReturnTypeLinkOfClone() of $root
     * when this was captured), or null if copies use their own return type
     */
    public $link_target;

    /** @var int the sequence number of the import that captured this template */
    public $captured_seq;

    /** @var int the sequence number of the first import that found the root changed, or PHP_INT_MAX */
    public $superseded_seq = \PHP_INT_MAX;

    /** @var list<int> LazyInheritedMethodsEligibility::getStateVersionsOfRoot() of $root when this was captured */
    public $versions;

    /**
     * @var ?array<string,mixed> with PHAN_LAZY_INHERIT_VERIFY=1: snapshots of the properties of $root, its scope, its
     * variables and its parameters when this was captured (for the exhaustive comparison at each later import)
     */
    public $snapshot = null;

    /**
     * @param list<int> $versions
     */
    public function __construct(Method $root, Method $template, ?Method $link_target, int $captured_seq, array $versions)
    {
        $this->root = $root;
        $this->template = $template;
        $this->link_target = $link_target;
        $this->captured_seq = $captured_seq;
        $this->versions = $versions;
    }
}
