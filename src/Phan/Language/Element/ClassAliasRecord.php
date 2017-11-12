<?php declare(strict_types=1);

namespace Phan\Language\Element;

use ast\Node;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedClassName;

class ClassAliasRecord
{

    /** @var FullyQualifiedClassName the FQSEN of the alias that will be created. */
    public $alias_fqsen;

    /** @var Context - the context of the class_alias() call*/
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
