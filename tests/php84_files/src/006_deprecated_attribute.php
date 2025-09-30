<?php

/**
 * @phan-file-suppress PhanEmptyFunction, PhanUnusedGlobalFunctionParameter, PhanPluginUseReturnValueNoopVoid, PhanEmptyPublicMethod
 */

// Test 1: Deprecated function with no parameters
#[Deprecated]
function oldFunction(): void {}

// Test 2: Deprecated function with message
#[Deprecated("Use newFunction() instead")]
function oldFunctionWithMessage(): void {}

// Test 3: Deprecated function with message and version
#[Deprecated("Use newFunction() instead", "2.0.0")]
function oldFunctionWithVersion(): void {}

// Test 4: Deprecated method
class MyClass {
    #[Deprecated]
    public function oldMethod(): void {}

    #[Deprecated("Use newMethod() instead")]
    public function oldMethodWithMessage(): void {}

    public function newMethod(): void {}
}

// Test 5: Deprecated class constant
class Constants {
    #[Deprecated]
    public const OLD_CONSTANT = 'old';

    #[Deprecated("Use NEW_CONSTANT instead")]
    public const OLD_CONSTANT_WITH_MESSAGE = 'old';

    public const NEW_CONSTANT = 'new';
}

// Usage tests - these should emit deprecation warnings
oldFunction(); // Should warn: PhanDeprecatedFunction
oldFunctionWithMessage(); // Should warn: PhanDeprecatedFunction
oldFunctionWithVersion(); // Should warn: PhanDeprecatedFunction

$obj = new MyClass();
$obj->oldMethod(); // Should warn: PhanDeprecatedFunction
$obj->oldMethodWithMessage(); // Should warn: PhanDeprecatedFunction

echo Constants::OLD_CONSTANT; // Should warn: PhanDeprecatedClassConstant
echo Constants::OLD_CONSTANT_WITH_MESSAGE; // Should warn: PhanDeprecatedClassConstant

// Non-deprecated usage - should not warn
$obj->newMethod();
echo Constants::NEW_CONSTANT;
