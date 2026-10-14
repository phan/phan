<?php

declare(strict_types=1);

namespace Phan\Tests\Language\Element;

use Closure;
use Phan\Analysis\CloneReplaySnapshot;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Language\Context;
use Phan\Language\Element\Comment\NullComment;
use Phan\Language\Element\Flags;
use Phan\Language\Element\Method;
use Phan\Language\Element\Parameter;
use Phan\Language\Element\Variable;
use Phan\Language\FileRef;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FutureUnionType;
use Phan\Language\Scope\FunctionLikeScope;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type\IntType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use Phan\Tests\CodeBaseAwareTestBase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests that every setter, in-place mutation and cache fill of the state owned by a Method, Parameter, Variable
 * or Scope increments its state version (TypedElement::getStateVersion()), and that the documented exceptions do not.
 *
 * @phan-file-suppress PhanAccessMethodInternal, PhanThrowTypeAbsentForCall, PhanPluginUnknownClosureReturnType
 */
final class StateVersionTest extends CodeBaseAwareTestBase
{
    /**
     * Prefixes of the names of public methods that the reflection sweep requires to be listed in the
     * "bumps" or "does not bump" lists below, so that a new setter cannot be added without deciding.
     */
    private const MUTATOR_PREFIX_REGEX = '/^(set|add|record|enable|disable|clear|inherit|ensure|mark|increment)/';

    /**
     * Public mutators of Method that intentionally do not increment the state version.
     */
    private const METHOD_NO_BUMP = [
        'incrementSuppressIssueCount' => 'the counts of inherited copies are never reported',
        'setCloneSource' => 'set by Clazz::addMethod() on every copy and reset by __clone()',
        'setEagerOrdinal' => 'set by CodeBase::addMethod() on every method it adds',
        'setIsOverride' => 'IS_OVERRIDE is in TypedElement::UNVERSIONED_PHAN_FLAGS',
        'setIsOverriddenByAnother' => 'IS_OVERRIDDEN_BY_ANOTHER is in UNVERSIONED_PHAN_FLAGS; the Set of overrides is shared with clones',
    ];

    /**
     * Public mutators of Parameter that intentionally do not increment the state version.
     */
    private const PARAMETER_NO_BUMP = [];

    /** @var bool */
    private $old_quick_mode;
    /** @var bool */
    private $old_force_tracking_references;

    public function setUp(): void
    {
        parent::setUp();
        $this->old_quick_mode = (bool)Config::getValue('quick_mode');
        $this->old_force_tracking_references = (bool)Config::getValue('force_tracking_references');
        // setNode() does nothing in quick mode, addReference() does nothing without reference tracking.
        Config::setValue('quick_mode', false);
        Config::setValue('force_tracking_references', true);
    }

    public function tearDown(): void
    {
        Config::setValue('quick_mode', $this->old_quick_mode);
        Config::setValue('force_tracking_references', $this->old_force_tracking_references);
        parent::tearDown();
    }

    private static function makeMethod(string $name = 'm', ?UnionType $type = null): Method
    {
        $fqsen = FullyQualifiedMethodName::make(
            FullyQualifiedClassName::fromFullyQualifiedString('\StateVersionTest\C'),
            $name
        );
        return new Method(
            (new Context())->withFile('src/state_version_test.php'),
            $name,
            $type ?? UnionType::empty(),
            0,
            $fqsen,
            []
        );
    }

    private static function makeParameter(string $name = 'x', ?UnionType $type = null): Parameter
    {
        return Parameter::create(
            (new Context())->withFile('src/state_version_test.php'),
            $name,
            $type ?? UnionType::empty(),
            0
        );
    }

    private static function intType(): UnionType
    {
        return IntType::instance(false)->asPHPDocUnionType();
    }

