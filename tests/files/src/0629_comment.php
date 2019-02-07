<?php

interface AssetRepositoryInterface
{
    /**
     * Find all assets optionally matching criteria and return a paginate object.
     *
     * @param array<string,string>|null $orderBy Ordering where the results should be returned by.
     */
    public function paginateByFilterCommand(array $orderBy = null): stdClass;
}

class AssetRepository implements AssetRepositoryInterface
{
    /**
     * @inheritdoc
     */
    public function paginateByFilterCommand(array $orderBy = null): stdClass
    {
        // Expected: should infer ?array<string,string> instead of ?array in the error message emitted by the below line
        echo strlen($orderBy);  // src/standalone.php:20 PhanTypeMismatchArgumentInternal Argument 1 (string) is ?array but \strlen() takes string
        return new stdClass();
    }
}

class ParamBase1 {
    /**
     * @return array<int,stdClass>
     */
    public function acceptList(array $x) : array {
        var_export($x);
        return [new stdClass()];
    }
}

class ParamSubclass1 extends ParamBase1 {
    /**
     * Because this doesn't explicitly specify (at)return array,
     * this inherits the return type from the ancestor class
     * and warns about not returning `array<int,stdClass>`.
     *
     * @param stdClass[] $x
     */
    public function acceptList(array $x) : array {
        echo strlen($x);  // should infer stdClass[]
        return ['key' => 2];
    }
}

// should also warn
class ParamSubclass2 extends ParamBase1 {
    public function acceptList(array $x) : array {
        return [count($x)];
    }
}
