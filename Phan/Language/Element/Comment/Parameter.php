<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Type;

class Parameter {

    /**
     * @var string
     * The name of the parameter
     */
    private $name = null;

    /**
     * @var Type
     * The type of the parameter
     */
    private $type = null;

    /**
     * @var string
     * An optional comment string associated with the parameter
     */
    private $comment = null;

    /**
     * @param string $name
     * The name of the parameter
     *
     * @param Type $type
     * The type of the parameter
     *
     * @param string $comment
     * An optional comment string associated with the parameter
     */
    public function __construct(
        string $name,
        Type $type,
        string $comment
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->comment = $comment;
    }

    /**
     * @return string
     * The name of the parameter
     */
    public function getName() : string {
        return $this->name;
    }

    /**
     * @return Type
     * The type of the parameter
     */
    public function getType() : Type {
        return $this->type;
    }

    /**
     * @return string
     * An optional comment string associated with the parameter
     */
    public function getComment() : string {
        return $this->comment;
    }

}
