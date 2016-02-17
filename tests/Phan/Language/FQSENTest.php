<?php declare(strict_types=1);

namespace Phan\Tests\Language;

use \Phan\Language\Context;
use \Phan\Language\FQSEN;
use \Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use \Phan\Language\FQSEN\FullyQualifiedClassName;
use \Phan\Language\FQSEN\FullyQualifiedConstantName;
use \Phan\Language\FQSEN\FullyQualifiedFunctionName;
use \Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use \Phan\Language\FQSEN\FullyQualifiedMethodName;
use \Phan\Language\FQSEN\FullyQualifiedPropertyName;

class FQSENTest extends \PHPUnit_Framework_TestCase {

    /** @var Context|null */
    protected $context = null;

    protected function setUp() {
        $this->context = new Context;
    }

    protected function tearDown() {
        $this->context = null;
    }

    public function testFullyQualifiedClassName() {
        $this->assertFQSENEqual(
            FullyQualifiedClassName::make('Name\\Space', 'A'),
            '\\name\\space\\a'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassName::make('', 'A'),
            '\\a'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassName::fromFullyQualifiedString('A'),
            '\\a'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassName::fromFullyQualifiedString(
                '\\Name\\Space\\A'
            ), '\\name\\space\\a'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassName::fromFullyQualifiedString(
                '\\Namespace\\A,1'
            ), '\\namespace\\a,1'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassName::fromStringInContext(
                '\\Namespace\\A', $this->context
            ), '\\namespace\\a'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassName::fromStringInContext(
                'A', $this->context
            ), '\\a'
        );
    }

    public function testFullyQualifiedMethodName() {
        $this->assertFQSENEqual(
            FullyQualifiedMethodName::make(
                FullyQualifiedClassName::make('\\Name\\Space', 'a'),
                'f'
            ), '\\name\\space\\a::f'
        );

        $this->assertFQSENEqual(
            FullyQualifiedMethodName::fromFullyQualifiedString(
                '\\Name\\a::f'
            ), '\\name\\a::f'
        );

        $this->assertFQSENEqual(
            FullyQualifiedMethodName::fromFullyQualifiedString(
                'Name\\a::f'
            ), '\\name\\a::f'
        );

        $this->assertFQSENEqual(
            FullyQualifiedMethodName::fromFullyQualifiedString(
                '\\Name\\Space\\a::f,2'
            ), '\\name\\space\\a::f,2'
        );

        $this->assertFQSENEqual(
            FullyQualifiedMethodName::fromFullyQualifiedString(
                '\\Name\\Space\\a,1::f,2'
            ), '\\name\\space\\a,1::f,2'
        );

        $this->assertFQSENEqual(
            FullyQualifiedMethodName::fromStringInContext(
                'a::methodName', $this->context
            ), '\\a::methodname'
        );
    }

    public function testFullyQualifiedPropertyName() {
        $this->assertFQSENEqual(
            FullyQualifiedPropertyName::make(
                FullyQualifiedClassName::make('\\Name\\Space', 'a'), 'p'
            ), '\\name\\space\\a::p'
        );

        $this->assertFQSENEqual(
            FullyQualifiedPropertyName::fromFullyQualifiedString(
                '\\Name\\a::p'
            ), '\\name\\a::p'
        );

        $this->assertFQSENEqual(
            FullyQualifiedPropertyName::fromFullyQualifiedString(
                'Name\\a::p'
            ), '\\name\\a::p'
        );

        $this->assertFQSENEqual(
            FullyQualifiedPropertyName::fromFullyQualifiedString(
                '\\Name\\Space\\a::p,2'
            ), '\\name\\space\\a::p,2'
        );

        $this->assertFQSENEqual(
            FullyQualifiedPropertyName::fromFullyQualifiedString(
                '\\Name\\Space\\a,1::p,2'
            ), '\\name\\space\\a,1::p,2'
        );

        $this->assertFQSENEqual(
            FullyQualifiedPropertyName::fromStringInContext(
                'a::p', $this->context
            ), '\\a::p'
        );
    }

    public function testFullyQualifiedClassConstantName() {
        $this->assertFQSENEqual(
            FullyQualifiedClassConstantName::make(
                FullyQualifiedClassName::make('\\Name\\Space', 'a'), 'c'
            ), '\\name\\space\\a::c'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassConstantName::fromFullyQualifiedString(
                '\\Name\\a::c'
            ), '\\name\\a::c'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassConstantName::fromFullyQualifiedString(
                'Name\\a::c'
            ), '\\name\\a::c'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassConstantName::fromFullyQualifiedString(
                '\\Name\\Space\\a::c,2'
            ), '\\name\\space\\a::c,2'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassConstantName::fromFullyQualifiedString(
                '\\Name\\Space\\a,1::c,2'
            ), '\\name\\space\\a,1::c,2'
        );

        $this->assertFQSENEqual(
            FullyQualifiedClassConstantName::fromStringInContext(
                'a::methodName', $this->context
            ), '\\a::methodName'
        );
    }

    public function testFullyQualifiedGlobalConstantName() {
        $this->assertFQSENEqual(
            FullyQualifiedGlobalConstantName::make(
                '\\Name\\Space', 'c'
            ), '\\name\\space\\c'
        );

        $this->assertFQSENEqual(
            FullyQualifiedGlobalConstantName::make(
                '', 'c'
            ), '\\c'
        );

        $this->assertFQSENEqual(
            FullyQualifiedGlobalConstantName::make(
                '', 'c'
            ), '\\c'
        );

        $this->assertFQSENEqual(
            FullyQualifiedGlobalConstantName::fromFullyQualifiedString('\\c'),
            '\\c'
        );

        $this->assertFQSENEqual(
            FullyQualifiedGlobalConstantName::fromStringInContext('c', $this->context),
            '\\c'
        );
    }

    public function testFullyQualifiedFunctionName() {
        $this->assertFQSENEqual(
            FullyQualifiedFunctionName::make(
                '\\Name\\Space', 'g'
            ), '\\name\\space\\g'
        );

        $this->assertFQSENEqual(
            FullyQualifiedFunctionName::make(
                '', 'g'
            ), '\\g'
        );

        $this->assertFQSENEqual(
            FullyQualifiedGlobalConstantName::make(
                '', 'g'
            ), '\\g'
        );

        $this->assertFQSENEqual(
            FullyQualifiedFunctionName::fromFullyQualifiedString('\\g'),
            '\\g'
        );

        $this->assertFQSENEqual(
            FullyQualifiedFunctionName::fromStringInContext('g', $this->context),
            '\\g'
        );
    }

    /**
     * Asserts that a given FQSEN produces the given string
     */
    public function assertFQSENEqual(
        FQSEN $fqsen,
        string $string
    ) {
        $this->assertEquals($string, (string)$fqsen);
    }

}


