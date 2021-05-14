<?php

declare(strict_types=1);

namespace Phan\Tests;

use Phan\CodeBase;

/**
 * This represents a test case that has a CodeBase instance.
 *
 * The CodeBase instance is created as a shallow clone to isolate
 * changes (e.g. adding element declarations) made by tests.
 */
abstract class CodeBaseAwareTest extends BaseTest
{
    /** @var CodeBase a temporary codebase for this test case run */
    protected $code_base;

    /**
     * Sets the codebase used by this unit test to a valid codebase (before the test),
     * or to null (to free up memory after the test is complete)
     *
     * @param ?CodeBase $code_base
     * @suppress PhanPossiblyNullTypeMismatchProperty
     */
    public function setCodeBase(?CodeBase $code_base): void
    {
        $this->code_base = $code_base;
    }

    public function setUp(): void
    {
        // We're holding a static reference to the
        // CodeBase because its pretty slow to build. To
        // avoid state moving from test to test, we clone
        // the CodeBase for each test to avoid changing
        // the one we're building here.
        static $code_base = null;
        if (!$code_base) {
            global $internal_class_name_list;
            global $internal_interface_name_list;
            global $internal_trait_name_list;
            global $internal_function_name_list;
            if (!isset($internal_class_name_list)) {
                require_once(\dirname(__DIR__, 2) . '/src/codebase.php');
            }

            $code_base = new CodeBase(
                $internal_class_name_list,  // @phan-suppress-current-line PhanTypeMismatchArgument
                $internal_interface_name_list,
                $internal_trait_name_list,
                CodeBase::getPHPInternalConstantNameList(),  // Get everything except user-defined constants
                $internal_function_name_list
            );
        }

        // @phan-suppress-next-line PhanStaticClassAccessWithStaticVariable this is deliberately writing the same value to different test subclasses
        $this->setCodeBase($code_base->shallowClone());
    }

    public function tearDown(): void
    {
        parent::tearDown();
        $this->setCodeBase(null);
    }
}
