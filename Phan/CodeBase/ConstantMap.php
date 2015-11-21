<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Language\Element\Constant;
use \Phan\Language\FQSEN;

/**
 * Information pertaining to PHP code files that we've read
 */
trait ConstantMap {

    /**
     * @var Constant[][]
     * A map from FQSEN to name to a constant
     */
    protected $constant_map = [];

    /**
     * @return Constant[][]
     * A map from FQSEN to constant
     */
    public function getConstantMap() : array {
        return $this->constant_map;
    }

    /**
     * @return Constant[]
     * A map from name to constant
     */
    public function getConstantMapForScope(FQSEN $fqsen) {
        if (empty($this->constant_map[(string)$fqsen])) {
            return [];
        }

        return $this->constant_map[(string)$fqsen];
    }

    /**
     * @param Constant[][] $constant_map
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
    public function hasConstant(FQSEN $fqsen, string $name) : bool {
        return !empty($this->constant_map[(string)$fqsen][$name]);
    }

    /**
     * @return Constant
     * Get the constant with the given FQSEN
     */
    public function getConstant(FQSEN $fqsen, string $name) : Constant {
        return $this->constant_map[(string)$fqsen][$name];
    }

    /**
     * @param Constant $constant
     * Any global or class-scoped constant
     *
     * @return null
     */
    public function addConstant(Constant $constant) {
        $this->addConstantWithNameInScope(
            $constant,
            $constant->getName(),
            $constant->getFQSEN()
        );
    }

    /**
     * @param Constant $constant
     * Any constant
     *
     * @param FQSEN $fqsen
     * The FQSEN to index the constant by
     *
     * @return null
     */
    public function addConstantWithNameInScope(
        Constant $constant,
        string $name,
        FQSEN $fqsen
    ) {
        $this->constant_map[(string)$fqsen][$name] = $constant;
    }

}
