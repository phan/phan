<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\FQSEN;
use Phan\Language\Type;

final class ClosureType extends Type
{
    /** Not an override */
    const NAME = 'Closure';

    /**
     * @var FQSEN|null
     */
    private $fqsen;

    // Same as instance(), but guaranteed not to have memoized state.
    private static function closureInstance() : ClosureType {
        static $instance = null;
        if (empty($instance)) {
            $instance = self::make('\\', self::NAME, [], false, self::FROM_NODE);
        }
        return $instance;
    }

    public static function instanceWithClosureFQSEN(FQSEN $fqsen)
    {
        // Use an instance with no memoized or lazily initialized results.
        // Avoids picking up changes to ClosureType::instance(false) in the case that a result depends on asFQSEN()
        $instance = clone(self::closureInstance());
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
        if ($type->isCallable()) {
            return !$this->getIsNullable() || $type->getIsNullable();
        }

        return parent::canCastToNonNullableType($type);
    }

    /**
     * @param bool $is_nullable
     * If true, returns a nullable instance of this closure type
     *
     * @return static
     */
    public static function instance(bool $is_nullable)
    {
        if ($is_nullable) {
            static $nullable_instance = null;

            if (empty($nullable_instance)) {
                $nullable_instance = self::make('\\', self::NAME, [], true, Type::FROM_NODE);
            }
            \assert($nullable_instance instanceof self);

            return $nullable_instance;
        }

        static $instance = null;

        if (empty($instance)) {
            $instance = self::make('\\', self::NAME, [], false, Type::FROM_NODE);
        }

        \assert($instance instanceof self);
        return $instance;
    }

    /**
     * @return bool
     * True if this type is a callable or a Closure.
     */
    public function isCallable() : bool
    {
        return true;
    }
}
