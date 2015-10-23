<?php
declare(strict_types=1);
namespace phan\language\context;

class Scope {
    /**
     * @var string[]
     */
    private $variable_list = [];

    public function __construct() {
    }

}
