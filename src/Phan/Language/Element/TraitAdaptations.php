<?php declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * This contains info for a single sub-node of a node of type \ast\AST_USE_TRAIT
 * (Which aliases of methods exist for this trait, which `insteadof` exist, etc)
 */
class TraitAdaptations
{
    /**
     * @var FullyQualifiedClassName
     * the FQSEN of the trait
     */
    private $trait_fqsen;

    /**
     * @var array<string,TraitAliasSource>
     * maps alias methods from this trait
     * to the info about the source method
     */
    public $alias_methods = [];

    /**
     * @var array<string,bool> Has an entry mapping name to true if a method with a given name is hidden.
     */
    public $hidden_methods = [];

    public function __construct(FullyQualifiedClassName $trait_fqsen)
    {
        $this->trait_fqsen = $trait_fqsen;
    }

    /**
     * Gets the trait's FQSEN
     */
    public function getTraitFQSEN() : FullyQualifiedClassName
    {
        return $this->trait_fqsen;
    }
}
