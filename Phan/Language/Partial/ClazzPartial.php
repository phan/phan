<?php
declare(strict_types=1);
namespace Phan\Language\Partial;

use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
use \Phan\Language\FileRef;

class ClazzPartial extends Partial {

    /**
     * @var FQSEN
     */
    private $parent_class_fqsen = null;

    /**
     * @var FQSEN[]
     */
    private $interface_fqsen_list = [];

    /**
     * @var FQSEN[]
     */
    private $trait_fqsen_list = [];

    /**
     * @var bool
     */
    private $is_parent_constructor_called = false;

    /**
     * @var int
     */
    private $flags;

    /**
     * @var Property[]
     */
    private $property_map = [];

    /**
     * @var Constant[]
     */
    private $constant_map = [];

    public function asClazz() {
        return new Clazz(
            Context::fromFileRef($this->file_ref),
            (string)$this->fqsen,
            $this->union_type,
            $this->flags
        );
    }

}