    /**
     * @return array<string,Closure(Method,CodeBase):void>
     * For each public mutator of Method that increments the state version, a call of it on a new method
     * that changes the method's state.
     */
    private static function methodBumps(): array
    {
        $fqsen = FullyQualifiedMethodName::fromFullyQualifiedString('\StateVersionTest\C::other');
        return [
            // TypedElement
            'setUnionType' => static fn(Method $m) => $m->setUnionType(self::intType()),
            'setFlags' => static fn(Method $m) => $m->setFlags(\ast\flags\MODIFIER_STATIC),
            'setPhanFlags' => static fn(Method $m) => $m->setPhanFlags($m->getPhanFlags() | Flags::IS_DEPRECATED),
            'enablePhanFlagBits' => static fn(Method $m) => $m->enablePhanFlagBits(Flags::IS_DEPRECATED),
            'disablePhanFlagBits' => static function (Method $m): void {
                $m->enablePhanFlagBits(Flags::IS_DEPRECATED);
                $before = $m->getStateVersion();
                $m->disablePhanFlagBits(Flags::IS_DEPRECATED);
                self::assertGreaterThan($before, $m->getStateVersion());
            },
            'setIsDeprecated' => static fn(Method $m) => $m->setIsDeprecated(true),
            'setSuppressIssueSet' => static fn(Method $m) => $m->setSuppressIssueSet(['PhanUnreferencedPublicMethod' => 0]),
            // AddressableElement
            'setFQSEN' => static fn(Method $m) => $m->setFQSEN($fqsen),
            'setIsNSInternal' => static fn(Method $m) => $m->setIsNSInternal(true),
            'addReference' => static fn(Method $m) => $m->addReference(new FileRef()),
            'setDocComment' => static fn(Method $m) => $m->setDocComment('/** doc */'),
            // ClassElement
            'setDefiningFQSEN' => static fn(Method $m) => $m->setDefiningFQSEN($fqsen),
            'setIsPHPDocAbstract' => static fn(Method $m) => $m->setIsPHPDocAbstract(true),
            'setIsOverrideIntended' => static fn(Method $m) => $m->setIsOverrideIntended(true),
            // HasAttributesTrait, ClosedScopeElement, Analyzable
            'setAttributeList' => static fn(Method $m) => $m->setAttributeList([]),
            'setInternalScope' => static fn(Method $m) => $m->setInternalScope(new FunctionLikeScope(new GlobalScope(), $fqsen)),
            'setNode' => static fn(Method $m) => $m->setNode(new \ast\Node(\ast\AST_METHOD, 0, [], 1)),
            // FunctionTrait
            'setNumberOfOptionalParameters' => static fn(Method $m) => $m->setNumberOfOptionalParameters(1),
            'setNumberOfRequiredParameters' => static fn(Method $m) => $m->setNumberOfRequiredParameters(1),
            'setIsReturnTypeUndefined' => static fn(Method $m) => $m->setIsReturnTypeUndefined(true),
            'setHasReturn' => static fn(Method $m) => $m->setHasReturn(true),
            'setHasYield' => static fn(Method $m) => $m->setHasYield(true),
            'setHasStaticVariable' => static fn(Method $m) => $m->setHasStaticVariable(true),
            'setParameterList' => static fn(Method $m) => $m->setParameterList([self::makeParameter()]),
            'setRealParameterList' => static fn(Method $m) => $m->setRealParameterList([self::makeParameter()]),
            'inheritRealParameterDefaults' => static fn(Method $m) => $m->inheritRealParameterDefaults(),
            'setRealReturnType' => static fn(Method $m) => $m->setRealReturnType(self::intType()),
            'clearParameterList' => static fn(Method $m) => $m->clearParameterList(),
            'setPHPDocParameterTypeMap' => static fn(Method $m) => $m->setPHPDocParameterTypeMap(['x' => self::intType()]),
            'recordOutputReferenceParamName' => static fn(Method $m) => $m->recordOutputReferenceParamName('x'),
            'setPHPDocReturnType' => static fn(Method $m) => $m->setPHPDocReturnType(self::intType()),
            'setDependentReturnTypeClosure' => static fn(Method $m) => $m->setDependentReturnTypeClosure(static fn(): UnionType => UnionType::empty()),
            'setConditionalReturnType' => static fn(Method $m) => $m->setConditionalReturnType(null),
            'setFunctionCallAnalyzer' => static fn(Method $m) => $m->setFunctionCallAnalyzer(static function (): void {
            }),
            'addFunctionCallAnalyzer' => static fn(Method $m) => $m->addFunctionCallAnalyzer(static function (): void {
            }, null),
            'setComment' => static fn(Method $m) => $m->setComment(NullComment::instance()),
            'ensureScopeInitialized' => static fn(Method $m, CodeBase $code_base) => $m->ensureScopeInitialized($code_base),
            'setIsPure' => static fn(Method $m) => $m->setIsPure(),
            'setHasTentativeReturnType' => static fn(Method $m) => $m->setHasTentativeReturnType(),
            'setOriginalReturnType' => static fn(Method $m) => $m->setOriginalReturnType(),
            'recordHasMandatoryPHPDocParamAtOffset' => static fn(Method $m) => $m->recordHasMandatoryPHPDocParamAtOffset(0),
            'setHasNoNamedArguments' => static fn(Method $m) => $m->setHasNoNamedArguments(),
            // Method
            'setIsFromPHPDoc' => static fn(Method $m) => $m->setIsFromPHPDoc(true),
            'ensureClonesReturnType' => static fn(Method $m) => $m->ensureClonesReturnType(self::makeMethod('original')),
            'setInheritedThrowsUnionType' => static fn(Method $m) => $m->setInheritedThrowsUnionType(self::intType()),
            'recordStaticPropertyModification' => static fn(Method $m) => $m->recordStaticPropertyModification(
                FullyQualifiedClassName::fromFullyQualifiedString('\StateVersionTest\C'),
                'prop',
                self::intType(),
                false
            ),
        ];
    }

