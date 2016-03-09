<?php declare(strict_types=1);
namespace Phan\Language\Type;

use Phan\Language\FQSEN;

class CallableType extends NativeType
{
    const NAME = 'callable';

    /**
     * @var FQSEN
     */
    private $fqsen;

    public static function instanceWithClosureFQSEN(FQSEN $fqsen)
    {
        $instance = self::instance();
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
