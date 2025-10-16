<?php

declare(strict_types=1);

namespace Phan;

use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Property;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Plugin\ConfigPluginSet;
use Phan\Tests\CodeBaseAwareTestBase;

/**
 * Unit tests of Phan analysis targeting AsymmetricVisibility (introduced in PHP 8.4).
 */
final class AsymmetricVisibilityTest extends CodeBaseAwareTestBase
{
    private const OVERRIDES = [
        'unused_variable_detection' => true,  // for use with tests of arrow functions
        'redundant_condition_detection' => true,  // for use with typed properties
        'dead_code_detection' => true,  // for use with constructor property promotion, etc.
        'target_php_version' => '8.4',
        'minimum_target_php_version' => '8.4',  // Test that checks and type inferences for older php versions aren't supported.
        'plugins' => [
            'DuplicateArrayKeyPlugin',
            'EmptyMethodAndFunctionPlugin',
            'UnknownElementTypePlugin',
            'UnreachableCodePlugin',
            'UseReturnValuePlugin',
        ],
        'plugin_config' => ['infer_pure_methods' => true],
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        foreach (self::OVERRIDES as $key => $value) {
            Config::setValue($key, $value);
        }
        ConfigPluginSet::reset();  // @phan-suppress-current-line PhanAccessMethodInternal
    }

    /**
     * Runs test to check Asymmetric Visibility
     *
     * @suppress PhanThrowTypeAbsentForCall
     * @dataProvider getCases
     */
    public function testAsymmetricVisibility(
        string $file,
        string $fqcn,
        string $prop1Visibility,
        ?string $prop1SetVisibility,
        string $prop2Visibility,
        ?string $prop2SetVisibility,
        string $prop3Visibility,
        ?string $prop3SetVisibility
    ): void {
        if (\PHP_VERSION_ID < 80400) {
            $this->markTestSkipped("PHP 8.4 is required");
        }

        $this->parseFile($file);;
        $fqcn = FullyQualifiedClassName::fromFullyQualifiedString($fqcn);
        $c = $this->code_base->getClassByFQSEN($fqcn);

        $p = $this->getClassProperty($c, 'prop1');
        $this->assertEquals($prop1Visibility, $p->getVisibilityName());
        $this->assertEquals($prop1SetVisibility, $p->getVisibilitySetName());

        $p = $this->getClassProperty($c, 'prop2');
        $this->assertEquals($prop2Visibility, $p->getVisibilityName());
        $this->assertEquals($prop2SetVisibility, $p->getVisibilitySetName());

        $p = $this->getClassProperty($c, 'prop3');
        $this->assertEquals($prop3Visibility, $p->getVisibilityName());
        $this->assertEquals($prop3SetVisibility, $p->getVisibilitySetName());
    }

    /**
     * Provides a list of test cases
     */
    public static function getCases(): iterable
    {
        yield 'No Asymmetric Visibility is used' => [
            '001_no_asymmetric_visibility',
            '\NoAsymmetricVisibility',
            'public',
            null,
            'protected',
            null,
            'private',
            null
        ];
        yield 'Public properties with Asymmetric Visibility' => [
            '002_public_props_with_av',
            '\PublicPropsWithAV',
            'public',
            'public',
            'public',
            'protected',
            'public',
            'private',
        ];
        yield 'Protected properties with Asymmetric Visibility' => [
            '003_protected_props_with_av',
            '\ProtectedPropsWithAV',
            'protected',
            'public',
            'protected',
            'protected',
            'protected',
            'private',
        ];
        yield 'Private properties with Asymmetric Visibility' => [
            '004_private_props_with_av',
            '\PrivatePropsWithAV',
            'private',
            'public',
            'private',
            'protected',
            'private',
            'private',
        ];
        yield 'Constructor promoted properties with Asymmetric Visibility' => [
            '005_promoted_public_props_with_av',
            '\PromotedPublicPropsWithAV',
            'public',
            'public',
            'public',
            'protected',
            'public',
            'private',
        ];
    }

    /**
     * @suppress PhanUndeclaredConstant
     */
    private function parseFile(string $file): void
    {
        $code = (string) \file_get_contents(ASYMMETRIC_VISIBILITY_TEST_FILE_DIR . '/' . $file . '.php');
        Analysis::parseNodeInContext(
            $this->code_base,
            new Context(),
            \ast\parse_code($code, Config::AST_VERSION)
        );
    }

    private function getClassProperty(Clazz $clazz, string $name): Property
    {
        return $clazz->getPropertyByName($this->code_base, $name);
    }
}
