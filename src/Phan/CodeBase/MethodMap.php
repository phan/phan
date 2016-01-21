<?php declare(strict_types=1);
namespace Phan\CodeBase;

use \Phan\Config;
use \Phan\Database;
use \Phan\Exception\NotFoundException;
use \Phan\Language\Element\Method;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\UnionType;
use \Phan\Map;
use \Phan\Model\Method as MethodModel;

trait MethodMap
{

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
     * @var Method[]
     * A map from Method name to all methods with that name.
     * This is useful for adding hail-mary references to
     * methods called on unknown types when doing dead
     * code detection
     */
    protected $method_name_map = [];

    /**
     * @return Method[][]
     * A map from FQSEN to name to method
     */
    public function getMethodMap() : array
    {
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
    public function setMethodMap(array $method_map)
    {
        $this->method_map = $method_map;
    }

    /**
     * @param FullyQualifiedMethodName|FullyQualifiedFunctionName $fqsen
     *
     * @return bool
     */
    public function hasMethod($fqsen) : bool
    {
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

        // For elements in the root namespace, check to see if
        // there's a static method signature for something that
        // hasn't been loaded into memory yet and create a
        // method out of it as its requested
        if ('\\' == $scope) {
            $function_signature_map =
                UnionType::internalFunctionSignatureMap();

            $fqsen = FullyQualifiedFunctionName::make(
                $scope,
                $name
            );

            if (!empty($function_signature_map[$name])) {
                $signature = $function_signature_map[$name];

                // Add each method returned for the signature
                foreach (Method::methodListFromSignature(
                    $this,
                    $fqsen,
                    $signature
                ) as $method) {
                    $this->addMethod($method);
                }

                return true;
            }
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
    public function getMethod($fqsen) : Method
    {
        if ($fqsen instanceof FullyQualifiedMethodName) {
            return $this->getMethodByMethodFQSEN($fqsen);
        } else {
            return $this->getMethodByFunctionFQSEN($fqsen);
        }
    }

    /**
     * @param string $name
     * The name of a method you'd like to get all instances of
     *
     * @return Method[]
     * All known methods with the given name
     */
    public function getMethodListByName(string $name) : array
    {

        // If we're doing dead code detection we'll have faster
        // access at the cost of being a bit more of a memory
        // hog.
        if (Config::get()->dead_code_detection) {
            return $this->method_name_map[strtolower($name)] ?? [];
        }

        $method_list = [];
        $name = strtolower($name);
        foreach ($this->getMethodMap() as $fqsen => $list) {
            foreach ($list as $method_name => $method) {
                if ($name === strtolower($method_name)) {
                    $method_list[] = $method;
                }
            }
        }
        return $method_list;
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
    public function addMethod(Method $method)
    {
        if ($method->getFQSEN() instanceof FullyQualifiedMethodName) {
            $this->addMethodWithMethodFQSEN(
                $method,
                $method->getFQSEN()
            );
        } else {
            assert(
                $method->getFQSEN() instanceof FullyQualifiedFunctionName,
                "Method given must have FQSEN of type FullyQualifiedMethodName"
            );
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

        // If we're doing dead code detection, map the name
        // directly to the method so we can quickly look up
        // all methods with that name to add a possible
        // reference
        if (Config::get()->dead_code_detection) {
            $this->method_name_map[strtolower($name)][] = $method;
        }

        // Associate the element with the file it was found in
        $this->getFileByPath($method->getContext()->getFile())
            ->addMethodFQSEN($method->getFQSEN());
    }

    /**
     * Write each object to the database
     *
     * @return null
     */
    protected function storeMethodMap()
    {
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
            MethodModel::delete(
                Database::get(),
                $scope . '|' .  $name
            );
        }

        unset($this->method_map[$scope][$name]);
    }
}
