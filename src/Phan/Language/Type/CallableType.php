<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\FQSEN;
use Phan\Language\Type;

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
            $instance = self::make('\\', static::NAME, [], false, self::FROM_NODE);
        }
        return $instance;
    }

    public static function instanceWithClosureFQSEN(FQSEN $fqsen)
    {
        // Use an instance with no memoized or lazily initialized results.
        // Avoids picking up changes to CallableType::instance(false) in the case that a result depends on asFQSEN()
        $instance = clone(self::callableInstance());
        $instance->fqsen = $fqsen;
        $instance->memoizeFlushAll();
        return $instance;
    }

    public function __clone() {
        assert($this->fqsen === null, 'should only clone null fqsen');
        $result = new static($this->namespace, $this->name, $this->template_parameter_type_list, $this->is_nullable);
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

    /**
     * @return bool
     * True if this Type can be cast to the given Type
     * cleanly
     */
    protected function canCastToNonNullableType(Type $type) : bool
    {
        $d = \strtolower((string)$type);
        if ($d[0] == '\\') {
            $d = \substr($d, 1);
        }

        // TODO: you can have a callable that isn't a closure
        //       This is wrong
        if ($d === 'closure') {
            return true;
        }

        return parent::canCastToNonNullableType($type);
    }

}
