<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;

trait MethodMap {

    /**
     * @var Method[][]
     * A map from FQSEN to name to a method
     */
    protected $method_map = [];

    /**
     * @return Method[][]
     * A map from FQSEN to name to method
     */
    public function getMethodMap() : array {
        return $this->method_map;
    }

    /**
     * @return Method[]
     * A map from name to method
     */
    public function getMethodMapForScope(
        FullyQualifiedClassName $fqsen
    ) {
        if (empty($this->method_map[(string)$fqsen])) {
            return [];
        }

        return $this->method_map[(string)$fqsen];
    }

    /**
     * @param Method[][] $method_map
     * A map from FQSEN to Method
     *
     * @return null
     */
    public function setMethodMap(array $method_map) {
        $this->method_map = $method_map;
    }

    /**
     * @param FullyQualifiedMethodName|FullyQualifiedFunctionName $fqsen
     *
     * @return bool
     */
    public function hasMethod(
        $fqsen
    ) : bool {
        if ($fqsen instanceof FullyQualifiedMethodName) {
            return !empty($this->method_map
                [(string)$fqsen->getFullyQualifiedClassName()]
                [$fqsen->getNameWithAlternateId()]
            );
        } else {
            assert($fqsen instanceof FullyQualifiedFunctionName,
                "Method given must have FQSEN of type FullyQualifiedMethodName");

            return !empty($this->method_map
                [$fqsen->getNamespace()]
                [$fqsen->getNameWithAlternateId()]);
        }
    }

    /**
     * @param FullyQualifiedMethodName|FullyQualifiedFunctionName $fqsen
     *
     * @return Method
     * Get the method with the given FQSEN
     */
    public function getMethod(
        $fqsen
    ) : Method {
        if ($fqsen instanceof FullyQualifiedMethodName) {
            return $this->method_map
                [(string)$fqsen->getFullyQualifiedClassName()]
                [$fqsen->getNameWithAlternateId()];
        } else {
            assert($fqsen instanceof FullyQualifiedFunctionName,
                "Method given must have FQSEN of type FullyQualifiedMethodName");

            return $this->method_map
                [$fqsen->getNamespace()]
                [$fqsen->getNameWithAlternateId()];
        }
    }

    /**
     * @param Method $method
     * Any method
     *
     * @return null
     */
    public function addMethod(Method $method) {
        if ($method->getFQSEN() instanceof FullyQualifiedMethodName) {
            $this->addMethodInScope(
                $method,
                $method->getFQSEN()->getFullyQualifiedClassName()
            );
        } else {
            assert($method->getFQSEN() instanceof FullyQualifiedFunctionName,
                "Method given must have FQSEN of type FullyQualifiedMethodName");

            $this->method_map
                [$method->getFQSEN()->getNamespace()]
                [$method->getFQSEN()->getNameWithAlternateId()]
                = $method;
        }
    }

    /**
     * @param Method $method
     * Any method
     *
     * @param FQSEN $fqsen
     * The FQSEN to index the method by
     *
     * @return null
     */
    public function addMethodInScope(
        Method $method,
        FullyQualifiedClassName $fqsen
    ) {
        $name = $method->getFQSEN()->getNameWithAlternateId();
        $this->method_map[(string)$fqsen][$name] = $method;
    }

}
