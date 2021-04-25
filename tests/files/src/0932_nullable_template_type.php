<?php declare(strict_types=1);

namespace App\Lib;

use TypeError;

/**
 * @template U
 */
class Test
{
    /** @var U */
    public $value;

    /**
     * @param ?U $value
     */
    public function __construct($value = null) {
        $this->value = Test::notNull($value);
    }

    /**
     * @template T
     *
     * @param ?T $subject
     * @return T
     * @throws TypeError
     */
    public static function notNull($subject)
    {
        if (null === $subject) {
            throw new TypeError(sprintf(
                '%s must not be null.',
                gettype($subject)
            ));
        }
        return $subject;
    }

    /**
     * @template T
     *
     * @param T $subject
     * @return T not able to narrow - need to add null to param type to filter it out
     * @throws TypeError
     */
    public static function notNullV2($subject)
    {
        if (null === $subject) {
            throw new TypeError(sprintf(
                '%s must not be null.',
                gettype($subject)
            ));
        }
        if (null === $subject) {
            throw new \Error('redundant');
        }
        return $subject;
    }
}

function example(?string $s): ?int {
    $t = new Test($s);
    if (rand(0, 1)) {
        if (rand(0, 1)) {
            return (new Test(rand(0, 1) ? new \stdClass() : null))->value;
        }
        return $t->value;
    }
    if (rand(0, 1)) {
        return Test::notNullV2($s);
    }
    return Test::notNull($s);  // should infer string, not ?string
}

