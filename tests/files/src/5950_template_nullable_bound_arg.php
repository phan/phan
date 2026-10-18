<?php
// Regression test for #5607: passing T (of Bound) or null to a `?T` parameter
// must not emit PhanTemplateTypeConstraintViolation or PhanTypeMismatchArgument.
class DemoObject5950 {}
class OtherObject5950 {}

/** @template T of DemoObject5950 */
class Demo5950 {
    /** @param T[] $objects */
    public function __construct( protected array $objects = [] ) {
    }

    /** @param ?T $arg */
    protected function test( ?DemoObject5950 $arg = null ): void {
        var_dump($arg);
    }

    /** @param T $arg */
    protected function testNonNull( DemoObject5950 $arg ): void {
        var_dump($arg);
    }

    public function demo( int $i ): void {
        $l = count( $this->objects );
        $this->test( $i + 1 < $l ? $this->objects[$i + 1] : null );
        $this->test( null );
        $this->test( $this->objects[0] );
        $this->testNonNull( $this->objects[0] );
    }

    /** @param ?T $x */
    public function passNullable($x): void {
        $this->test( $x );
        $this->testNonNull( $x );  // should warn: ?T to T
    }

    public function passWrong(): void {
        $this->test( new OtherObject5950() );  // should warn: wrong class
        $this->testNonNull( new OtherObject5950() );  // should warn: wrong class
        $this->test( new DemoObject5950() );
    }
}
(new Demo5950([]))->demo(1);
