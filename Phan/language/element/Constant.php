<?php
declare(strict_types=1);
namespace phan\element;

/**
 *
 */
class ConstantElement extends TypedStructuralElement {

    /**
     * @param string $file
     * The path to the file in which the class is defined
     *
     * @param string $namespace,
     * The namespace of the class
     *
     * @param int $line_number_start,
     * The starting line number of the class within the $file
     *
     * @param int $line_number_end,
     * The ending line number of the class within the $file
     *
     * @param string $comment,
     * Any comment block associated with the class
     */
    public function __construct(
        string $file,
        string $namespace,
        int $line_number_start,
        int $line_number_end,
        string $comment,
        bool $is_conditional,
        int $flags,
        string $name,
        string $type
    ) {
        parent::__construct(
            $file,
            $namespace,
            $line_number_start,
            $line_number_end,
            $comment,
            $is_conditional,
            $flags,
            $name,
            $type
        );
    }
}
