<?php declare(strict_types=1);

namespace Phan\Plugin\Internal\UseReturnValuePlugin;

use Phan\Language\Context;
use Phan\Language\Element\FunctionInterface;

/**
 * Information about the function and the locations where the function was called for one FQSEN
 */
class StatsForFQSEN
{
    /** @var array<string,Context> the locations where the return value was unused */
    public $unused_locations = [];
    /** @var array<string,Context> the locations where the return value was used */
    public $used_locations = [];
    /** @var bool is this function fqsen internal to PHP */
    public $is_internal;

    public function __construct(FunctionInterface $function)
    {
        $this->is_internal = $function->isPHPInternal();
    }
}
