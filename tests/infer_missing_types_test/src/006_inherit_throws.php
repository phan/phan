<?php

// @phan-file-suppress PhanUnreferencedPublicMethod, PhanUnreferencedClass, PhanUnreferencedFunction

namespace NS978;

use RuntimeException;

class BaseClass {
    /**
     * @throws RuntimeException
     */
    public function foo(): void {
        throw new RuntimeException( 'A' );
    }
}

class ChildClass extends BaseClass {
    public function foo(): void {
        // With inherit_phpdoc_types, the method should inherit @throws from the parent and
        // this line should NOT emit PhanThrowTypeAbsent
        throw new RuntimeException( 'B' );
    }
}

class BaseClassThatThrows {
    /**
     * @throws RuntimeException
     */
    public function foo(): void {
        throw new RuntimeException( 'A' );
    }
}

class ChildClassThatDoesNotThrow extends BaseClassThatThrows {
    public function foo(): void {
        // This method also inherit @throws from the parent, but we do not emit any issue if callers
        // of this method do not catch the exception.
        echo "this method definitely does not throw any exceptions";
    }
}

function doTest() {
    $c = new ChildClassThatDoesNotThrow();
    $c->foo();// Phan should NOT emit PhanThrowTypeAbsentForCall here
}
