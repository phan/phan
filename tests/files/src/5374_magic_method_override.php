<?php

/*
 * @phan-file-suppress PhanParamSignatureMismatchInternal
 * @phan-file-suppress PhanClassContainsAbstractMethodInternal
 */

class BaseClass {}
class ChildClass extends BaseClass {}

abstract class ListBase implements Iterator {
    /**
     * @return BaseClass
     */
    #[\ReturnTypeWillChange]
    public function next() {
        return new ChildClass;
    }
}

/**
 * @method ChildClass next()
 */
class ListChild extends ListBase {
}
