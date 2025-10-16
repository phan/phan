<?php

declare(strict_types=1);

namespace Phan\Tests\Language;

use Phan\Language\Type\MixedType;
use Phan\Language\Type\NonEmptyMixedType;
use Phan\Language\Type\NonNullMixedType;
use Phan\Language\Type\NullType;
use PHPUnit\Framework\TestCase;

/**
 * Test the behavior of MixedType and its variants
 */
class MixedTypeTest extends TestCase
{
    /**
     * Test that removing truthy types from mixed doesn't narrow to just null
     * @see https://github.com/phan/phan/issues/5074
     */
    public function testMixedAsNonTruthyType(): void
    {
        $mixed = MixedType::instance(false);
        $result = $mixed->asNonTruthyType();

        // Mixed's asNonTruthyType() method returns null by default,
        // but the UnionType's toNonTruthyTypeSet handles it specially
        // to return all possible falsey values
        $this->assertInstanceOf(NullType::class, $result);
    }

    /**
     * Test that non-null-mixed handles asNonTruthyType correctly
     */
    public function testNonNullMixedAsNonTruthyType(): void
    {
        $nonNullMixed = NonNullMixedType::instance(false);
        $result = $nonNullMixed->asNonTruthyType();

        // Non-null-mixed after removing truthy types should be null
        // (because the default implementation returns null for types that can't be falsey)
        $this->assertInstanceOf(NullType::class, $result);
    }

    /**
     * Test that non-empty-mixed handles asNonTruthyType correctly
     */
    public function testNonEmptyMixedAsNonTruthyType(): void
    {
        $nonEmptyMixed = NonEmptyMixedType::instance(false);
        $result = $nonEmptyMixed->asNonTruthyType();

        // Non-empty-mixed is always truthy, so removing truthy values leaves nothing (null)
        $this->assertInstanceOf(NullType::class, $result);

        // Nullable version
        $nullableNonEmptyMixed = NonEmptyMixedType::instance(true);
        $nullableResult = $nullableNonEmptyMixed->asNonTruthyType();

        // Nullable non-empty-mixed after removing truthy values leaves only null
        $this->assertInstanceOf(NullType::class, $nullableResult);
    }
}