    /**
     * @return array<string,Closure(Method,CodeBase):mixed>
     * Mutators and cache fills of Method that the reflection sweep does not look for, but that must increment the version.
     */
    private static function otherMethodBumps(): array
    {
        $fqsen = FullyQualifiedMethodName::fromFullyQualifiedString('\StateVersionTest\C::other');
        $snapshot = new CloneReplaySnapshot([], null, [], false, null);
        return [
            'appendParameter' => static fn(Method $m) => $m->appendParameter(self::makeParameter()),
            'cloneParameterList' => static fn(Method $m) => $m->cloneParameterList(),
            'copyReferencesFrom' => static fn(Method $m) => $m->copyReferencesFrom(self::makeMethod('other')),
            'hydrate' => static fn(Method $m, CodeBase $code_base) => $m->hydrate($code_base),
            'replayDeclarationAnalysis' => static fn(Method $m, CodeBase $code_base) => $m->replayDeclarationAnalysis($code_base, $snapshot),
            'applyCloneReplaySnapshot' => static fn(Method $m) => $m->applyCloneReplaySnapshot($snapshot),
            'analyzeReturnTypes' => static function (Method $m, CodeBase $code_base): void {
                try {
                    $m->analyzeReturnTypes($code_base);
                } catch (CodeBaseException) {
                    // The class of the method does not exist in this test; the flag was set before the lookup.
                }
            },
            'needsRecursiveAnalysis (cache fill)' => static fn(Method $m) => $m->needsRecursiveAnalysis(),
            'asFunctionLikeDeclarationType (cache fill)' => static fn(Method $m) => $m->asFunctionLikeDeclarationType(),
            'getReturnTypeAsGeneratorTemplateType (cache fill)' => static fn(Method $m) => $m->getReturnTypeAsGeneratorTemplateType(),
            'getOriginalReturnType (cache fill)' => static fn(Method $m) => $m->getOriginalReturnType(),
            'getDeprecationReason (memoize fill)' => static fn(Method $m) => $m->getDeprecationReason(),
            'addReference (subclass override)' => static fn(Method $m) => $m->addReference(
                (new Context())->withScope(new FunctionLikeScope(new GlobalScope(), $fqsen))
            ),
        ];
    }

    /**
     * @return array<string,Closure(Parameter,CodeBase):mixed>
     */
    private static function parameterBumps(): array
    {
        return [
            // UnaddressableTypedElement
            'setUnionType' => static fn(Parameter $p) => $p->setUnionType(self::intType()),
            'setFlags' => static fn(Parameter $p) => $p->setFlags(\ast\flags\PARAM_REF),
            'setPhanFlags' => static fn(Parameter $p) => $p->setPhanFlags(Flags::IS_DEPRECATED),
            'enablePhanFlagBits' => static fn(Parameter $p) => $p->enablePhanFlagBits(Flags::IS_DEPRECATED),
            'disablePhanFlagBits' => static function (Parameter $p): void {
                $p->enablePhanFlagBits(Flags::IS_DEPRECATED);
                $before = $p->getStateVersion();
                $p->disablePhanFlagBits(Flags::IS_DEPRECATED);
                self::assertGreaterThan($before, $p->getStateVersion());
            },
            // HasAttributesTrait
            'setAttributeList' => static fn(Parameter $p) => $p->setAttributeList([]),
            // Parameter
            'setDefaultValueType' => static fn(Parameter $p) => $p->setDefaultValueType(self::intType()),
            'setDefaultValueFutureType' => static fn(Parameter $p, CodeBase $code_base) => $p->setDefaultValueFutureType(
                new FutureUnionType($code_base, new Context(), 1)
            ),
            'setDefaultValueRepresentation' => static fn(Parameter $p) => $p->setDefaultValueRepresentation('1'),
            'setDefaultValue' => static fn(Parameter $p) => $p->setDefaultValue(1),
            'addUnionType' => static fn(Parameter $p) => $p->addUnionType(self::intType()),
            'addType' => static fn(Parameter $p) => $p->addType(IntType::instance(false)),
            'setIsOutputReference' => static fn(Parameter $p) => $p->setIsOutputReference(),
            'setIsIgnoredReference' => static fn(Parameter $p) => $p->setIsIgnoredReference(),
            'setHasNoNamedArguments' => static fn(Parameter $p) => $p->setHasNoNamedArguments(),
            'setShouldWarnIfProvided' => static fn(Parameter $p) => $p->setShouldWarnIfProvided(true),
            'setCloneReplayState' => static fn(Parameter $p) => $p->setCloneReplayState($p->getCloneReplayState()),
        ];
    }

