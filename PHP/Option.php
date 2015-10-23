<?php
declare(strict_types=1);
namespace php;

/**
 *
 */
abstract class Option {

    /**
     * @return bool
     */
    public function isEmpty() : bool;

    /**
     * @return bool
     */
    public function isDefined() : bool;

    /**
     * @return mixed
     */
    public function get();

    /**
     * @return mixed
     */
    public function getOrElse($else);
}
