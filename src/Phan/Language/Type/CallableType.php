<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\FQSEN;

class CallableType extends NativeType
{
    const NAME = 'callable';

    /**
     * @var FQSEN|null
     */
    private $fqsen;

    // Same as instance(), but guaranteed not to have memoized state.
    private static function callableInstance() : CallableType {
        static $instance = null;
        if (empty($instance)) {
            $instance = self::make('\\', static::NAME, []);
        }
        return $instance;
    }

    public static function instanceWithClosureFQSEN(FQSEN $fqsen)
    {
        // Use an instance with no memoized or lazily initialized results.
        // Avoids picking up changes to CallableType::instance() in the case that a result depends on asFQSEN()
        $instance = clone(self::callableInstance());
        $instance->fqsen = $fqsen;
        return $instance;
    }

    /**
     * Override asFQSEN to return the closure's FQSEN
     */
    public function asFQSEN() : FQSEN
    {
        if (!empty($this->fqsen)) {
            return $this->fqsen;
        }

        return parent::asFQSEN();
    }
}
