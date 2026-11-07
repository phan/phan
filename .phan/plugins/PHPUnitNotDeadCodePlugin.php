<?php

declare(strict_types=1);

use ast\Node;
use Phan\AST\UnionTypeVisitor;
use Phan\Config;
use Phan\Language\Element\Attribute;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type;
use Phan\PluginV3;
use Phan\PluginV3\PluginAwarePostAnalysisVisitor;
use Phan\PluginV3\PostAnalyzeNodeCapability;

/**
 * Mark all phpunit test cases as used for dead code detection during Phan's self-analysis.
 *
 * Implements the following capabilities
 * (This choice of capability makes this plugin efficiently analyze only classes that are in the analyzed file list)
 *
 * - public static function getPostAnalyzeNodeVisitorClassName() : string
 *   Returns the name of a class extending PluginAwarePostAnalysisVisitor, which will be used to analyze nodes in the analysis phase.
 *   If the PluginAwarePostAnalysisVisitor subclass has an instance property called parent_node_list,
 *   Phan will automatically set that property to the list of parent nodes (The nodes deepest in the AST are at the end of the list)
 *   (implement \Phan\PluginV3\PostAnalyzeNodeCapability)
 */
class PHPUnitNotDeadCodePlugin extends PluginV3 implements PostAnalyzeNodeCapability
{
    /**
     * @override
     */
    public static function getPostAnalyzeNodeVisitorClassName(): string
    {
        return PHPUnitNotDeadPluginVisitor::class;
    }
}

/**
 * This visitor visits classes (After all class/method definitions are parsed and analyzed)
 * and, for subclasses of PHPUnit test cases,
 * marks the phpunit test cases, (at)dataProviders, and special PHPUnit subclass properties as being referenced (i.e. not dead code)
 */
class PHPUnitNotDeadPluginVisitor extends PluginAwarePostAnalysisVisitor
{
    /** @var FullyQualifiedClassName the class FQSEN for the base class of all PHPUnit tests */
    private static $phpunit_test_case_fqsen;

    /** @var Type the type of the base class of all PHPUnit tests */
    private static $phpunit_test_case_type;

    /** @var bool did this plugin already warn that TestCase was missing? */
    private static $did_warn_missing_class = false;

    /** The attribute marking a public method as a test (PHPUnit 10+), like the `@test` annotation */
    private const TEST_ATTRIBUTE = '\PHPUnit\Framework\Attributes\Test';

    /** The attribute naming a data provider method of the same class (PHPUnit 10+), like the `@dataProvider` annotation */
    private const DATA_PROVIDER_ATTRIBUTE = '\PHPUnit\Framework\Attributes\DataProvider';

    /** The attribute naming a data provider method of another class (PHPUnit 10+) */
    private const DATA_PROVIDER_EXTERNAL_ATTRIBUTE = '\PHPUnit\Framework\Attributes\DataProviderExternal';

    /**
     * PHPUnit calls the methods with these attributes before or after the tests of a test case (PHPUnit 10+).
     * The methods can have any visibility. (Keys are lowercase)
     */
    private const HOOK_ATTRIBUTES = [
        '\phpunit\framework\attributes\before' => true,
        '\phpunit\framework\attributes\after' => true,
        '\phpunit\framework\attributes\beforeclass' => true,
        '\phpunit\framework\attributes\afterclass' => true,
        '\phpunit\framework\attributes\precondition' => true,
        '\phpunit\framework\attributes\postcondition' => true,
    ];

    /** The annotations corresponding to HOOK_ATTRIBUTES */
    private const HOOK_ANNOTATION_REGEX = '/@(before|after|beforeClass|afterClass|preCondition|postCondition)\b/';

    /**
     * This is called after the parse phase is completely finished, so $this->code_base contains all class definitions
     * @override
     * @unused-param $node
     */
    public function visitClass(Node $node): void
    {
        if (!Config::get_track_references()) {
            return;
        }
        $code_base = $this->code_base;
        if (!$code_base->hasClassWithFQSEN(self::$phpunit_test_case_fqsen)) {
            if (!self::$did_warn_missing_class) {
                // @phan-suppress-next-line PhanPluginRemoveDebugCall
                fprintf(STDERR, "Using plugin %s but could not find PHPUnit\Framework\TestCase\n", self::class);
                self::$did_warn_missing_class = true;
            }
            return;
        }
        // This assumes PreOrderAnalysisVisitor->visitClass is called first.
        $context = $this->context;
        $class = $context->getClassInScope($code_base);
        if (!$class->getFQSEN()->asType()->asExpandedTypes($code_base)->hasType(self::$phpunit_test_case_type)) {
            // This isn't a phpunit test case.
            return;
        }

        // Mark subclasses of TestCase as referenced.
        // Clazz::addReference() ignores a reference made from the scope of the class itself (#2655), and $context is in that scope,
        // so record the reference from the global scope: the test runner instantiates the test case from outside of the class (#5629).
        $class->addReference($context->withScope(new GlobalScope()));
        // Mark all test cases as referenced
        foreach ($class->getMethodMap($code_base) as $method) {
            if (static::isTestCase($method)) {
                $method->addReference($context);
                $this->markDataProvidersAsReferenced($class, $method);
            } elseif (static::isHookMethod($method)) {
                // PHPUnit calls this before or after the tests
                $method->addReference($context);
            }
        }
        // https://phpunit.de/manual/current/en/fixtures.html (PHPUnit framework checks for this override)
        if ($class->hasPropertyWithName($code_base, 'backupStaticAttributesBlacklist')) {
            $property = $class->getPropertyByName($code_base, 'backupStaticAttributesBlacklist');
            $property->addReference($context);
            $property->setHasReadReference();
        }
    }

