<?php
declare(strict_types=1);
namespace Phan\Language\Partial;

use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
use \Phan\Language\FileRef;

class PropertyPartial {

    /**
     * @var string
     */
    private $name;

    /**
     * @param FQSEN $fqsen
     * @param UnionType $union_type
     * @param FileRef $file_ref
     */
    public function __construct(
        FQSEN $class_fqsen,
        string $name,
        UnionType $union_type,
        FileRef $file_ref,
        int $flags
    ) {
        parent::__construct(
            $class_fqsen,
            $union_type,
            $file_ref,
            $flags
        );
        $this->name = $name;
    }

}
