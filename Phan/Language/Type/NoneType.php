<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Language\Type;

class None extends Type {

    protected $name = '';

    public function instance() : None {
        static $instance = null;

        if (empty($instance)) {
            $instance = new self();
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