    /**
     * This regex contains a single pattern, which matches a valid PHP identifier.
     * (e.g. for variable names, magic property names, etc.
     * This does not allow backslashes.
     */
    private const WORD_REGEX = '([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)';

    /**
     * Marks all data provider methods as being referenced
     *
     * @param Method $method the Method representing a unit test in a test case subclass
     */
    private function markDataProvidersAsReferenced(Clazz $class, Method $method): void
    {
        // A test can have several `@dataProvider` annotations
        if (preg_match_all('/@dataProvider\s+' . self::WORD_REGEX . '/', $method->getDocComment() ?? '', $matches)) {
            foreach ($matches[1] as $data_provider_name) {
                $this->markMethodAsReferenced($class, $data_provider_name);
            }
        }
        foreach ($method->getAttributeList() as $attribute) {
            $attribute_name = $attribute->getFQSEN()->__toString();
            if (strcasecmp($attribute_name, self::DATA_PROVIDER_ATTRIBUTE) === 0) {
                // #[DataProvider('providerName')]
                $data_provider_name = self::getAttributeArgument($attribute, 0, 'methodName');
                if (is_string($data_provider_name)) {
                    $this->markMethodAsReferenced($class, $data_provider_name);
                }
            } elseif (strcasecmp($attribute_name, self::DATA_PROVIDER_EXTERNAL_ATTRIBUTE) === 0) {
                // #[DataProviderExternal(ProviderClass::class, 'providerName')]
                $data_provider_name = self::getAttributeArgument($attribute, 1, 'methodName');
                $data_provider_class = $this->getClassForAttributeArgument($method, self::getAttributeArgument($attribute, 0, 'className'));
                if (is_string($data_provider_name) && $data_provider_class) {
                    $this->markMethodAsReferenced($data_provider_class, $data_provider_name);
                }
            }
        }
    }

    private function markMethodAsReferenced(Clazz $class, string $method_name): void
    {
        if ($class->hasMethodWithName($this->code_base, $method_name, true)) {
            $class->getMethodByName($this->code_base, $method_name)->addReference($this->context);
        }
    }

    /**
     * @param Node|string|int|float|null $argument an argument of an attribute, from getAttributeArgument()
     * @return ?Clazz the class named by `SomeClass::class` or by a class name string, if it is declared
     */
    private function getClassForAttributeArgument(Method $method, Node|string|int|float|null $argument): ?Clazz
    {
        try {
            if ($argument instanceof Node) {
                if ($argument->kind !== \ast\AST_CLASS_NAME) {
                    return null;
                }
                $class_name = UnionTypeVisitor::unionTypeFromClassNode($this->code_base, $method->getContext(), $argument->children['class'])->__toString();
            } elseif (is_string($argument)) {
                $class_name = '\\' . ltrim($argument, '\\');
            } else {
                return null;
            }
            $class_fqsen = FullyQualifiedClassName::fromFullyQualifiedString($class_name);
        } catch (Exception) {
            // Not a valid class name
            return null;
        }
        if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
            return null;
        }
        return $this->code_base->getClassByFQSEN($class_fqsen);
    }

    /**
     * @return Node|string|int|float|null the positional argument $i of the attribute, or its named argument $name
     */
    private static function getAttributeArgument(Attribute $attribute, int $i, string $name): Node|string|int|float|null
    {
        $args = $attribute->getArgs();
        if (!$args) {
            return null;
        }
        foreach ($args->children as $j => $argument) {
            if ($argument instanceof Node && $argument->kind === \ast\AST_NAMED_ARG) {
                if ($argument->children['name'] === $name) {
                    return $argument->children['expr'];
                }
            } elseif ($j === $i) {
                return $argument;
            }
        }
        return null;
    }

    /**
     * @return bool true if $method is a PHPUnit test case
     */
    protected static function isTestCase(Method $method): bool
    {
        if (!$method->isPublic()) {
            return false;
        }
        if (preg_match('@^test@i', $method->getName())) {
            return true;
        }
        if (preg_match('/@test\b/', $method->getDocComment() ?? '')) {
            return true;
        }
        return self::hasAttribute($method, self::TEST_ATTRIBUTE);
    }

    /**
     * @return bool true if PHPUnit calls $method before or after the tests of the test case
     */
    protected static function isHookMethod(Method $method): bool
    {
        foreach ($method->getAttributeList() as $attribute) {
            if (isset(self::HOOK_ATTRIBUTES[strtolower($attribute->getFQSEN()->__toString())])) {
                return true;
            }
        }
        return preg_match(self::HOOK_ANNOTATION_REGEX, $method->getDocComment() ?? '') > 0;
    }

    private static function hasAttribute(Method $method, string $attribute_class_name): bool
    {
        foreach ($method->getAttributeList() as $attribute) {
            if (strcasecmp($attribute->getFQSEN()->__toString(), $attribute_class_name) === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Static initializer for this plugin - Gets called below before any methods can be used
     * @suppress PhanThrowTypeAbsentForCall this FQSEN is valid
     */
    public static function init(): void
    {
        $fqsen = FullyQualifiedClassName::make('\\PHPUnit\Framework', 'TestCase');
        self::$phpunit_test_case_fqsen = $fqsen;
        self::$phpunit_test_case_type = $fqsen->asType();
    }
}
PHPUnitNotDeadPluginVisitor::init();

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new PHPUnitNotDeadCodePlugin();
