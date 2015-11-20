<?php
declare(strict_types=1);
namespace Phan\Language\Partial;

use \Phan\Language\FQSEN;
use \Phan\Language\UnionType;
use \Phan\Language\FileRef;

class Partial {

    /**
     * @var FQSEN
     */
    protected $fqsen;

    /**
     * @var UnionType
     */
    protected $union_type;

    /**
     * @var FileRef;
     */
    protected $file_ref;

    /**
     * @param FQSEN $fqsen
     * @param UnionType $union_type
     * @param FileRef $file_ref
     * @param int $flags
     */
    public function __construct(
        FQSEN $fqsen,
        UnionType $union_type,
        FileRef $file_ref,
        int $flags
    ) {
        $this->fqsen = $fqsen;
        $this->union_type = $union_type;
        $this->file_ref = $file_ref;
        $this->flags = $flags;
    }
}
