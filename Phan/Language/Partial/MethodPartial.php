<?php
declare(strict_types=1);
namespace Phan\Language\Partial;

use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
use \Phan\Language\FileRef;

class MethodPartial extends Partial {

    /**
     * @var ParameterPartial[]
     */
    private $parameter_list;

    /**
     * @var int
     */
    private $flags;

    /**
     * @var int
     */
    private $number_of_required_parameters = 0;

    /**
     * @var int
     */
    private $number_of_optional_parameters = 0;

}
