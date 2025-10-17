<?php

// Test multiline support for Phan-specific annotations (issue #4252)

// Test 1: Multiline @phan-param on method
class Test1 {
    /**
     * @phan-param ArrayIterator<
     *     string,
     *     array{
     *         id:int,
     *         name:string
     *     }
     * > $iterator
     */
    public function testMethod($iterator) {
        foreach ($iterator as $key => $value) {
            echo $value['id'];
            echo $value['name'];
            echo $value['missing']; // Should warn: PhanTypeInvalidDimOffset
        }
    }
}

// Test 2: Multiline @phan-return on method
class Test2 {
    /**
     * @phan-return array{
     *     success:true,
     *     data:array<string,mixed>
     * }|array{
     *     success:false,
     *     error:string
     * }
     */
    public function getResponse() {
        if (rand(0, 1)) {
            return ['success' => true, 'data' => []];
        }
        return ['success' => false, 'error' => 'Failed'];
    }
}

// Test 3: Multiline @phan-type alias on class
/**
 * @phan-type UserData = array{
 *     id:int,
 *     username:string,
 *     email:string,
 *     profile:array{
 *         age:int,
 *         location:string
 *     }
 * }
 */
class Test3 {
    /**
     * @phan-param UserData $user
     */
    public function processUser($user) {
        echo $user['id'];
        echo $user['profile']['age'];
        echo $user['invalid']; // Should warn: PhanTypeInvalidDimOffset
    }
}

// Test 4: Multiline @phan-property on class
/**
 * @phan-property array{
 *     settings:array<string,mixed>,
 *     metadata:array{
 *         created:int,
 *         updated:int
 *     }
 * } $config
 */
class Test4 {
    public function __get($name) {
        return [];
    }

    public function test() {
        echo $this->config['settings'];
        echo $this->config['metadata']['created'];
        echo $this->config['invalid']; // Should warn: PhanTypeInvalidDimOffset
    }
}

// Test 5: Multiline @phan-property-read on class
/**
 * @phan-property-read array{
 *     readonly_field:string,
 *     nested:array{
 *         value:int
 *     }
 * } $readonlyData
 */
class Test5 {
    public function __get($name) {
        return [];
    }

    public function test() {
        echo $this->readonlyData['readonly_field'];
        echo $this->readonlyData['nested']['value'];
    }
}

// Test 6: Multiline @phan-property-write on class
/**
 * @phan-property-write array{
 *     writable_field:string,
 *     data:array<string,int>
 * } $writableData
 */
class Test6 {
    public function __set($name, $value) {
    }

    public function test() {
        $this->writableData = ['writable_field' => 'test', 'data' => []];
    }
}

// Test 7: Multiline @phan-assert on method
class Test7 {
    /**
     * @phan-assert array{
     *     id:int,
     *     name:string
     * } $value
     */
    public function assertValidUser($value): void {
        if (!is_array($value) || !isset($value['id'], $value['name'])) {
            throw new \InvalidArgumentException('Invalid user');
        }
    }

    public function test($input) {
        $this->assertValidUser($input);
        echo $input['id'];
        echo $input['name'];
        echo $input['missing']; // Should warn: PhanTypeInvalidDimOffset
    }
}

// Test 8: Multiline @phan-real-return on method
class Test8 {
    /**
     * @phan-real-return array{
     *     status:int,
     *     headers:array<string,string>,
     *     body:string
     * }
     */
    public function getResponse() {
        return ['status' => 200, 'headers' => [], 'body' => ''];
    }
}

// Test 9: Multiline @phan-implements on class
/**
 * @phan-implements \ArrayAccess<
 *     string,
 *     array{
 *         value:mixed,
 *         meta:array<string,mixed>
 *     }
 * >
 */
class Test9 implements \ArrayAccess {
    public function offsetExists($offset): bool { return true; }
    public function offsetGet($offset): mixed { return []; }
    public function offsetSet($offset, $value): void {}
    public function offsetUnset($offset): void {}
}

// Test 10: Multiline @phan-extends on class
/**
 * @phan-extends \ArrayIterator<
 *     int,
 *     array{
 *         id:int,
 *         data:string
 *     }
 * >
 */
class Test10 extends \ArrayIterator {
}

// Test 11: Mixed single-line and multiline in same comment
class Test11 {
    /**
     * @phan-param array{id:int} $simple
     * @phan-param array{
     *     complex_id:int,
     *     complex_data:array<string,mixed>
     * } $complex
     * @phan-return array{result:bool}
     */
    public function test($simple, $complex) {
        echo $simple['id'];
        echo $complex['complex_id'];
        echo $complex['complex_data'];
        return ['result' => true];
    }
}

// Test 12: Multiline @phan-use (trait generics) on class
trait GenericTrait {
    /** @var mixed */
    private $data;
}

/**
 * @phan-use GenericTrait<
 *     array{
 *         id:int,
 *         value:string
 *     }
 * >
 */
class Test12 {
    use GenericTrait;
}

// Test 13: Multiline @phan-inherits on class
/**
 * @phan-inherits \ArrayIterator<
 *     int,
 *     array{
 *         key:string,
 *         value:int
 *     }
 * >
 */
class Test13 extends \ArrayIterator {
}

// Test 14: Multiline @phan-var on property
class Test14 {
    /**
     * @phan-var array{
     *     nested:array{
     *         deep:int
     *     }
     * }
     */
    public $data;

    public function test() {
        echo $this->data['nested']['deep'];
    }
}
