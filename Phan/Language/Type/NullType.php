<?php declare(strict_types=1);
namespace Phan\Language\Type;

use \Phan\Language\Type;

class NullType extends ScalarType {
    protected $name = 'null';
}