    /**
     * @return array<string,Closure(Parameter,CodeBase):mixed>
     * Mutators and cache fills of Parameter that the reflection sweep does not look for, but that must increment the version.
     */
    private static function otherParameterBumps(): array
    {
        return [
            'copyDefaultValueFrom' => static fn(Parameter $p) => $p->copyDefaultValueFrom(self::makeParameter('y')),
            'handleDefaultValueOfNull' => static function (Parameter $p, CodeBase $code_base): void {
                $p->setUnionType(self::intType());
                $p->setDefaultValueType(\Phan\Language\Type\NullType::instance(false)->asPHPDocUnionType());
                $before = $p->getStateVersion();
                $p->handleDefaultValueOfNull($code_base, new Context());
                self::assertGreaterThan($before, $p->getStateVersion());
            },
            'getDefaultValueType (future type fill)' => static function (Parameter $p, CodeBase $code_base): void {
                $p->setDefaultValueFutureType(new FutureUnionType($code_base, new Context(), 1));
                $before = $p->getStateVersion();
                $p->getDefaultValueType();
                self::assertGreaterThan($before, $p->getStateVersion());
            },
            'getDefaultValueLiteralType (future type fill)' => static function (Parameter $p, CodeBase $code_base): void {
                $p->setDefaultValueFutureType(new FutureUnionType($code_base, new Context(), 1));
                $before = $p->getStateVersion();
                $p->getDefaultValueLiteralType();
                self::assertGreaterThan($before, $p->getStateVersion());
            },
        ];
    }

    /**
     * @param Closure(Method,CodeBase):mixed $mutator
     * @dataProvider methodBumpProvider
     */
    public function testMethodMutatorBumps(string $name, Closure $mutator): void
    {
        $method = self::makeMethod();
        $before = $method->getStateVersion();
        $mutator($method, $this->code_base);
        $this->assertGreaterThan($before, $method->getStateVersion(), "Method::$name() should increment the state version");
    }

    /**
     * @return list<array{0:string,1:Closure}>
     */
    public static function methodBumpProvider(): array
    {
        $result = [];
        foreach (self::methodBumps() + self::otherMethodBumps() as $name => $closure) {
            $result[] = [$name, $closure];
        }
        return $result;
    }

    /**
     * @param Closure(Parameter,CodeBase):mixed $mutator
     * @dataProvider parameterBumpProvider
     */
    public function testParameterMutatorBumps(string $name, Closure $mutator): void
    {
        $parameter = self::makeParameter();
        $before = $parameter->getStateVersion();
        $mutator($parameter, $this->code_base);
        $this->assertGreaterThan($before, $parameter->getStateVersion(), "Parameter::$name() should increment the state version");
    }

    /**
     * @return list<array{0:string,1:Closure}>
     */
    public static function parameterBumpProvider(): array
    {
        $result = [];
        foreach (self::parameterBumps() + self::otherParameterBumps() as $name => $closure) {
            $result[] = [$name, $closure];
        }
        return $result;
    }

