<?php

abstract class Abstract316 {}

interface Interface316 {}

trait Trait316 {}

class BadInheritance316
    extends Trait316
    implements Interface316, Abstract316 {}

class BadInheritanceInternal316 implements SplObjectStorage {
    use Exception;
    use Interface316;
}

interface MyBadExceptionInterface316
    extends Exception { }

interface MyGoodInterface extends ArrayAccess {}

interface MyBadInternalInterfaceUsage316 {
    use ArrayAccess;
}

// Test WeakMap template parameter requirements
class MyWeakMap316 extends WeakMap {
    // This should trigger PhanGenericMissingParameters without @extends annotation
}

/**
 * @extends WeakMap<\stdClass,int>
 */
class MyTypedWeakMap316 extends WeakMap {
    // This is correct - template parameters specified
}
