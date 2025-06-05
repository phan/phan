<?php declare(strict_types=1);

namespace Phan\Tests\Language;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Property;
use Phan\Language\Element\Method;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\VoidType;
use Phan\Language\UnionType;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Tests\BaseTest;
use ast\Node;

class PropertyHookTest extends BaseTest {
    
    /**
     * Test basic property hook functionality
     */
    public function testBasicPropertyHooks(): void {
        $code_base = new CodeBase([], [], [], [], []);
        $context = new Context();
        
        // Create a test class
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\TestClass');
        $class = new Clazz($context, 'TestClass', UnionType::empty(), 0, $class_fqsen);
        $code_base->addClass($class);
        
        // Create a property with hooks
        $property_fqsen = FullyQualifiedPropertyName::make($class_fqsen, 'testProperty');
        $property = new Property(
            $context,
            'testProperty',
            StringType::instance(false)->asRealUnionType(),
            0,
            $property_fqsen,
            StringType::instance(false)->asRealUnionType()
        );
        
        // Create a get hook
        $get_method = new Method(
            $context,
            '__get_testProperty',
            StringType::instance(false)->asRealUnionType(),
            0,
            FullyQualifiedMethodName::make($class_fqsen, '__get_testProperty'),
            [] // empty parameter list
        );
        $get_method->setRealReturnType(StringType::instance(false)->asRealUnionType());
        $property->setGetHook($get_method);
        
        // Test that property has get hook
        $this->assertTrue($property->hasGetHook());
        $this->assertFalse($property->hasSetHook());
        $this->assertSame($get_method, $property->getGetHook());
    }
    
    /**
     * Test property with both get and set hooks
     */
    public function testGetAndSetHooks(): void {
        $code_base = new CodeBase([], [], [], [], []);
        $context = new Context();
        
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\TestClass');
        $class = new Clazz($context, 'TestClass', UnionType::empty(), 0, $class_fqsen);
        
        $property_fqsen = FullyQualifiedPropertyName::make($class_fqsen, 'price');
        $property = new Property(
            $context,
            'price',
            FloatType::instance(false)->asRealUnionType(),
            0,
            $property_fqsen,
            FloatType::instance(false)->asRealUnionType()
        );
        
        // Create get hook
        $get_method = new Method(
            $context,
            '__get_price',
            FloatType::instance(false)->asRealUnionType(),
            0,
            FullyQualifiedMethodName::make($class_fqsen, '__get_price'),
            [] // empty parameter list
        );
        $get_method->setRealReturnType(FloatType::instance(false)->asRealUnionType());
        $property->setGetHook($get_method);
        
        // Create set hook with value parameter
        $value_param = new \Phan\Language\Element\Parameter(
            $context,
            'value',
            FloatType::instance(false)->asRealUnionType(),
            0
        );
        $set_method = new Method(
            $context,
            '__set_price',
            VoidType::instance(false)->asRealUnionType(),
            0,
            FullyQualifiedMethodName::make($class_fqsen, '__set_price'),
            [$value_param] // set hook has one parameter
        );
        $set_method->setRealReturnType(VoidType::instance(false)->asRealUnionType());
        $property->setSetHook($set_method);
        
        // Test that property has both hooks
        $this->assertTrue($property->hasGetHook());
        $this->assertTrue($property->hasSetHook());
        $this->assertSame($get_method, $property->getGetHook());
        $this->assertSame($set_method, $property->getSetHook());
    }
    
    /**
     * Test virtual property (no backing storage)
     */
    public function testVirtualProperty(): void {
        $code_base = new CodeBase([], [], [], [], []);
        $context = new Context();
        
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\TestClass');
        $property_fqsen = FullyQualifiedPropertyName::make($class_fqsen, 'virtualProp');
        
        $property = new Property(
            $context,
            'virtualProp',
            UnionType::empty(),
            0,
            $property_fqsen,
            UnionType::empty()
        );
        
        // Set as virtual
        $property->setIsVirtual(true);
        
        $this->assertTrue($property->isVirtual());
    }
    
    /**
     * Test property reference restrictions with set hook
     */
    public function testCanBeUsedByReference(): void {
        $code_base = new CodeBase([], [], [], [], []);
        $context = new Context();
        
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\TestClass');
        $property_fqsen = FullyQualifiedPropertyName::make($class_fqsen, 'prop');
        
        $property = new Property(
            $context,
            'prop',
            UnionType::empty(),
            0,
            $property_fqsen,
            UnionType::empty()
        );
        
        // Without set hook, can be used by reference
        $this->assertTrue($property->canBeUsedByReference());
        
        // Add set hook
        $value_param = new \Phan\Language\Element\Parameter(
            $context,
            'value',
            UnionType::empty(),
            0
        );
        $set_method = new Method(
            $context,
            '__set_prop',
            VoidType::instance(false)->asRealUnionType(),
            0,
            FullyQualifiedMethodName::make($class_fqsen, '__set_prop'),
            [$value_param]
        );
        $set_method->setRealReturnType(VoidType::instance(false)->asRealUnionType());
        $property->setSetHook($set_method);
        
        // With set hook, cannot be used by reference
        $this->assertFalse($property->canBeUsedByReference());
    }
    
    /**
     * Test getUnionTypeWithHook returns hook return type
     */
    public function testGetUnionTypeWithHook(): void {
        $code_base = new CodeBase([], [], [], [], []);
        $context = new Context();
        
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\TestClass');
        $property_fqsen = FullyQualifiedPropertyName::make($class_fqsen, 'computed');
        
        // Property declared as string
        $property = new Property(
            $context,
            'computed',
            StringType::instance(false)->asRealUnionType(),
            0,
            $property_fqsen,
            StringType::instance(false)->asRealUnionType()
        );
        
        // Get hook returns int
        $get_method = new Method(
            $context,
            '__get_computed',
            IntType::instance(false)->asRealUnionType(),
            0,
            FullyQualifiedMethodName::make($class_fqsen, '__get_computed'),
            [] // empty parameter list
        );
        $get_method->setRealReturnType(IntType::instance(false)->asRealUnionType());
        $property->setGetHook($get_method);
        
        // getUnionTypeWithHook should return the hook's return type
        $hook_type = $property->getUnionTypeWithHook();
        $this->assertTrue($hook_type->hasType(IntType::instance(false)));
        $this->assertFalse($hook_type->hasType(StringType::instance(false)));
    }
    
    /**
     * Test backing value usage tracking
     */
    public function testBackingValueUsage(): void {
        $code_base = new CodeBase([], [], [], [], []);
        $context = new Context();
        
        $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString('\\TestClass');
        $property_fqsen = FullyQualifiedPropertyName::make($class_fqsen, 'prop');
        
        $property = new Property(
            $context,
            'prop',
            UnionType::empty(),
            0,
            $property_fqsen,
            UnionType::empty()
        );
        
        // Test default
        $this->assertFalse($property->getUsesBackingValue());
        
        // Set uses backing value
        $property->setUsesBackingValue(true);
        $this->assertTrue($property->getUsesBackingValue());
        
        // When using backing value, property should not be virtual
        $property->setIsVirtual(false);
        $this->assertFalse($property->isVirtual());
    }
}