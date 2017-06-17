<?php declare(strict_types=1);
namespace Phan\Language\Element;

/**
 * This contains info for the source method of a trait alias.
 */
class TraitAliasSource
{
    /**
     * @var int line number where this trait method alias was created
     * (in the class using traits).
     */
    private $alias_lineno;

    /**
     * @var string source method name
     */
    private $source_method_name;

    /**
     * @var int the overridden visibility modifier, or 0 if the visibility didn't change
     */
    private $alias_visibility_flags;

    public function __construct(string $source_method_name, int $alias_lineno, int $alias_visibility_flags)
    {
        $this->source_method_name = $source_method_name;
        $this->alias_lineno = $alias_lineno;
        $this->alias_visibility_flags = $alias_visibility_flags;
    }

    public function getSourceMethodName() : string
    {
        return $this->source_method_name;
    }

    public function getAliasLineno() : int
    {
        return $this->alias_lineno;
    }

    public function getAliasVisibilityFlags() : int
    {
        return $this->alias_visibility_flags;
    }
}
