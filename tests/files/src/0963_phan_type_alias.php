<?php

/**
 * NOTE: Due to a limitation of the php-ast parser, 'phan-type' annotations must be on element doc comments such as classlikes, functions, etc.
 * @phan-type UserData = array{name:string, id:int}
 */
class UserModel {
    /**
     * @var UserData
     */
    private $data;
    /**
     * @param UserData $data
     */
    public function __construct(array $data) {
        $this->data = $data;
    }

    /** @return UserData */
    public function getData(): array {
        return $this->data;
    }
}

// should warn about 'id'
$value = new UserModel(['name' => 'test', 'id' => 'invalid']);
$data = $value->getData();

'@phan-debug-var $data';
