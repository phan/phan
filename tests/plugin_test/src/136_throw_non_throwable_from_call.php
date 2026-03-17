<?php declare(strict_types=1);

// Regression test for https://github.com/phan/phan/issues/5490
// Phan crashed with "Undefined array key 'expr'" when
// warnAboutPossiblyThrownType() was called from visitCall/visitStaticCall
// with a @throws type that is not a Throwable subtype.

namespace NS5490;

class NotAThrowable {}

class Caller {
    /**
     * @throws NotAThrowable
     */
    public static function staticMethod(): void {}

    /**
     * @throws NotAThrowable
     */
    public function instanceMethod(): void {}

    /**
     * @throws \RuntimeException
     */
    public function caller(): void {
        // These should not crash; they should emit TypeInvalidThrowStatementNonThrowable
        self::staticMethod();
        $this->instanceMethod();
    }
}
