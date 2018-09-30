<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * Represents the Scope of a closure declaration, used by a Closure's Context.
 *
 * TODO: Wrap this with a ClosureLikeScope
 */
class ClosureScope extends FunctionLikeScope
{
    /**
     * The optional FQSEN of an (at)phan-closure-scope annotation. (an annotation used for closures that will be bound to a different class)
     * @var FullyQualifiedClassName|null
     */
    private $override_class_fqsen = null;

    /**
     * Override the class FQSEN inside this closure's scope (with an (at)phan-closure-scope annotation).
     * @return void
     */
    public function overrideClassFQSEN(FullyQualifiedClassName $fqsen = null)
    {
        $this->override_class_fqsen = $fqsen;
    }

    /**
     * @return FullyQualifiedClassName|null
     * If the (at)phan-closure-scope annotation is used, returns the corresponding override class FQSEN.
     * Returns the class FQSEN inside this closure's scope (with an (at)phan-closure-scope annotation).
     */
    public function getOverrideClassFQSEN()
    {
        return $this->override_class_fqsen;
    }

    /**
     * @return bool
     * True if this closure should be analyzed as if we're in a class scope
     */
    public function isInClassScope() : bool
    {
        if ($this->override_class_fqsen !== null) {
            return true;
        }
        return parent::isInClassScope();
    }

    /**
     * @return FullyQualifiedClassName
     * Crawl the scope hierarchy to get a class FQSEN.
     */
    public function getClassFQSEN() : FullyQualifiedClassName
    {
        return $this->override_class_fqsen ?? parent::getClassFQSEN();
    }
}
