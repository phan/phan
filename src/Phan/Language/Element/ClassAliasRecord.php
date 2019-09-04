<?php declare(strict_types=1);

namespace Phan\Language\Element;

use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;

/**
 * A ClassAliasRecord represents the information Phan parsed from calls
 * to class_alias() within the codebase (FQSEN and location of alias creation)
 *
 * The original class is mapped to a set of ClassAliasRecord
 * @phan-immutable
 */
class ClassAliasRecord
{

    /** @var FullyQualifiedClassName the FQSEN of the alias that will be created. */
    public $alias_fqsen;

    /** @var Context - the context of the class_alias() call */
    public $context;

    /** @var int - the line number of the class_alias() call */
    public $lineno;

    public function __construct(FullyQualifiedClassName $alias_fqsen, Context $context, int $lineno)
    {
        $this->alias_fqsen = $alias_fqsen;
        $this->context = $context;
        $this->lineno = $lineno;
    }
}
