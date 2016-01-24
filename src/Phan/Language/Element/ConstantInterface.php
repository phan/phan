<?php declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\FutureUnionType;

interface ConstantInterface {

    /**
     * @return void
     */
    public function setFutureUnionType(
        FutureUnionType $future_union_type
    );

}