    public function testMethodExceptionsDoNotBump(): void
    {
        $method = self::makeMethod();
        $method->setSuppressIssueSet(['PhanUnreferencedPublicMethod' => 0]);
        $fqsen = FullyQualifiedMethodName::fromFullyQualifiedString('\StateVersionTest\D::m');
        $checks = [
            'incrementSuppressIssueCount' => static fn() => $method->incrementSuppressIssueCount('PhanUnreferencedPublicMethod'),
            'setCloneSource' => static fn() => $method->setCloneSource(self::makeMethod('source')),
            'setEagerOrdinal' => static fn() => $method->setEagerOrdinal(42),
            'setIsOverride' => static fn() => $method->setIsOverride(true),
            'setIsOverriddenByAnother' => static fn() => $method->setIsOverriddenByAnother(true, $fqsen),
            'withStaticExpandedToDeclaringClass (returns a value)' => static fn() => $method->withStaticExpandedToDeclaringClass(UnionType::empty()),
        ];
        $this->assertSame(\array_keys(self::METHOD_NO_BUMP), \array_slice(\array_keys($checks), 0, \count(self::METHOD_NO_BUMP)));
        foreach ($checks as $name => $check) {
            $before = $method->getStateVersion();
            $check();
            $this->assertSame($before, $method->getStateVersion(), "Method::$name() should not increment the state version");
        }
        $this->assertSame(42, $method->getEagerOrdinal());
        $this->assertTrue($method->isOverride());
        $this->assertTrue($method->isOverriddenByAnother());

        // Only the masked bits are ignored: a phan flag write that also changes another bit increments the version.
        $before = $method->getStateVersion();
        $method->setPhanFlags(($method->getPhanFlags() & ~(Flags::IS_OVERRIDE | Flags::IS_OVERRIDDEN_BY_ANOTHER)) | Flags::IS_DEPRECATED);
        $this->assertGreaterThan($before, $method->getStateVersion());

        // Writes that do not change anything do not increment it.
        $method->recordHasMandatoryPHPDocParamAtOffset(3);
        $before = $method->getStateVersion();
        $method->enablePhanFlagBits(Flags::IS_DEPRECATED);
        $method->setIsDeprecated(true);
        $method->recordHasMandatoryPHPDocParamAtOffset(2);
        $method->recordStaticPropertyModification(
            FullyQualifiedClassName::fromFullyQualifiedString('\StateVersionTest\C'),
            'prop',
            UnionType::empty(),
            false
        );
        $this->assertSame($before, $method->getStateVersion());
    }

    public function testEnsureClonesReturnTypeBumpsOnlyWhenLinking(): void
    {
        $method = self::makeMethod();
        $before = $method->getStateVersion();
        // A method with a return type is not linked to.
        $method->ensureClonesReturnType(self::makeMethod('typed', self::intType()));
        $this->assertSame($before, $method->getStateVersion());
        $method->ensureClonesReturnType(self::makeMethod('untyped'));
        $this->assertGreaterThan($before, $method->getStateVersion());
        // Already linked: nothing changes.
        $before = $method->getStateVersion();
        $method->ensureClonesReturnType(self::makeMethod('untyped2'));
        $this->assertSame($before, $method->getStateVersion());
    }

    public function testCloneKeepsVersionAndWithMethodsDoNotBumpOriginal(): void
    {
        $method = self::makeMethod();
        $method->setUnionType(self::intType());
        $method->setHasReturn(true);
        $version = $method->getStateVersion();
        $clone = clone($method);
        $this->assertSame($version, $clone->getStateVersion());
        $clone->setHasYield(true);
        $this->assertSame($version, $method->getStateVersion());
        $this->assertGreaterThan($version, $clone->getStateVersion());

        $parameter = self::makeParameter();
        $parameter->setUnionType(self::intType());
        $version = $parameter->getStateVersion();
        $this->assertSame($version, (clone($parameter))->getStateVersion());
        $parameter->asNonVariadic();
        $parameter->cloneAsNonVariadic();
        $this->assertSame($version, $parameter->getStateVersion());

        $scope = new FunctionLikeScope(new GlobalScope(), FullyQualifiedMethodName::fromFullyQualifiedString('\StateVersionTest\C::m'));
        $scope->addVariable(new Variable(new Context(), 'a', UnionType::empty(), 0));
        $version = $scope->getStateVersion();
        $with = $scope->withVariable(new Variable(new Context(), 'b', UnionType::empty(), 0));
        $without = $scope->withUnsetVariable('a');
        $this->assertSame($version, $scope->getStateVersion());
        $this->assertGreaterThan($version, $with->getStateVersion());
        $this->assertGreaterThan($version, $without->getStateVersion());
    }

