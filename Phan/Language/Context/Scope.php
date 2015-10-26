<?php
declare(strict_types=1);
namespace Phan\Language\Context;

class Scope {
    /**
     * @var string[]
     */
    private $variable_list = [];

    public function __construct() {
    }

    public function hasVariableName(string $name) : bool {

    }

}
