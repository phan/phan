<?php

namespace NS906;

abstract class AbstractDto
{
    /**
     * @return static
     */
    public static function createFromArray(array $data)
    {
        $dtoClass = static::class;

        $dto = new $dtoClass();
        foreach ($data as $key => $value) {
            $dto->$key = $value;
        }

        return $dto;
    }
}

class Dto extends AbstractDto
{
    /** @var array */
    public $baz;

    /**
     * @return static
     */
    public static function createFromArray(array $data)
    {
        $dto = parent::createFromArray($data);
        $dto->baz = 123;  // should infer type is a subtype of Dto and warn about the incorrect access

        return $dto;  // should not warn
    }
}

var_dump(Dto::createFromArray(["bar" => 456]));