    public function testVariableMutatorsBump(): void
    {
        $checks = [
            'setUnionType' => static fn(Variable $v) => $v->setUnionType(self::intType()),
            'setFlags' => static fn(Variable $v) => $v->setFlags(1),
            'setPhanFlags' => static fn(Variable $v) => $v->setPhanFlags(Flags::IS_DEPRECATED),
            'enablePhanFlagBits' => static fn(Variable $v) => $v->enablePhanFlagBits(Flags::IS_DEPRECATED),
        ];
        foreach ($checks as $name => $check) {
            $variable = new Variable(new Context(), 'v', UnionType::empty(), 0);
            $before = $variable->getStateVersion();
            $check($variable);
            $this->assertGreaterThan($before, $variable->getStateVersion(), "Variable::$name() should increment the state version");
        }
        $variable = new Variable(new Context(), 'v', UnionType::empty(), 0);
        $variable->enablePhanFlagBits(Flags::IS_DEPRECATED);
        $before = $variable->getStateVersion();
        $variable->disablePhanFlagBits(Flags::IS_DEPRECATED);
        $this->assertGreaterThan($before, $variable->getStateVersion());
        // The masked phan flags do not.
        $before = $variable->getStateVersion();
        $variable->enablePhanFlagBits(Flags::IS_OVERRIDE);
        $this->assertSame($before, $variable->getStateVersion());
    }

    public function testScopeMutatorsBump(): void
    {
        $fqsen = FullyQualifiedMethodName::fromFullyQualifiedString('\StateVersionTest\C::m');
        $checks = [
            'addVariable' => static fn(FunctionLikeScope $s) => $s->addVariable(new Variable(new Context(), 'a', UnionType::empty(), 0)),
            'unsetVariable' => static fn(FunctionLikeScope $s) => $s->unsetVariable('a'),
            'addGlobalVariable' => static fn(FunctionLikeScope $s) => $s->addGlobalVariable(new Variable(new Context(), 'state_version_test_global', UnionType::empty(), 0)),
            'addTemplateType' => static fn(FunctionLikeScope $s) => $s->addTemplateType(TemplateType::instanceForId('T', false)),
        ];
        foreach ($checks as $name => $check) {
            $scope = new FunctionLikeScope(new GlobalScope(), $fqsen);
            $before = $scope->getStateVersion();
            $check($scope);
            $this->assertGreaterThan($before, $scope->getStateVersion(), "Scope::$name() should increment the state version");
        }
    }

    /**
     * @param class-string $class_name
     * @param array<string,mixed> $bumps
     * @param array<string,string> $no_bump
     */
    private function assertEveryMutatorIsClassified(string $class_name, array $bumps, array $no_bump): void
    {
        $this->assertSame([], \array_values(\array_intersect(\array_keys($bumps), \array_keys($no_bump))));
        $unclassified = [];
        foreach ((new ReflectionClass($class_name))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $name = $method->getName();
            if ($method->isStatic() || !\preg_match(self::MUTATOR_PREFIX_REGEX, $name)) {
                continue;
            }
            if (!isset($bumps[$name]) && !isset($no_bump[$name])) {
                $unclassified[] = $name;
            }
        }
        $this->assertSame(
            [],
            $unclassified,
            "Every public mutator of $class_name must increment the state version (and be tested here) or be listed as an exception"
        );
    }

    public function testEveryMethodMutatorIsClassified(): void
    {
        $this->assertEveryMutatorIsClassified(Method::class, self::methodBumps(), self::METHOD_NO_BUMP);
    }

    public function testEveryParameterMutatorIsClassified(): void
    {
        $this->assertEveryMutatorIsClassified(Parameter::class, self::parameterBumps(), self::PARAMETER_NO_BUMP);
    }

    public function testRealParameterListWithoutCloning(): void
    {
        $method = self::makeMethod();
        $method->setRealParameterList([self::makeParameter('a'), self::makeParameter('b')]);
        $list = $method->getRealParameterListWithoutCloning();
        $this->assertCount(2, $list);
        $this->assertSame($list, $method->getRealParameterListWithoutCloning());
        $this->assertNotSame($list[0], $method->getRealParameterList()[0]);
    }

    public function testCodeBaseAssignsEagerOrdinals(): void
    {
        $code_base = new CodeBase([], [], [], [], []);
        $first = self::makeMethod('first');
        $second = self::makeMethod('second');
        $this->assertSame(0, $first->getEagerOrdinal());
        $code_base->addMethod($first);
        $code_base->addMethod($second);
        $this->assertGreaterThan(0, $first->getEagerOrdinal());
        $this->assertSame($first->getEagerOrdinal() + 1, $second->getEagerOrdinal());
        // A clone keeps the ordinal until it is added itself.
        $clone = clone($second);
        $this->assertSame($second->getEagerOrdinal(), $clone->getEagerOrdinal());
    }
}
