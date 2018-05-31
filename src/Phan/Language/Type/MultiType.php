<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\Type;

/**
 * Callers should split this up into multiple Type instances.
 */
interface MultiType
{
    /**
     * @return array<int,Type>
     */
    public function asIndividualTypeInstances() : array;
}
