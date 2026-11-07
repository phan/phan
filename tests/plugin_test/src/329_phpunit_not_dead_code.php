<?php
// PHPUnitNotDeadCodePlugin: test case classes, tests, data providers and hook methods are referenced (#5629)
// @phan-file-suppress PhanWriteOnlyPublicProperty the properties of the attribute stubs are read by PHPUnit
namespace PHPUnit\Framework {
    abstract class TestCase {
        public function assertTrue(bool $condition): void {
            if (!$condition) {
                echo "failed\n";
            }
        }
    }
}
namespace PHPUnit\Framework\Attributes {
    #[\Attribute(\Attribute::TARGET_METHOD)]
    final class Test {}
    #[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
    final class DataProvider {
        public function __construct(public readonly string $methodName) {}
    }
    #[\Attribute(\Attribute::TARGET_METHOD)]
    final class DataProviderExternal {
        public function __construct(public readonly string $className, public readonly string $methodName) {}
    }
    #[\Attribute(\Attribute::TARGET_METHOD)]
    final class Before {}
    #[\Attribute(\Attribute::TARGET_METHOD)]
    final class AfterClass {}
}
namespace App5629 {
    use PHPUnit\Framework\Attributes\AfterClass;
    use PHPUnit\Framework\Attributes\Before;
    use PHPUnit\Framework\Attributes\DataProvider;
    use PHPUnit\Framework\Attributes\DataProviderExternal;
    use PHPUnit\Framework\Attributes\Test;
    use PHPUnit\Framework\TestCase;

    final class Providers5629 {
        public static function provideExternal(): array {
            return [[1]];
        }
        public static function provideUnused(): array {
            return [[1]];
        }
    }

    final class AnnotationsTest extends TestCase {
        /** @before */
        protected function prepare(): void {
            echo "prepare\n";
        }

        /**
         * @dataProvider provideFirst
         * @dataProvider provideSecond
         */
        public function testThing(int $x): void {
            $this->assertTrue($x > 0);
        }

        /** @test */
        public function somethingWorks(): void {
            $this->assertTrue(true);
        }

        public function provideFirst(): array {
            return [[1]];
        }

        public function provideSecond(): array {
            return [[2]];
        }

        public function provideUnused(): array {
            return [[2]];
        }

        private function helperUnused(): void {
            echo "unused\n";
        }
    }

    final class AttributesTest extends TestCase {
        #[Before]
        protected function prepare(): void {
            echo "prepare\n";
        }

        #[AfterClass]
        public static function cleanUp(): void {
            echo "clean up\n";
        }

        #[DataProvider('provideThings')]
        #[DataProvider(methodName: 'provideMoreThings')]
        #[DataProviderExternal(Providers5629::class, 'provideExternal')]
        public function testThing(int $x): void {
            $this->assertTrue($x > 0);
        }

        #[Test]
        public function somethingWorks(): void {
            $this->assertTrue(true);
        }

        public static function provideThings(): array {
            return [[1]];
        }

        public static function provideMoreThings(): array {
            return [[1]];
        }

        public static function provideUnused(): array {
            return [[1]];
        }
    }

    // Tests, data providers and hooks provided by a trait: the copies in the test case have no attributes or doc comment
    trait SharedTests5629 {
        #[Before]
        protected function prepareShared(): void {
            echo "prepare\n";
        }

        /** @before */
        protected function prepareSharedAnnotated(): void {
            echo "prepare\n";
        }

        #[Test]
        public function sharedAttributeTest(): void {
            echo "shared test\n";
        }

        /** @test */
        public function sharedAnnotatedTest(): void {
            echo "shared test\n";
        }

        #[DataProvider('provideShared')]
        public function testShared(int $x): void {
            echo $x;
        }

        /** @dataProvider provideSharedAnnotated */
        public function testSharedAnnotated(int $x): void {
            echo $x;
        }

        public static function provideShared(): array {
            return [[1]];
        }

        public static function provideSharedAnnotated(): array {
            return [[1]];
        }

        public static function provideSharedUnused(): array {
            return [[1]];
        }
    }

    final class UsesSharedTestsTest extends TestCase {
        use SharedTests5629;
    }

    final class NotATest {
        public function testThing(): void {
            echo "not a test\n";
        }
    }

    // The test case can rename a trait method or change its visibility: PHPUnit sees the name and the visibility of the copy,
    // and the annotations and attributes of the trait's declaration.
    trait AliasedTests5629 {
        protected function helper(): void {
            echo "aliased test\n";
        }

        #[Test]
        protected function attributed(): void {
            echo "aliased test\n";
        }

        protected function testProtected(): void {
            echo "aliased test\n";
        }

        protected function testNotPublic(): void {
            echo "not a test\n";
        }

        // `self` in a method of a trait is the class using the trait
        #[DataProviderExternal(self::class, 'provideSelf')]
        public function testSelf(int $x): void {
            echo $x;
        }
    }

    final class AliasedTestsTest extends TestCase {
        use AliasedTests5629 {
            helper as public testHelper;
            attributed as public;
            testProtected as public;
        }

        public static function provideSelf(): array {
            return [[1]];
        }
    }

    // Hooks and tests of a trait used through another trait
    trait InnerHooks5629 {
        #[Before]
        protected function prepareInner(): void {
            echo "prepare\n";
        }

        /** @after */
        protected function cleanUpInner(): void {
            echo "clean up\n";
        }

        #[Test]
        protected function innerTest(): void {
            echo "inner test\n";
        }
    }

    trait OuterHooks5629 {
        use InnerHooks5629;

        public function testOuter(): void {
            echo "outer test\n";
        }
    }

    final class NestedHooksTest extends TestCase {
        use OuterHooks5629 {
            innerTest as public;
        }
    }

    // `self` in a test inherited from a parent class is the parent class
    abstract class BaseProvidersTest extends TestCase {
        #[DataProviderExternal(self::class, 'provideBase')]
        public function testBase(int $x): void {
            echo $x;
        }

        public static function provideBase(): array {
            return [[1]];
        }
    }

    final class ChildProvidersTest extends BaseProvidersTest {
    }
}
