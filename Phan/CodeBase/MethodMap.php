<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Database;
use \Phan\Exception\NotFoundException;
use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Model\Method as MethodModel;

trait MethodMap {

    /**
     * Implementing classes must support a mechanism for
     * getting a File by its path
     */
    abstract function getFileByPath(string $file_path) : File;

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
    public function hasMethod($fqsen) : bool {
        if ($fqsen instanceof FullyQualifiedMethodName) {
            return $this->hasMethodWithMethodFQSEN($fqsen);
        } else {
            return $this->hasMethodWithFunctionFQSEN($fqsen);
        }
    }

    /**
     * @param FullyQualifiedMethodName $fqsen
     *
     * @return bool
     */
    private function hasMethodWithMethodFQSEN(
        FullyQualifiedMethodName $fqsen
    ) : bool {
        return $this->hasMethodWithScopeAndName(
            (string)$fqsen->getFullyQualifiedClassName(),
            $fqsen->getNameWithAlternateId()
        );
    }

    /**
     * @param FullyQualifiedFunctionName $fqsen
     *
     * @return bool
     */
    private function hasMethodWithFunctionFQSEN(
        FullyQualifiedFunctionName $fqsen
    ) : bool {
        return $this->hasMethodWithScopeAndName(
            $fqsen->getNamespace(),
            $fqsen->getNameWithAlternateId()
        );
    }

    /**
     * @param string $scope
     * The scope of the method or function
     *
     * @param string $name
     * The name of the method (with an optional alternate id)
     *
     * @return bool
     */
    private function hasMethodWithScopeAndName(
        string $scope,
        string $name
    ) {
        if (!empty($this->method_map[$scope][$name])) {
            return true;
        }

        if (Database::isEnabled()) {
            // Otherwise, check the database
            try {
                MethodModel::read(Database::get(), $scope . '|' . $name);
                return true;
            } catch (NotFoundException $exception) {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * @param FullyQualifiedMethodName|FullyQualifiedFunctionName $fqsen
     *
     * @return Method
     * Get the method with the given FQSEN
     */
    public function getMethod($fqsen) : Method {
        if ($fqsen instanceof FullyQualifiedMethodName) {
            return $this->getMethodByMethodFQSEN($fqsen);
        } else {
            return $this->getMethodByFunctionFQSEN($fqsen);
        }
    }

    /**
     * @param FullyQualifiedMethodName $fqsen
     *
     * @return Method
     * Get the method with the given FQSEN
     */
    private function getMethodByMethodFQSEN(
        FullyQualifiedMethodName $fqsen
    ) : Method {
        return $this->getMethodByScopeAndName(
            (string)$fqsen->getFullyQualifiedClassName(),
            $fqsen->getNameWithAlternateId()
        );
    }

    /**
     * @param FullyQualifiedFunctionName $fqsen
     *
     * @return Method
     * Get the method with the given FQSEN
     */
    private function getMethodByFunctionFQSEN(
        FullyQualifiedFunctionName $fqsen
    ) : Method {
        return $this->getMethodByScopeAndName(
            $fqsen->getNamespace(),
            $fqsen->getNameWithAlternateId()
        );
    }

    /**
     * @param string $scope
     * The scope of the method or function
     *
     * @param string $name
     * The name of the method (with an optional alternate id)
     *
     * @return Method
     * Get the method with the given FQSEN
     *
     * @throws NotFoundException
     * An exception is thrown if the class cannot be
     * found
     */
    private function getMethodByScopeAndName(
        string $scope,
        string $name
    ) : Method {

        if (empty($this->method_map[$scope][$name])) {
            $this->method_map[$scope][$name] =
                MethodModel::read(Database::get(), $scope . '|' . $name)
                ->getMethod();
        }

        return $this->method_map[$scope][$name];
    }

    /**
     * @param Method $method
     * Any method
     *
     * @return null
     */
    public function addMethod(Method $method) {
        if ($method->getFQSEN() instanceof FullyQualifiedMethodName) {
            $this->addMethodWithMethodFQSEN(
                $method,
                $method->getFQSEN()
            );
        } else {
            assert($method->getFQSEN() instanceof FullyQualifiedFunctionName,
                "Method given must have FQSEN of type FullyQualifiedMethodName");
            $this->addMethodWithFunctionFQSEN(
                $method,
                $method->getFQSEN()
            );
        }
    }

    /**
     * @param Method $method
     * Any method
     *
     * @param FullyQualifiedMethodName $fqsen
     * The FQSEN for the method
     *
     * @return null
     */
    private function addMethodWithMethodFQSEN(
        Method $method,
        FullyQualifiedMethodName $fqsen
    ) {
        $this->addMethodInScope(
            $method,
            $fqsen->getFullyQualifiedClassName()
        );
    }

    /**
     * @param Method $method
     * Any method
     *
     * @param FullyQualifiedFunctionName $fqsen
     * The FQSEN for the method
     *
     * @return null
     */
    private function addMethodWithFunctionFQSEN(
        Method $method,
        FullyQualifiedFunctionName $fqsen
    ) {
        $this->addMethodWithScopeAndName(
            $method,
            $fqsen->getNamespace(),
            $fqsen->getNameWithAlternateId()
        );
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
        $this->addMethodWithScopeAndName(
            $method,
            (string)$fqsen,
            $method->getFQSEN()->getNameWithAlternateId()
        );
    }

    private function addMethodWithScopeAndName(
        Method $method,
        string $scope,
        string $name
    ) {
        $this->method_map[$scope][$name] = $method;

        // For elements that aren't internal PHP classes
        if (!$method->getContext()->isInternal()) {

            // Associate the element with the file it was found in
            $this->getFileByPath($method->getContext()->getFile())
                ->addMethodFQSEN($method->getFQSEN());
        }
    }

    /**
     * Write each object to the database
     *
     * @return null
     */
    protected function storeMethodMap() {
        if (!Database::isEnabled()) {
            return;
        }

        foreach ($this->method_map as $scope => $map) {
            foreach ($map as $name => $method) {
                if (!$method->getContext()->isInternal()) {
                    (new MethodModel($method, $scope, $name))->write(
                        Database::get()
                    );
                }
            }
        }
    }

    /**
     * @return null
     */
    protected function flushMethodWithScopeAndName(
        string $scope,
        string $name
    ) {
        if (Database::isEnabled()) {
            MethodModel::delete(Database::get(),
                $scope . '|' .  $name
            );
        }

        unset($this->method_map[$scope][$name]);
    }

}
