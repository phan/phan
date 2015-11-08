<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Language\Type;

abstract class NativeType extends Type {

    public function instance() : None {
        static $instance = null;

        if (empty($instance)) {
            $instance = new self();
        }

        return $instance;
    }

    public function isNativeType() : bool {
        return true;
    }

    public function isSelfType() : bool {
        return false;
    }

    public function __toString() : string {
        // Native types can just use their
        // non-fully-qualified names
        return $this->name;
    }


}
