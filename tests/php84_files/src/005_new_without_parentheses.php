<?php

/**
 * @phan-file-suppress PhanUnreferencedPublicMethod, PhanReadOnlyPublicProperty, PhanPluginUnknownArrayMethodReturnType, PhanEmptyFunction, PhanPluginUseReturnValueNoopVoid, PhanUnusedGlobalFunctionParameter
 */

class Request {
    private string $method = 'GET';
    private string $uri = '/';

    public function withMethod(string $method): self {
        $this->method = $method;
        return $this;
    }

    public function withUri(string $uri): self {
        $this->uri = $uri;
        return $this;
    }

    public function getMethod(): string {
        return $this->method;
    }

    public function getUri(): string {
        return $this->uri;
    }
}

class Builder {
    private array $data = [];

    public function set(string $key, mixed $value): self {
        $this->data[$key] = $value;
        return $this;
    }

    public function build(): array {
        return $this->data;
    }
}

// Test 1: Method chaining without parentheses
$request = new Request()->withMethod('POST')->withUri('/api/users');
echo $request->getMethod(); // Should infer as 'string'

// Test 2: Property access without parentheses
class PropertyTest {
    public string $value = 'test';
}
$prop = new PropertyTest()->value;
echo strlen($prop); // Should know $prop is string

// Test 3: Array access without parentheses
class ArrayTest implements ArrayAccess {
    private array $data = ['key' => 'value'];

    public function offsetExists($offset): bool {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset): void {
        unset($this->data[$offset]);
    }
}
$value = new ArrayTest()['key'];

// Test 4: Static method/constant access
class StaticTest {
    public const CONSTANT = 42;

    public static function staticMethod(): string {
        return 'static';
    }
}
$const = new StaticTest()::CONSTANT;
$static = new StaticTest()::staticMethod();

// Test 5: Invocation
class Invokable {
    public function __invoke(string $arg): int {
        return strlen($arg);
    }
}
$result = new Invokable()('test'); // Should infer as int

// Test 6: Complex chaining
$data = new Builder()
    ->set('name', 'John')
    ->set('age', 30)
    ->build();

// Test 7: Type inference should work correctly
function expectString(string $s): void {}
expectString(new Request()->withMethod('GET')->getMethod()); // Should not error

// Test 8: Error case - wrong type
function expectInt(int $i): void {}
expectInt(new Request()->getMethod()); // Should error - string passed to int parameter
