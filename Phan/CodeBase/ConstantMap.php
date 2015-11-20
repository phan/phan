<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Language\Element\Constant;
use \Phan\Language\FQSEN;

/**
 * Information pertaining to PHP code files that we've read
 */
trait ConstantMap {

    /**
     * @var Constant[]
     * A map from FQSEN to a constant
     */
    protected $constant_map = [];

    /**
     * @return Constant[]
     * A map from FQSEN to constant
     */
    public function getConstantMap() : array {
        return $this->constant_map;
    }

    /**
     * @param Constant[] $constant_map
     * A map from FQSEN to Constant
     *
     * @return null
     */
    public function setConstantMap(array $constant_map) {
        $this->constant_map = $constant_map;
    }

    /**
     * @return bool
     */
    public function hasConstantWithFQSEN(FQSEN $fqsen) : bool {
        return !empty($this->constant_map[(string)$fqsen]);
    }

    /**
     * @return Constant
     * Get the constant with the given FQSEN
     */
    public function getConstantByFQSEN(FQSEN $fqsen) : Constant {
        return $this->constant_map[(string)$fqsen];
    }

    /**
     * @param Constant $constant
     * Any global or class-scoped constant
     *
     * @return null
     */
    public function addConstant(Constant $constant) {
        $this->constant_map[(string)$constant->getFQSEN()] = $constant;
    }

}
