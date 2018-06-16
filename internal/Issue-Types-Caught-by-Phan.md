<!-- This is mirrored at https://github.com/phan/phan/wiki/Issue-Types-Caught-by-Phan -->
<!-- The copy distributed with Phan is in the internal folder because it may be removed or moved elsewhere -->

See [\Phan\Issue](https://github.com/phan/phan/blob/master/src/Phan/Issue.php) for the most up to date list of error types that are emitted. Below is a listing of all issue types as of [47e98af](https://github.com/phan/phan/tree/47e98af627276a90c377fd349c69f6cd3063efda/). The test case [0101_one_of_each.php](https://github.com/phan/phan/blob/master/tests/files/src/0101_one_of_each.php) was originally intended to cover all examples in this document.

A concise summary of issue categories found by Phan can be seen in [Phan's README](https://github.com/phan/phan#features).

Please add example code, fix outdated info and add any remedies to the issues below.

In addition to the below issue types, there are [additional issue types that can be detected by Phan's plugins](https://github.com/phan/phan/tree/master/.phan/plugins#plugins).

[![Gitter](https://badges.gitter.im/phan/phan.svg)](https://gitter.im/phan/phan?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

# AccessError

This category of issue is emitted when you're trying to access things that you can't access.
Note: in this document, "`@internal`" refers to user-defined elements with `/** @internal */` in their PHPDoc,
while "internal" refers to classes, functions, methods, etc.  that are built into PHP and PHP modules (e.g. `is_string`, `stdClass`, etc)

## PhanAccessClassConstantPrivate

This issue comes up when there is an attempt to access a private class constant outside of the scope in which it's defined.

```
Cannot access private class constant {CONST} defined at {FILE}:{LINE}
```

## PhanAccessClassConstantProtected

This issue comes up when there is an attempt to access a protected class constant outside of the scope in which it's defined.

```
Cannot access protected class constant {CONST} defined at {FILE}:{LINE}
```


## PhanAccessExtendsFinalClass

This issue comes up when there is an attempt to extend a user-defined final class.

```
Attempting to extend from final class {CLASS} defined at {FILE}:{LINE}
```

This will be emitted for the following code.

```php
final class FinalClass
class A extends FinalClass {}
```

## PhanAccessExtendsFinalClassInternal

This issue comes up when there is an attempt to extend an internal final class.

```
Attempting to extend from final internal class {CLASS}
```

This will be emitted for the following code.

```php
class A extends Closure {}
```

## PhanAccessMethodPrivate

This issue comes up when there is an attempt to invoke a private method outside of the scope in which it's defined.

```
Cannot access private method {METHOD} defined at {FILE}:{LINE}
```

## PhanAccessMethodPrivateWithCallMagicMethod

This issue comes up when there is an attempt to invoke a private method outside of the scope in which it's defined, but the attempt would end up calling `__call` or `__callStatic` instead.

```
Cannot access private method {METHOD} defined at {FILE}:{LINE} (if this call should be handled by __call, consider adding a @method tag to the class)
```

## PhanAccessMethodProtected

This issue comes up when there is an attempt to invoke a protected method outside of the scope in which it's defined or an implementing child class.

```
Cannot access protected method {METHOD} defined at {FILE}:{LINE}
```

## PhanAccessMethodProtectedWithCallMagicMethod

This issue comes up when there is an attempt to invoke a protected method outside of the scope in which it's defined or an implementing child class, but the attempt would end up calling `__call` or `__callStatic` instead.

```
Cannot access protected method {METHOD} defined at {FILE}:{LINE} (if this call should be handled by __call, consider adding a @method tag to the class)
```

## PhanAccessNonStaticToStatic

This issue is emitted when a class redeclares an inherited instance method as a static method.

```
Cannot make non static method {METHOD}() static
```

## PhanAccessNonStaticToStaticProperty

```
Cannot make non static property {PROPERTY} into the static property {PROPERTY}
```

## PhanAccessOverridesFinalMethod

This issue is emitted when a class attempts to override an inherited final method.

```
Declaration of method {METHOD} overrides final method {METHOD} defined in {FILE}:{LINE}
```

## PhanAccessOverridesFinalMethodInternal

This issue is emitted when a class attempts to override an inherited final method of an internal class.

```
Declaration of method {METHOD} overrides final internal method {METHOD}
```

## PhanAccessOverridesFinalMethodPHPDoc

This issue is emitted when a class declares a PHPDoc `@method` tag, despite having already inherited a final method from a base class.

```
Declaration of phpdoc method {METHOD} is an unnecessary override of final method {METHOD} defined in {FILE}:{LINE}
```

## PhanAccessOwnConstructor

```
Accessing own constructor directly via {CLASS}::__construct
```

## PhanAccessPropertyNonStaticAsStatic

This issue comes up when there is an attempt to access a non-static(instance) property as if it were a static property.

```
Accessing non static property {PROPERTY} as static
```

This will be emitted for the following code.

```php
class A { public $prop = 'value'; }
$x = A::$prop;
```

## PhanAccessPropertyPrivate

This issue comes up when there is an attempt to access a private property outside of the scope in which it's defined.

```
Cannot access private property {PROPERTY}
```

This will be emitted for the following code.

```php
class C1 { private static $p = 42; }
print C1::$p;
```

## PhanAccessPropertyProtected

This issue comes up when there is an attempt to access a protected property outside of the scope in which it's defined or an implementing child class.

```
Cannot access protected property {PROPERTY}
```

This will be emitted for the following code.

```php
class C1 { protected static $p = 42; }
print C1::$p;
```

## PhanAccessPropertyStaticAsNonStatic

This issue comes up when there is an attempt to access a static property as if it were a non static(instance) property.

```
Accessing static property {PROPERTY} as non static
```

This will be emitted for the following code.

```php
class A { public static $prop = 'value'; }
$x = (new A())->prop;
```

## PhanAccessSignatureMismatch

```
Access level to {METHOD} must be compatible with {METHOD} defined in {FILE}:{LINE}
```


## PhanAccessSignatureMismatchInternal

```
Access level to {METHOD} must be compatible with internal {METHOD}
```

## PhanAccessStaticToNonStatic

This issue is emitted when a class redeclares an inherited static method as an instance method.

```
Cannot make static method {METHOD}() non static
```

## PhanAccessStaticToNonStaticProperty

```
Cannot make static property {PROPERTY} into the non static property {PROPERTY}
```

## PhanAccessWrongInheritanceCategory

```
Attempting to inherit {CLASSLIKE} defined at {FILE}:{LINE} as if it were a {CLASSLIKE}
```

## PhanAccessWrongInheritanceCategoryInternal

```
Attempting to inherit internal {CLASSLIKE} as if it were a {CLASSLIKE}
```

## PhanConstantAccessSignatureMismatch

```
Access level to {CONST} must be compatible with {CONST} defined in {FILE}:{LINE}
```

## PhanConstantAccessSignatureMismatchInternal

```
Access level to {CONST} must be compatible with internal {CONST}
```

## PhanPropertyAccessSignatureMismatch

```
Access level to {PROPERTY} must be compatible with {PROPERTY} defined in {FILE}:{LINE}
```

## PhanPropertyAccessSignatureMismatchInternal

```
Access level to {PROPERTY} must be compatible with internal {PROPERTY}
```

# Analysis

This category will be emitted when Phan doesn't know how to analyze something.

Please do file an issue or otherwise get in touch if you get one of these (or an uncaught exception, or anything else thats shitty).

[![Gitter](https://badges.gitter.im/phan/phan.svg)](https://gitter.im/phan/phan?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)


## PhanUnanalyzable

This issue will be emitted when we hit a structure that Phan doesn't know how to parse. More commonly this will be expressed by Phan having an uncaught exception or behaving poorly.

```
Expression is unanalyzable or feature is unimplemented. Please create an issue at https://github.com/phan/phan/issues/new.
```
## PhanUnanalyzableInheritance

```
Unable to determine the method(s) which {METHOD} overrides, but Phan inferred that it did override something earlier. Please create an issue at https://github.com/phan/phan/issues/new with a test case.
```

# CompatError

This category of issue is emitted when there are compatibility issues. They will be thrown if there is an expression that may be treated differently in PHP7 than it was in previous major versions of the PHP runtime. Take a look at the [PHP7 Migration Manual](http://php.net/manual/en/migration70.incompatible.php) to understand changes in behavior.

## PhanCompatibleExpressionPHP7

This issue will be thrown if there is an expression that may be treated differently in PHP7 than it was in previous major versions of the PHP runtime. Take a look at the [PHP7 Migration Manual](http://php.net/manual/en/migration70.incompatible.php) to understand changes in behavior.

The config `Config::get()->backward_compatibility_checks` must be enabled for this to run such as by passing the command line argument `--backward-compatibility-checks` or by defining it in a `.phan/config.php` file such as [Phan's own config](https://github.com/phan/phan/blob/master/.phan/config.php).

```
{CLASS} expression may not be PHP 7 compatible
```

## PhanCompatibleIterableTypePHP70

```
Return type '{TYPE}' means a Traversable/array value starting in PHP 7.1. In PHP 7.0, iterable refers to a class/interface with the name 'iterable'
```

## PhanCompatibleKeyedArrayAssignPHP70

```
Using array keys in an array destructuring assignment is not compatible with PHP 7.0
```

## PhanCompatibleMultiExceptionCatchPHP70

```
Catching multiple exceptions is not supported before PHP 7.1
```

## PhanCompatibleNegativeStringOffset

```
Using negative string offsets is not supported before PHP 7.1 (emits an 'Uninitialized string offset' notice)
```

## PhanCompatibleNullableTypePHP70

```
Nullable type '{TYPE}' is not compatible with PHP 7.0
```

## PhanCompatibleNullableTypePHP71

```
Type '{TYPE}' refers to any object starting in PHP 7.2. In PHP 7.1 and earlier, it refers to a class/interface with the name 'object'
```

## PhanCompatiblePHP7

This issue will be thrown if there is an expression that may be treated differently in PHP7 than it was in previous major versions of the PHP runtime. Take a look at the [PHP7 Migration Manual](http://php.net/manual/en/migration70.incompatible.php) to understand changes in behavior.

The config `Config::get()->backward_compatibility_checks` must be enabled for this to run such as by passing the command line argument `--backward-compatibility-checks` or by defining it in a `.phan/config.php` file such as [Phan's own config](https://github.com/phan/phan/blob/master/.phan/config.php).

```
Expression may not be PHP 7 compatible
```

This will be emitted for the following code.

```php
$c->$m[0]();
```

## PhanCompatibleShortArrayAssignPHP70

```
Square bracket syntax for an array destructuring assignment is not compatible with PHP 7.0
```

## PhanCompatibleUseIterablePHP71

```
Using '{TYPE}' as iterable will be a syntax error in PHP 7.2 (iterable becomes a native type with subtypes Array and Iterator).
```

## PhanCompatibleUseObjectPHP71

```
Using '{TYPE}' as object will be a syntax error in PHP 7.2 (object becomes a native type that accepts any class instance).
```

## PhanCompatibleUseVoidPHP70

```
Using '{TYPE}' as void will be a syntax error in PHP 7.1 (void becomes the absense of a return type).
```

## PhanCompatibleVoidTypePHP70

```
Return type '{TYPE}' means the absense of a return value starting in PHP 7.1. In PHP 7.0, void refers to a class/interface with the name 'void'
```

# Context

This category of issue are for when you're doing stuff out of the context in which you're allowed to do it like referencing `self` or `parent` when not in a class, interface or trait.

## PhanContextNotObject

This issue comes up when you attempt to use things like `$this` that only exist when you're inside of a class, trait or interface, but are not.

```
Cannot access {CLASS} when not in object context
```

This will be emitted for the following code.

```php
new parent;
```

## PhanContextNotObjectInCallable

```
Cannot access {CLASS} when not in object context, but code is using callable {METHOD}
```

# DeprecatedError

This category of issue comes up when you're accessing deprecated elements (as marked by the `@deprecated` comment).

**Note!** Only classes, traits, interfaces, methods, functions, properties, and traits may be marked as deprecated. You can't deprecate a variable or any other expression.

## PhanDeprecatedClass

```
Call to deprecated class {CLASS} defined at {FILE}:{LINE}
```

## PhanDeprecatedFunction

If a class, method, function, property or constant is marked in its comment as `@deprecated`, any references to them will emit a deprecated error.

```
Call to deprecated function {FUNCTIONLIKE}() defined at {FILE}:{LINE}
```

This will be emitted for the following code.

```php
/** @deprecated  */
function f1() {}
f1();
```

## PhanDeprecatedFunctionInternal

```
Call to deprecated function {FUNCTIONLIKE}()
```

## PhanDeprecatedInterface

```
Using a deprecated interface {INTERFACE} defined at {FILE}:{LINE}
```

## PhanDeprecatedProperty

```
Reference to deprecated property {PROPERTY} defined at {FILE}:{LINE}
```

## PhanDeprecatedTrait

```
Using a deprecated trait {TRAIT} defined at {FILE}:{LINE}
```

# NOOPError

This category of issues are emitted when you have reasonable code but it isn't doing anything. They're all low severity.

## PhanNoopArray

Emitted when you have an array that is not used in any way.

```
Unused array
```

This will be emitted for the following code.

```php
[1,2,3];
```

## PhanNoopBinaryOperator

```
Unused result of a binary '{OPERATOR}' operator
```

## PhanNoopClosure

Emitted when you have a closure that is unused.

```
Unused closure
```

This will be emitted for the following code.

```php
function () {};
```

## PhanNoopConstant

Emitted when you have a reference to a constant that is unused.

```
Unused constant
```

This will be emitted for the following code.

```php
const C = 42;
C;
```

## PhanNoopEncapsulatedStringLiteral

```
Unused result of an encapsulated string literal
```

## PhanNoopNumericLiteral

```
Unused result of a numeric literal {STRING_LITERAL} near this line
```

## PhanNoopProperty

Emitted when you have a refence to a property that is unused.

```
Unused property
```

This will be emitted for the following code.

```php
class C {
    public $p;
    function f() {
        $this->p;
    }
}
```

## PhanNoopStringLiteral

```
Unused result of a string literal {STRING_LITERAL} near this line
```

## PhanNoopUnaryOperator

```
Unused result of a unary '{OPERATOR}' operator
```

## PhanNoopVariable

Emitted when you have a reference to a variable that is unused.

```
Unused variable
```

This will be emitted for the following code.

```php
$a = 42;
$a;
```

## PhanReadOnlyPrivateProperty

These issues are emitted when the analyzed file list contains at least one read operation
for a given declared property, but no write operations on that property.

There may be false positives if dynamic property accesses are performed, or if the code is a library that is used elsewhere.

```
Possibly zero write references to private property {PROPERTY}
```

## PhanReadOnlyProtectedProperty

```
Possibly zero write references to protected property {PROPERTY}
```

## PhanReadOnlyPublicProperty

```
Possibly zero write references to public property {PROPERTY}
```

## PhanUnreachableCatch

```
Catch statement for {CLASSLIKE} is unreachable. An earlier catch statement at line {LINE} caught the ancestor class/interface {CLASSLIKE}
```

## PhanUnreferencedClass

Similar issues exist for PhanUnreferencedProperty, PhanUnreferencedConstant, PhanUnreferencedMethod, and PhanUnreferencedFunction

This issue is disabled by default, but can be enabled by setting `Config::get()->dead_code_detection` to enabled. It indicates that the given element is (possibly) unused.

```
Possibly zero references to class {CLASS}
```

This will be emitted for the following code so long as `Config::get()->dead_code_detection` is enabled.

```php
class C {}
```

Keep in mind that for the following code we'd still emit the issue.

```php
class C2 {}
$v = 'C2';
new $v;

$v2 = 'C' . (1 + 1);
new $v2;
```

YMMV.

## PhanUnreferencedClosure

```
Possibly zero references to closure {FUNCTION}
```

## PhanUnreferencedConstant

```
Possibly zero references to global constant {CONST}
```

## PhanUnreferencedFunction

```
Possibly zero references to function {FUNCTION}
```

## PhanUnreferencedPrivateClassConstant

```
Possibly zero references to public class constant {CONST}
```

## PhanUnreferencedPrivateMethod

```
Possibly zero references to private method {METHOD}
```

## PhanUnreferencedPrivateProperty

```
Possibly zero references to private property {PROPERTY}
```

## PhanUnreferencedProtectedClassConstant

```
Possibly zero references to protected class constant {CONST}
```

## PhanUnreferencedProtectedMethod

```
Possibly zero references to protected method {METHOD}
```

## PhanUnreferencedProtectedProperty

```
Possibly zero references to protected property {PROPERTY}
```

## PhanUnreferencedPublicClassConstant

```
Possibly zero references to public class constant {CONST}
```

## PhanUnreferencedPublicMethod

```
Possibly zero references to public method {METHOD}
```

## PhanUnreferencedPublicProperty

```
Possibly zero references to public property {PROPERTY}
```

## PhanUnreferencedUseConstant

```
Possibly zero references to use statement for constant {CONST} ({CONST})
```

## PhanUnreferencedUseFunction

```
Possibly zero references to use statement for function {FUNCTION} ({FUNCTION})
```

## PhanUnreferencedUseNormal

```
Possibly zero references to use statement for classlike/namespace {CLASSLIKE} ({CLASSLIKE})
```

## PhanUnusedClosureParameter

Phan has various checks (See the `unused_variable_detection` config)
to detect if a variable or parameter is unused.

```
Parameter ${PARAMETER} is never used
```

## PhanUnusedClosureUseVariable

```
Closure use variable ${VARIABLE} is never used
```

## PhanUnusedGlobalFunctionParameter

```
Parameter ${PARAMETER} is never used
```

## PhanUnusedPrivateFinalMethodParameter

```
Parameter ${PARAMETER} is never used
```

## PhanUnusedPrivateMethodParameter

```
Parameter ${PARAMETER} is never used
```

## PhanUnusedProtectedFinalMethodParameter

```
Parameter ${PARAMETER} is never used
```

## PhanUnusedProtectedMethodParameter

```
Parameter ${PARAMETER} is never used
```

## PhanUnusedPublicFinalMethodParameter

```
Parameter ${PARAMETER} is never used
```

## PhanUnusedPublicMethodParameter

```
Parameter ${PARAMETER} is never used
```

## PhanUnusedVariable

Phan has various checks (See the `unused_variable_detection` config)
to detect if a variable or parameter is unused.

```
Unused definition of variable ${VARIABLE}
```

## PhanUnusedVariableValueOfForeachWithKey

```
Unused definition of variable ${VARIABLE} as the value of a foreach loop that included keys
```

## PhanWriteOnlyPrivateProperty

```
Possibly zero read references to private property {PROPERTY}
```

## PhanWriteOnlyProtectedProperty

```
Possibly zero read references to protected property {PROPERTY}
```

## PhanWriteOnlyPublicProperty

```
Possibly zero read references to public property {PROPERTY}
```

# ParamError

This category of error comes up when you're messing up your method or function parameters in some way.

## PhanParamRedefined

```
Redefinition of parameter {PARAMETER}
```

## PhanParamReqAfterOpt

If you declare a function with required parameters after optional parameters, you'll see this issue.

```
Required argument follows optional
```

This will be emitted for the following code

```php
function f2($p1 = null, $p2) {}
```

## PhanParamSignatureMismatch

This compares the param and return types inferred from phpdoc and real types,
and warns if an overriding method's signature is incompatible with the overridden method.
**For a check with much lower false positives and clearer issue messages, use the `PhanParamSignatureRealMismatch...` issue types instead.**

```
Declaration of {METHOD} should be compatible with {METHOD} defined in {FILE}:{LINE}
```

## PhanParamSignatureMismatchInternal

This compares the param and return types inferred from phpdoc and real types (as well as documentation of internal methods),
and warns if an overriding method's signature is incompatible with the overridden internal method.
For a check with much lower false positives and clearer issue messages, use the `PhanParamSignatureRealMismatchInternal...` issue types.

```
Declaration of {METHOD} should be compatible with internal {METHOD}
```

## PhanParamSignaturePHPDocMismatchHasNoParamType

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (parameter #{INDEX} with no type cannot replace original parameter with type '{TYPE}') defined in {FILE}:{LINE}
```

## PhanParamSignaturePHPDocMismatchHasParamType

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (parameter #{INDEX} has type '{TYPE}' cannot replace original parameter with no type) defined in {FILE}:{LINE}
```

## PhanParamSignaturePHPDocMismatchParamIsNotReference

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (parameter #{INDEX} is a non-reference parameter overriding a reference parameter) defined in {FILE}:{LINE}
```

## PhanParamSignaturePHPDocMismatchParamIsReference

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (parameter #{INDEX} is a reference parameter overriding a non-reference parameter) defined in {FILE}:{LINE}
```

## PhanParamSignaturePHPDocMismatchParamNotVariadic

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (parameter #{INDEX} is a non-variadic parameter replacing a variadic parameter) defined in {FILE}:{LINE}
```

## PhanParamSignaturePHPDocMismatchParamType

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (parameter #{INDEX} of type '{TYPE}' cannot replace original parameter of type '{TYPE}') defined in {FILE}:{LINE}
```

## PhanParamSignaturePHPDocMismatchParamVariadic

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (parameter #{INDEX} is a variadic parameter replacing a non-variadic parameter) defined in {FILE}:{LINE}
```

## PhanParamSignaturePHPDocMismatchReturnType

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (method returning '{TYPE}' cannot override method returning '{TYPE}') defined in {FILE}:{LINE}
```

## PhanParamSignaturePHPDocMismatchTooFewParameters

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (the method override accepts {COUNT} parameter(s), but the overridden method can accept {COUNT}) defined in {FILE}:{LINE}
```

## PhanParamSignaturePHPDocMismatchTooManyRequiredParameters

```
Declaration of real/@method {METHOD} should be compatible with real/@method {METHOD} (the method override requires {COUNT} parameter(s), but the overridden method requires only {COUNT}) defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchHasNoParamType

```
Declaration of {METHOD} should be compatible with {METHOD} (parameter #{INDEX} with no type cannot replace original parameter with type '{TYPE}') defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchHasNoParamTypeInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (parameter #{INDEX} with no type cannot replace original parameter with type '{TYPE}')
```

## PhanParamSignatureRealMismatchHasParamType

```
Declaration of {METHOD} should be compatible with {METHOD} (parameter #{INDEX} has type '{TYPE}' cannot replace original parameter with no type) defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchHasParamTypeInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (parameter #{INDEX} has type '{TYPE}' cannot replace original parameter with no type)
```

## PhanParamSignatureRealMismatchParamIsNotReference

```
Declaration of {METHOD} should be compatible with {METHOD} (parameter #{INDEX} is a non-reference parameter overriding a reference parameter) defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchParamIsNotReferenceInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (parameter #{INDEX} is a non-reference parameter overriding a reference parameter)
```

## PhanParamSignatureRealMismatchParamIsReference

```
Declaration of {METHOD} should be compatible with {METHOD} (parameter #{INDEX} is a reference parameter overriding a non-reference parameter) defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchParamIsReferenceInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (parameter #{INDEX} is a reference parameter overriding a non-reference parameter)
```

## PhanParamSignatureRealMismatchParamNotVariadic

```
Declaration of {METHOD} should be compatible with {METHOD} (parameter #{INDEX} is a non-variadic parameter replacing a variadic parameter) defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchParamNotVariadicInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (parameter #{INDEX} is a non-variadic parameter replacing a variadic parameter)
```

## PhanParamSignatureRealMismatchParamType

```
Declaration of {METHOD} should be compatible with {METHOD} (parameter #{INDEX} of type '{TYPE}' cannot replace original parameter of type '{TYPE}') defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchParamTypeInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (parameter #{INDEX} of type '{TYPE}' cannot replace original parameter of type '{TYPE}')
```

## PhanParamSignatureRealMismatchParamVariadic

```
Declaration of {METHOD} should be compatible with {METHOD} (parameter #{INDEX} is a variadic parameter replacing a non-variadic parameter) defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchParamVariadicInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (parameter #{INDEX} is a variadic parameter replacing a non-variadic parameter)
```

## PhanParamSignatureRealMismatchReturnType

```
Declaration of {METHOD} should be compatible with {METHOD} (method returning '{TYPE}' cannot override method returning '{TYPE}') defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchReturnTypeInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (method returning '{TYPE}' cannot override method returning '{TYPE}')
```

## PhanParamSignatureRealMismatchTooFewParameters

```
Declaration of {METHOD} should be compatible with {METHOD} (the method override accepts {COUNT} parameter(s), but the overridden method can accept {COUNT}) defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchTooFewParametersInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (the method override accepts {COUNT} parameter(s), but the overridden method can accept {COUNT})
```

## PhanParamSignatureRealMismatchTooManyRequiredParameters

```
Declaration of {METHOD} should be compatible with {METHOD} (the method override requires {COUNT} parameter(s), but the overridden method requires only {COUNT}) defined in {FILE}:{LINE}
```

## PhanParamSignatureRealMismatchTooManyRequiredParametersInternal

```
Declaration of {METHOD} should be compatible with internal {METHOD} (the method override requires {COUNT} parameter(s), but the overridden method requires only {COUNT})
```

## PhanParamSpecial1

```
Argument {INDEX} ({PARAMETER}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE} when argument {INDEX} is {TYPE}
```

## PhanParamSpecial2

```
Argument {INDEX} ({PARAMETER}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE} when passed only one argument
```

## PhanParamSpecial3

```
The last argument to {FUNCTIONLIKE} must be of type {TYPE}
```

## PhanParamSpecial4

```
The second to last argument to {FUNCTIONLIKE} must be of type {TYPE}
```

## PhanParamSuspiciousOrder

```
Argument #{INDEX} of this call to {FUNCTIONLIKE} is typically a literal or constant but isn't, but argument #{INDEX} (which is typically a variable) is a literal or constant. The arguments may be in the wrong order.
```

## PhanParamTooFew

This issue indicates that you're not passing in at least the number of required parameters to a function or method.

```
Call with {COUNT} arg(s) to {FUNCTIONLIKE}() which requires {COUNT} arg(s) defined at {FILE}:{LINE}
```

This will be emitted for the code

```php
function f6($i) {}
f6();
```

## PhanParamTooFewCallable

```
Call with {COUNT} arg(s) to {FUNCTIONLIKE}() (as a provided callable) which requires {COUNT} arg(s) defined at {FILE}:{LINE}
```

## PhanParamTooFewInternal

This issue indicates that you're not passing in at least the number of required parameters to an internal function or method.

```
Call with {COUNT} arg(s) to {FUNCTIONLIKE}() which requires {COUNT} arg(s)
```

This will be emitted for the code

```php
strlen();
```

## PhanParamTooMany

This issue is emitted when you're passing more than the number of required and optional parameters than are defined for a method or function.

```
Call with {COUNT} arg(s) to {FUNCTIONLIKE}() which only takes {COUNT} arg(s) defined at {FILE}:{LINE}
```

This will be emitted for the code

```php
function f7($i) {}
f7(1, 2);
```

## PhanParamTooManyCallable

```
Call with {COUNT} arg(s) to {FUNCTIONLIKE}() (As a provided callable) which only takes {COUNT} arg(s) defined at {FILE}:{LINE}
```

## PhanParamTooManyInternal

This issue is emitted when you're passing more than the number of required and optional parameters than are defined for an internal method or function.

```
Call with {COUNT} arg(s) to {FUNCTIONLIKE}() which only takes {COUNT} arg(s)
```

This will be emitted for the code

```php
strlen('str', 42);
```

## PhanParamTypeMismatch

```
Argument {INDEX} is {TYPE} but {FUNCTIONLIKE}() takes {TYPE}
```

# RedefineError

This category of issue come up when more than one thing of whatever type have the same name and namespace.

## PhanIncompatibleCompositionMethod

```
Declaration of {METHOD} must be compatible with {METHOD} in {FILE} on line {LINE}
```

## PhanIncompatibleCompositionProp

```
{TRAIT} and {TRAIT} define the same property ({PROPERTY}) in the composition of {CLASS}. However, the definition differs and is considered incompatible. Class was composed in {FILE} on line {LINE}
```

## PhanRedefineClass

If you attempt to create a class that has the same name and namespace as a class that exists elsewhere you'll see this issue. Note that you'll get the issue on the second class in the order of files passed in to Phan.

```
{CLASS} defined at {FILE}:{LINE} was previously defined as {CLASS} at {FILE}:{LINE}
```

This issue will be emitted for code like

```php
class C15 {}
class C15 {}
```

## PhanRedefineClassAlias

This issue is emitted when `class_alias` creates ambiguity in what the intended definition of a class is.
If possible, exclude one of the files containing the conflicting definitions.

```
{CLASS} aliased at {FILE}:{LINE} was previously defined as {CLASS} at {FILE}:{LINE}
```

## PhanRedefineClassInternal

If you attempt to create a class that has the same name and namespace as a class that is internal to PHP, you'll see this issue.

```
{CLASS} defined at {FILE}:{LINE} was previously defined as {CLASS} internally
```

This issue will be emitted for code like

```php
class DateTime {}
```

## PhanRedefineFunction

This issue comes up when you have two functions (or methods) with the same name and namespace.

```
Function {FUNCTION} defined at {FILE}:{LINE} was previously defined at {FILE}:{LINE}
```

It'll come up with code like

```php
function f9() {}
function f9() {}
```

## PhanRedefineFunctionInternal

This issue comes up if you define a function or method that has the same name and namespace as an internal function or method.

```
Function {FUNCTION} defined at {FILE}:{LINE} was previously defined internally
```

You'll see this issue with code like

```php
function strlen() {}
```

## PhanRedefinedExtendedClass

```
{CLASS} extends {CLASS} declared at {FILE}:{LINE} which is also declared at {FILE}:{LINE}. This may lead to confusing errors.
```

## PhanRedefinedInheritedInterface

```
{CLASS} inherits {INTERFACE} declared at {FILE}:{LINE} which is also declared at {FILE}:{LINE}. This may lead to confusing errors.
```

## PhanRedefinedUsedTrait

```
{CLASS} uses {TRAIT} declared at {FILE}:{LINE} which is also declared at {FILE}:{LINE}. This may lead to confusing errors.
```

# StaticCallError

## PhanStaticCallToNonStatic

If you make a static call to a non static method, you'll see this issue.

```
Static call to non-static method {METHOD} defined at {FILE}:{LINE}
```

An example of this issue would come from the following code.

```php
class C19 { function f() {} }
C19::f();
```

# TypeError

This category of issue come from using incorrect types or types that cannot cast to the expected types.

## PhanMismatchVariadicComment

```
{PARAMETER} is variadic in comment, but not variadic in param ({PARAMETER})
```

## PhanMismatchVariadicParam

```
{PARAMETER} is not variadic in comment, but variadic in param ({PARAMETER})
```

## PhanNonClassMethodCall

If you call a method on a non-class element, you'll see this issue.

```
Call to method {METHOD} on non-class type {TYPE}
```

An example would come from

```php
$v8 = null;
$v8->f();
```

## PhanPartialTypeMismatchArgument

This issue (and similar issues) may be emitted when `strict_param_checking` is true, when analyzing a user-defined function.
(when some types of the argument's union type match, but not others.)

```
Argument {INDEX} ({VARIABLE}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE} ({TYPE} is incompatible) defined at {FILE}:{LINE}
```

## PhanPartialTypeMismatchArgumentInternal

This issue may be emitted when `strict_param_checking` is true, when analyzing an internal function.

```
Argument {INDEX} ({VARIABLE}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE} ({TYPE} is incompatible)
```

## PhanPartialTypeMismatchProperty

This issue (and similar issues) may be emitted when `strict_property_checking` is true

```
Assigning {TYPE} to property but {PROPERTY} is {TYPE} ({TYPE} is incompatible)
```

## PhanPartialTypeMismatchReturn

This issue (and similar issues) may be emitted when `strict_return_checking` is true
(when some types of the return statement's union type match, but not others.)

```
Returning type {TYPE} but {FUNCTIONLIKE}() is declared to return {TYPE} ({TYPE} is incompatible)
```

## PhanPossiblyFalseTypeArgument

This issue may be emitted when `strict_param_checking` is true

```
Argument {INDEX} ({VARIABLE}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE} ({TYPE} is incompatible) defined at {FILE}:{LINE}
```

## PhanPossiblyFalseTypeArgumentInternal

This issue may be emitted when `strict_param_checking` is true

```
Argument {INDEX} ({VARIABLE}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE} ({TYPE} is incompatible)
```

## PhanPossiblyFalseTypeMismatchProperty

```
Assigning {TYPE} to property but {PROPERTY} is {TYPE} ({TYPE} is incompatible)
```

## PhanPossiblyFalseTypeReturn

This issue may be emitted when `strict_return_checking` is true

```
Returning type {TYPE} but {FUNCTIONLIKE}() is declared to return {TYPE} ({TYPE} is incompatible)
```

## PhanPossiblyNullTypeArgument

This issue may be emitted when `strict_param_checking` is true

```
Argument {INDEX} ({VARIABLE}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE} ({TYPE} is incompatible) defined at {FILE}:{LINE}
```

## PhanPossiblyNullTypeArgumentInternal

This issue may be emitted when `strict_param_checking` is true

```
Argument {INDEX} ({VARIABLE}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE} ({TYPE} is incompatible)
```

## PhanPossiblyNullTypeMismatchProperty

```
Assigning {TYPE} to property but {PROPERTY} is {TYPE} ({TYPE} is incompatible)
```

## PhanPossiblyNullTypeReturn

This issue may be emitted when `strict_return_checking` is true

```
Returning type {TYPE} but {FUNCTIONLIKE}() is declared to return {TYPE} ({TYPE} is incompatible)
```

## PhanTypeArrayOperator

```
Invalid array operator between types {TYPE} and {TYPE}
```

## PhanTypeArraySuspicious

Attempting to treat a non-array or non-string element as an array will get you this issue.

```
Suspicious array access to {TYPE}
```

This issue will be emitted for the following code

```php
$a = false; if($a[1]) {}
```

## PhanTypeArraySuspiciousNullable

```
Suspicious array access to nullable {TYPE}
```

## PhanTypeArrayUnsetSuspicious

```
Suspicious attempt to unset an offset of a value of type {TYPE}
```

## PhanTypeComparisonFromArray

Comparing an array to a non-array will result in this issue.

```
array to {TYPE} comparison
```

An example would be

```php
if ([1, 2] == 'string') {}
```

## PhanTypeComparisonToArray

Comparing a non-array to an array will result in this issue.

```
{TYPE} to array comparison
```

An example would be

```php
if (42 == [1, 2]) {}
```

## PhanTypeConversionFromArray

```
array to {TYPE} conversion
```

## PhanTypeExpectedObject

```
Expected an object instance but saw expression with type {TYPE}
```

## PhanTypeExpectedObjectOrClassName

```
Expected an object instance or the name of a class but saw expression with type {TYPE}
```

## PhanTypeExpectedObjectPropAccess

```
Expected an object instance when accessing an instance property, but saw an expression with type {TYPE}
```

## PhanTypeExpectedObjectPropAccessButGotNull

```
Expected an object instance when accessing an instance property, but saw an expression with type {TYPE}
```

## PhanTypeExpectedObjectStaticPropAccess

```
Expected an object instance or a class name when accessing a static property, but saw an expression with type {TYPE}
```

## PhanTypeInstantiateAbstract

```
Instantiation of abstract class {CLASS}
```

This issue will be emitted for the following code

```php
abstract class D {} (new D);
```

## PhanTypeInstantiateInterface

```
Instantiation of interface {INTERFACE}
```

This issue will be emitted for the following code

```php
interface E {} (new E);
```

## PhanTypeInvalidCallableArrayKey

```
In a place where phan was expecting a callable, saw an array with an unexpected key for element #{INDEX} (expected [$class_or_expr, $method_name])
```

## PhanTypeInvalidCallableArraySize

```
In a place where phan was expecting a callable, saw an array of size {COUNT}, but callable arrays must be of size 2
```

## PhanTypeInvalidCallableObjectOfMethod

```
In a place where phan was expecting a callable, saw a two-element array with a class or expression with an unexpected type {TYPE} (expected a class type or string). Method name was {METHOD}
```

## PhanTypeInvalidClosureScope

```
Invalid @phan-closure-scope: expected a class name, got {TYPE}
```

## PhanTypeInvalidDimOffset

```
Invalid offset {SCALAR} of array type {TYPE}
```

## PhanTypeInvalidDimOffsetArrayDestructuring

```
Invalid offset {SCALAR} of array type {TYPE} in an array destructuring assignment
```

## PhanTypeInvalidInstanceof

```
Found an instanceof class name of type {TYPE}, but class name must be a valid object or a string
```

## PhanTypeInvalidLeftOperand

```
Invalid operator: right operand is array and left is not
```

## PhanTypeInvalidLeftOperandOfAdd

```
Invalid operator: left operand is {TYPE} (expected array or number)
```

## PhanTypeInvalidLeftOperandOfNumericOp

```
Invalid operator: left operand is {TYPE} (expected number)
```

## PhanTypeInvalidRightOperand

```
Invalid operator: left operand is array and right is not
```

## PhanTypeInvalidRightOperandOfAdd

```
Invalid operator: right operand is {TYPE} (expected array or number)
```

## PhanTypeInvalidRightOperandOfNumericOp

```
Invalid operator: right operand is {TYPE} (expected number)
```

## PhanTypeInvalidThrowsIsInterface

```
@throws annotation of {FUNCTIONLIKE} has suspicious interface type {TYPE} for an @throws annotation, expected class (PHP allows interfaces to be caught, so this might be intentional)
```

## PhanTypeInvalidThrowsIsTrait

```
@throws annotation of {FUNCTIONLIKE} has invalid trait type {TYPE}, expected a class
```

## PhanTypeInvalidThrowsNonObject

```
@throws annotation of {FUNCTIONLIKE} has invalid non-object type {TYPE}, expected a class
```

## PhanTypeInvalidThrowsNonThrowable

```
@throws annotation of {FUNCTIONLIKE} has suspicious class type {TYPE}, which does not extend Error/Exception
```

## PhanTypeInvalidYieldFrom

```
Yield from statement was passed an invalid expression of type {TYPE} (expected Traversable/array)
```

## PhanTypeMagicVoidWithReturn

```
Found a return statement with a value in the implementation of the magic method {METHOD}, expected void return type
```

This will be emitted for the code

```php
// (PHP ignores the return value of some magic methods, such as __set)
class A { public function __set() { return true; } }
```

## PhanTypeMismatchArgument

```
Argument {INDEX} ({VARIABLE}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE} defined at {FILE}:{LINE}
```

This will be emitted for the code

```php
function f8(int $i) {}
f8('string');
```

## PhanTypeMismatchArgumentInternal

```
Argument {INDEX} ({VARIABLE}) is {TYPE} but {FUNCTIONLIKE}() takes {TYPE}
```

This will be emitted for the code

```php
strlen(42);
```

## PhanTypeMismatchArrayDestructuringKey

```
Attempting an array destructing assignment with a key of type {TYPE} but the only key types of the right hand side are of type {TYPE}
```

## PhanTypeMismatchDeclaredParam

```
Doc-block of ${VARIABLE} in {METHOD} contains phpdoc param type {TYPE} which is incompatible with the param type {TYPE} declared in the signature
```

## PhanTypeMismatchDeclaredParamNullable

```
Doc-block of ${VARIABLE} in {METHOD} is phpdoc param type {TYPE} which is not a permitted replacement of the nullable param type {TYPE} declared in the signature ('?T' should be documented as 'T|null' or '?T')
```

## PhanTypeMismatchDeclaredReturn

```
Doc-block of {METHOD} contains declared return type {TYPE} which is incompatible with the return type {TYPE} declared in the signature
```

## PhanTypeMismatchDeclaredReturnNullable

```
Doc-block of {METHOD} has declared return type {TYPE} which is not a permitted replacement of the nullable return type {TYPE} declared in the signature ('?T' should be documented as 'T|null' or '?T')
```

## PhanTypeMismatchDefault

```
Default value for {TYPE} ${VARIABLE} can't be {TYPE}
```

## PhanTypeMismatchDimAssignment

```
When appending to a value of type {TYPE}, found an array access index of type {TYPE}, but expected the index to be of type {TYPE}
```

## PhanTypeMismatchDimEmpty

```
Assigning to an empty array index of a value of type {TYPE}, but expected the index to exist and be of type {TYPE}
```

## PhanTypeMismatchDimFetch

```
When fetching an array index from a value of type {TYPE}, found an array index of type {TYPE}, but expected the index to be of type {TYPE}
```

## PhanTypeMismatchDimFetchNullable

```
When fetching an array index from a value of type {TYPE}, found an array index of type {TYPE}, but expected the index to be of the non-nullable type {TYPE}
```

## PhanTypeMismatchForeach

This issue will be emitted when something that can't be an array is passed as the array_expression.

```
{TYPE} passed to foreach instead of array
```

This will be emitted for the code

```php
foreach (null as $i) {}
```

## PhanTypeMismatchGeneratorYieldKey

```
Yield statement has a key with type {TYPE} but {FUNCTIONLIKE}() is declared to yield keys of type {TYPE} in {TYPE}
```

## PhanTypeMismatchGeneratorYieldValue

```
Yield statement has a value with type {TYPE} but {FUNCTIONLIKE}() is declared to yield values of type {TYPE} in {TYPE}
```

## PhanTypeMismatchProperty

```
Assigning {TYPE} to property but {PROPERTY} is {TYPE}
```

This issue is emitted from the following code

```php
function f(int $p = false) {}
```

## PhanTypeMismatchReturn

```
Returning type {TYPE} but {FUNCTIONLIKE}() is declared to return {TYPE}
```

This issue is emitted from the following code

```php
class G { function f() : int { return 'string'; } }
```

## PhanTypeMismatchUnpackKey

```
When unpacking a value of type {TYPE}, the value's keys were of type {TYPE}, but the keys should be consecutive integers starting from 0
```

## PhanTypeMismatchUnpackValue

```
Attempting to unpack a value of type {TYPE} which does not contain any subtypes of iterable (such as array or Traversable)
```

## PhanTypeMissingReturn

```
Method {METHOD} is declared to return {TYPE} but has no return value
```

This issue is emitted from the following code

```php
class H { function f() : int {} }
```

## PhanTypeNonVarPassByRef

```
Only variables can be passed by reference at argument {INDEX} of {FUNCTIONLIKE}()
```

This issue is emitted from the following code

```php
class F { static function f(&$v) {} } F::f('string');
```

## PhanTypeParentConstructorCalled

```
Must call parent::__construct() from {CLASS} which extends {CLASS}
```

## PhanTypeSuspiciousEcho

```
Suspicious argument {TYPE} for an echo/print statement
```

## PhanTypeSuspiciousIndirectVariable

```
Indirect variable ${(expr)} has invalid inner expression type {TYPE}, expected string/integer
```

## PhanTypeSuspiciousStringExpression

```
Suspicious type {TYPE} of a variable or expression encapsulated within a string. (Expected this to be able to cast to a string)
```

## PhanTypeVoidAssignment

This is triggered by assigning the return value of a function or method that returns void.

```
Cannot assign void return value
```

This issue will be emitted from the following code:

```php
class A { /** @return void */ function v() {} }
$a = (new A)->v();
```

# UndefError

This category of issue come up when there are references to undefined things. These are a big source of false-positives in Phan given that code bases often take liberties with calling methods on sub-classes of the class defined to be returned by a function and things like that.

You can ignore all errors of this category by passing in the command-line argument `-i` or `--ignore-undeclared`.

## PhanAmbiguousTraitAliasSource

```
Trait alias {METHOD} has an ambiguous source method {METHOD} with more than one possible source trait. Possibilities: {TRAIT}
```

## PhanClassContainsAbstractMethod

```
non-abstract class {CLASS} contains abstract method {METHOD} declared at {FILE}:{LINE}
```

## PhanClassContainsAbstractMethodInternal

```
non-abstract class {CLASS} contains abstract internal method {METHOD}
```

## PhanEmptyFQSENInCallable

```
Possible call to a function '{FUNCTIONLIKE}' with an empty FQSEN.
```

## PhanEmptyFQSENInClasslike

```
Possible use of a classlike '{CLASSLIKE}' with an empty FQSEN.
```

## PhanEmptyFile

This low severity issue is emitted for empty files.

```
Empty file {FILE}
```

This would be emitted if you have a file with the contents

```php
```

## PhanParentlessClass

If there is a reference to the parent of a class that does not extend something, you'll see this issue.

```
Reference to parent of class {CLASS} that does not extend anything
```

This issue will be emitted from the following code

```php
class F { function f() { $v = parent::f(); } }
```

## PhanRequiredTraitNotAdded

This happens when a trait name is used in a trait adaptations clause, but that trait wasn't added to the class.

```
Required trait {TRAIT} for trait adaptation was not added to class
```

You'll see this issue with code like

```php
trait T1 {}
trait T2 {}
class A {
	use T1 {T2::foo as bar;}
}
```

## PhanTraitParentReference

If you reference `parent` from within a trait, you'll get this issue. This is a low priority issue given that it is legal in PHP, but for general-purpose traits you should probably avoid this pattern.

```
Reference to parent from trait {TRAIT}
```

This issue will be emitted from the following code

```php
trait T { function f() { return parent::f(); } }
```

## PhanUndeclaredAliasedMethodOfTrait

```
Alias {METHOD} was defined for a method {METHOD} which does not exist in trait {TRAIT}
```

## PhanUndeclaredClass

```
Reference to undeclared class {CLASS}
```

## PhanUndeclaredClassAliasOriginal

```
Reference to undeclared class {CLASS} for the original class of a class_alias for {CLASS}
```

## PhanUndeclaredClassCatch

If you're catching a throwable of a type that isn't defined, you'll see this issue.

```
Catching undeclared class {CLASS}
```

The following code will emit this error.

```php
try {} catch (Undef $exception) {}
```

## PhanUndeclaredClassConstant

```
Reference to constant {CONST} from undeclared class {CLASS}
```

## PhanUndeclaredClassInCallable

```
Reference to undeclared class {CLASS} in callable {METHOD}
```

## PhanUndeclaredClassInstanceof

```
Checking instanceof against undeclared class {CLASS}
```

This issue will be emitted from the following code

```php
$v = null;
if ($v instanceof Undef) {}
```

## PhanUndeclaredClassMethod

```
Call to method {METHOD} from undeclared class {CLASS}
```

This issue will be emitted from the following code

```php
function g(Undef $v) { $v->f(); }
```

## PhanUndeclaredClassReference

```
Reference to undeclared class {CLASS}
```

## PhanUndeclaredClosureScope

```
Reference to undeclared class {CLASS} in @phan-closure-scope
```

## PhanUndeclaredConstant

This issue comes up when you reference a constant that doesn't exist.

```
Reference to undeclared constant {CONST}
```

You'll see this issue with code like

```php
$v7 = UNDECLARED_CONSTANT;
```
## PhanUndeclaredExtendedClass

You'll see this issue if you extend a class that doesn't exist.

```
Class extends undeclared class {CLASS}
```

This issue will be emitted from the following code

```php
class E extends Undef {}
```

## PhanUndeclaredFunction

This issue will be emitted if you reference a function that doesn't exist.

```
Call to undeclared function {FUNCTION}
```

This issue will be emitted for the code

```php
f10();
```

## PhanUndeclaredFunctionInCallable

```
Call to undeclared function {FUNCTION} in callable
```

## PhanUndeclaredInterface

Implementing an interface that doesn't exist or otherwise can't be found will emit this issue.

```
Class implements undeclared interface {INTERFACE}
```

The following code will express this issue.

```php
class C17 implements C18 {}
```

## PhanUndeclaredMethod

```
Call to undeclared method {METHOD}
```

## PhanUndeclaredMethodInCallable

```
Call to undeclared method {METHOD} in callable. Possible object type(s) for that method are {TYPE}
```

## PhanUndeclaredProperty

```
Reference to undeclared property {PROPERTY}
```

This issue will be emitted from the following code

```php
class C {}
$v = (new C)->undef;
```

## PhanUndeclaredStaticMethod

```
Static call to undeclared method {METHOD}
```

This issue will be emitted from the following code

```php
C::staticMethod();
```

## PhanUndeclaredStaticMethodInCallable

```
Reference to undeclared static method {METHOD} in callable
```

## PhanUndeclaredStaticProperty

Attempting to read a property that doesn't exist will result in this issue. You'll also see this issue if you write to an undeclared static property so long as `Config::get()->allow_missing_property` is false (which defaults to true).

```
Static property '{PROPERTY}' on {CLASS} is undeclared
```

An example would be

```php
class C22 {}
$v11 = C22::$p;
```

## PhanUndeclaredTrait

If you attempt to use a trait that doesn't exist, you'll see this issue.

```
Class uses undeclared trait {TRAIT}
```

An example would be

```php
class C20 { use T2; }
```

## PhanUndeclaredTypeParameter

If you have a parameter on a function or method of a type that is not defined, you'll see this issue.

```
Parameter of undeclared type {TYPE}
```

This issue will be emitted from the following code

```php
function f(Undef $p) {}
```

## PhanUndeclaredTypeProperty

If you have a property with an undefined type, you'll see this issue.

```
Property {PROPERTY} has undeclared type {TYPE}
```

This issue will be emitted from the following code

```php
class D { /** @var Undef */ public $p; }
```

## PhanUndeclaredTypeReturnType

```
Return type of {METHOD} is undeclared type {TYPE}
```

## PhanUndeclaredTypeThrowsType

```
@throws type of {METHOD} has undeclared type {TYPE}
```

## PhanUndeclaredVariable

Trying to use a variable that hasn't been defined anywhere in scope will produce this issue.

```
Variable ${VARIABLE} is undeclared
```

An example would be

```php
$v9 = $v10;
```

## PhanUndeclaredVariableAssignOp

```
Variable ${VARIABLE} was undeclared, but it is being used as the left hand side of an assignment operation
```

## PhanUndeclaredVariableDim

```
Variable ${VARIABLE} was undeclared, but array fields are being added to it.
```

# VarError

## PhanVariableUseClause

```
Non-variables not allowed within use clause
```

# Generic

This category contains issues related to [Phan's generic type support](https://github.com/phan/phan/wiki/Generic-Types)

## PhanGenericConstructorTypes

```
Missing template parameters {PARAMETER} on constructor for generic class {CLASS}
```

## PhanGenericGlobalVariable

```
Global variable {VARIABLE} may not be assigned an instance of a generic class
```

## PhanTemplateTypeConstant

This is emitted when a class constant's PHPDoc contains a type declared in a class's phpdoc template annotations.

```
constant {CONST} may not have a template type
```

## PhanTemplateTypeStaticMethod

This is emitted when a static method's PHPdoc contains a param/return type declared in a class's phpdoc template annotations.

```
static method {METHOD} may not use template types
```

## PhanTemplateTypeStaticProperty

This is emitted when a static property's PHPdoc contains an `@var` type declared in the class's phpdoc template annotations.

```
static property {PROPERTY} may not have a template type
```



# Internal

This issue category comes up when there is an attempt to access an `@internal` element (property, class, constant, method, function, etc.) outside of the namespace in which it's defined.

This category is completely unrelated to elements being internal to PHP (i.e. part of PHP core or PHP modules).

## PhanAccessClassConstantInternal

This issue comes up when there is an attempt to access an `@internal` class constant outside of the namespace in which it's defined.

```
Cannot access internal class constant {CONST} defined at {FILE}:{LINE}
```

## PhanAccessClassInternal

This issue comes up when there is an attempt to access an `@internal` class constant outside of the namespace in which it's defined.

```
Cannot access internal {CLASS} defined at {FILE}:{LINE}
```

## PhanAccessConstantInternal

This issue comes up when there is an attempt to access an `@internal` global constant outside of the namespace in which it's defined.

```
Cannot access internal constant {CONST} of namepace {NAMESPACE} defined at {FILE}:{LINE} from namespace {NAMESPACE}
```

## PhanAccessMethodInternal

This issue comes up when there is an attempt to access an `@internal` method outside of the namespace in which it's defined.

```
Cannot access internal method {METHOD} of namespace {NAMESPACE} defined at {FILE}:{LINE} from namespace {NAMESPACE}
```

## PhanAccessPropertyInternal

This issue comes up when there is an attempt to access an `@internal` property outside of the namespace in which it's defined.

```
Cannot access internal property {PROPERTY} of namespace {NAMESPACE} defined at {FILE}:{LINE} from namespace {NAMESPACE}
```

# CommentError

This is emitted for some (but not all) comments which Phan thinks are invalid or unparseable.

## PhanCommentOverrideOnNonOverrideConstant

```
Saw an @override annotation for class constant {CONST}, but could not find an overridden constant
```

## PhanCommentOverrideOnNonOverrideMethod

```
Saw an @override annotation for method {METHOD}, but could not find an overridden method and it is not a magic method
```

## PhanCommentParamOnEmptyParamList

```
Saw an @param annotation for {VARIABLE}, but the param list of {FUNCTIONLIKE} is empty
```

## PhanCommentParamOutOfOrder

```
Expected @param annotation for {VARIABLE} to be before the @param annotation for {VARIABLE}
```

## PhanCommentParamWithoutRealParam

```
Saw an @param annotation for {VARIABLE}, but it was not found in the param list of {FUNCTIONLIKE}
```

## PhanInvalidCommentForDeclarationType

```
The phpdoc comment for {COMMENT} cannot occur on a {TYPE}
```

## PhanMisspelledAnnotation

```
Saw misspelled annotation {COMMENT}, should be one of {COMMENT}
```

## PhanUnextractableAnnotation

```
Saw unextractable annotation for comment '{COMMENT}'
```

## PhanUnextractableAnnotationElementName

```
Saw possibly unextractable annotation for a fragment of comment '{COMMENT}': after {TYPE}, did not see an element name (will guess based on comment order)
```

## PhanUnextractableAnnotationPart

```
Saw unextractable annotation for a fragment of comment '{COMMENT}': '{COMMENT}'
```

## PhanUnextractableAnnotationSuffix

```
Saw a token Phan may have failed to parse after '{COMMENT}': after {TYPE}, saw '{COMMENT}'
```

# Syntax

Emitted for syntax errors.

## PhanSyntaxError

This emits warnings for unparseable PHP files (detected by `php-ast`).
Note: This is not the same thing as running `php -l` on a file - PhanSyntaxError checks for syntax errors, but not sematics such as where certain expressions can occur (Which `php -l` would check for).
