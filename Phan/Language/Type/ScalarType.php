<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Language\Type;

abstract class ScalarType extends Type {
    public function isScalar() : bool {
        return true;
    }
}
