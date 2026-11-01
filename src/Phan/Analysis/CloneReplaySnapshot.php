<?php

declare(strict_types=1);

namespace Phan\Analysis;

use Phan\Language\FutureUnionType;
use Phan\Language\UnionType;

/**
 * The state that the declaration analysis of a method wrote to it (see CloneReplay and Method::getCloneReplaySnapshot()).
 *
 * This is not part of Phan's plugin API.
 */
final class CloneReplaySnapshot
{
    /** @var array<string,UnionType> the phpdoc parameter type map */
    public $phpdoc_parameter_type_map;

    /** @var ?int the offset of the last parameter marked (at)phan-mandatory-param */
    public $last_mandatory_phpdoc_param_offset;

    /** @var list<array{0:UnionType,1:int,2:?UnionType,3:?UnionType,4:?FutureUnionType}> the result of Parameter::getCloneReplayState() for each parameter */
    public $parameter_states;

    /** @var bool whether ensureScopeInitialized() added the variable `$this` of a generic class to the scope */
    public $adds_this_variable;

    /**
     * @var ?UnionType the return type in which checkForTemplateTypes() found no template type,
     * if resolving `static` in it does not change it (otherwise null)
     */
    public $return_type;

    /**
     * @param array<string,UnionType> $phpdoc_parameter_type_map
     * @param list<array{0:UnionType,1:int,2:?UnionType,3:?UnionType,4:?FutureUnionType}> $parameter_states
     */
    public function __construct(
        array $phpdoc_parameter_type_map,
        ?int $last_mandatory_phpdoc_param_offset,
        array $parameter_states,
        bool $adds_this_variable,
        ?UnionType $return_type
    ) {
        $this->phpdoc_parameter_type_map = $phpdoc_parameter_type_map;
        $this->last_mandatory_phpdoc_param_offset = $last_mandatory_phpdoc_param_offset;
        $this->parameter_states = $parameter_states;
        $this->adds_this_variable = $adds_this_variable;
        $this->return_type = $return_type;
    }
}
