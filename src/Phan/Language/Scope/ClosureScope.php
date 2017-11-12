<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;

// TODO: Wrap this with a ClosureLikeScope
class ClosureScope extends FunctionLikeScope
{
    /**
     * @var FullyQualifiedClassName|null
     */
    private $override_class_fqsen = null;

    /**
     * @return void
     */
    public function overrideClassFQSEN(FullyQualifiedClassName $fqsen = null)
    {
        $this->override_class_fqsen = $fqsen;
    }

    /**
     * @return FullyQualifiedClassName|null
     */
    public function getOverrideClassFQSEN()
    {
        return $this->override_class_fqsen;
    }

    /**
     * @return bool
     * True if we're in a class scope
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
        if ($this->override_class_fqsen) {
            return $this->override_class_fqsen;
        }
        return parent::getClassFQSEN();
    }
}
