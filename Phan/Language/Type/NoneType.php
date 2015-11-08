<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Language\Type;

class None extends Type {
    const NAME = '';

    public function instance() : None {
        static $instance = null;

        if (empty($instance)) {
            $instance = new static(static::NAME);
        }

        return $instance;
    }

    public function isNativeType() : bool {
        return false;
    }

    public function isSelfType() : bool {
        return false;
    }

    public function isScalar() : bool {
        return false;
    }

    public function __toString() : string {
        return '';
    }
}
