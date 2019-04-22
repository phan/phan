<?php declare(strict_types=1);

namespace Phan\Language;

use AssertionError;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\Property;
use Phan\Language\Element\TypedElement;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionLikeName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedGlobalStructuralElement;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type\ArrayShapeType;
use RuntimeException;

/**
 * An object representing the context in which any
 * structural element (such as a class or method) lives.
 * @phan-file-suppress PhanPluginDescriptionlessCommentOnPublicMethod
 */
class Context extends FileRef
{
    /**
     * @var string
     * The namespace of the file.
     * To be consistent with what ScopeVisitor sets in visitNamespace(), this is '\\' for the root namespace as well.
     */
    private $namespace = '\\';

    /**
     * @var int
     * The id of the namespace
     */
    private $namespace_id = 0;

    /**
     * @var array<int,array<string,NamespaceMapEntry>>
     * Maps [int flags => [string name/namespace => NamespaceMapEntry(fqsen, is_used)]]
     * Note that for \ast\USE_CONST (global constants), this is case-sensitive,
     * but the remaining types are case-insensitive (stored with lowercase name).
     */
    private $namespace_map = [];

    /**
     * @var array<int,array<string,NamespaceMapEntry>>
     * Maps [int flags => [string name/namespace => NamespaceMapEntry(fqsen, is_used)]]
     *
     * (This is used in the analysis phase after the parse phase)
     * @see self::$namespace_map
     */
    private $parse_namespace_map = [];

    /**
     * @var int
     * strict_types setting for the file
     */
    protected $strict_types = 0;

    /**
     * @var Scope
     * The current scope in this context
     */
    private $scope;

    /**
     * @var array<mixed,mixed>
     * caches union types for a given node
     */
    private $cache = [];

    /**
     * Create a new context
     */
    public function __construct()
    {
        $this->scope = new GlobalScope();
    }

    /**
     * @param string $namespace
     * The namespace of the file
     *
     * @return Context
     * A clone of this context with the given value is returned
     */
    public function withNamespace(string $namespace) : Context
    {
        $context = clone($this);
        $context->namespace = $namespace;
        $context->namespace_id += 1;  // Assumes namespaces are walked in order
        $context->namespace_map = [];
        return $context;
    }

    /**
     * @return string
     * The namespace of the file
     */
    public function getNamespace() : string
    {
        return $this->namespace;
    }

    /**
     * @return int
     * The namespace id within the file (incrementing starting from 0)
     * Used because a file can have duplicate identical namespace declarations.
     */
    public function getNamespaceId() : int
    {
        return $this->namespace_id;
    }

    /**
     * @return bool
     * True if we have a mapped NS for the given named element
     */
    public function hasNamespaceMapFor(int $flags, string $name) : bool
    {
        // Look for the mapping on the part before a
        // slash
        $name_parts = \explode('\\', $name, 2);
        if (\count($name_parts) > 1) {
            // We're looking for a namespace if there's more than one part
            // Namespaces are case-insensitive.
            $namespace_map_key = \strtolower($name_parts[0]);
            $flags = \ast\flags\USE_NORMAL;
        } else {
            if ($flags !== \ast\flags\USE_CONST) {
                $namespace_map_key = \strtolower($name);
            } else {
                // Constants are case-sensitive, and stored in a case-sensitive manner.
                $namespace_map_key = $name;
            }
        }
        return isset($this->namespace_map[$flags][$namespace_map_key]);
    }

    /**
     * @return FullyQualifiedGlobalStructuralElement
     * The namespace mapped name for the given flags and name
     */
    public function getNamespaceMapFor(
        int $flags,
        string $name
    ) : FullyQualifiedGlobalStructuralElement {

        // Look for the mapping on the part before a
        // slash
        $name_parts = \explode('\\', $name, 2);
        if (\count($name_parts) > 1) {
            $name = \strtolower($name_parts[0]);
            $suffix = $name_parts[1];
            // In php, namespaces, functions, and classes are case-insensitive.
            // However, constants are almost always case-insensitive.
            if ($flags !== \ast\flags\USE_CONST) {
                $suffix = \strtolower($suffix);
            }
            // The name we're looking for is a namespace(USE_NORMAL).
            // The suffix has type $flags
            $map_flags = \ast\flags\USE_NORMAL;
        } else {
            $suffix = '';
            $map_flags = $flags;
            if ($flags !== \ast\flags\USE_CONST) {
                $name = \strtolower($name);
            }
        }

        $namespace_map_entry = $this->namespace_map[$map_flags][$name] ?? null;

        if (!$namespace_map_entry) {
            throw new AssertionError('No namespace defined for name');
        }
        $fqsen = $namespace_map_entry->fqsen;
        $namespace_map_entry->is_used = true;

        // Create something of the corresponding type (which may or may not be within a suffix)
        if (!$suffix) {
            return $fqsen;
        }

        switch ($flags) {
            case \ast\flags\USE_NORMAL:
                // @phan-suppress-next-line PhanThrowTypeAbsentForCall This and the suffix should have already been validated
                return FullyQualifiedClassName::fromFullyQualifiedString(
                    $fqsen->__toString() . '\\' . $suffix
                );
            case \ast\flags\USE_FUNCTION:
                // @phan-suppress-next-line PhanThrowTypeAbsentForCall
                return FullyQualifiedFunctionName::fromFullyQualifiedString(
                    $fqsen->__toString() . '\\' . $suffix
                );
            case \ast\flags\USE_CONST:
                // @phan-suppress-next-line PhanThrowTypeAbsentForCall
                return FullyQualifiedGlobalConstantName::fromFullyQualifiedString(
                    $fqsen->__toString() . '\\' . $suffix
                );
        }

        throw new AssertionError("Unknown flag $flags");
    }

    /**
     * @return Context
     * This context with the given value is returned
     */
    public function withNamespaceMap(
        int $flags,
        string $alias,
        FullyQualifiedGlobalStructuralElement $target,
        int $lineno
    ) : Context {
        $original_alias = $alias;
        if ($flags !== \ast\flags\USE_CONST) {
            $alias = \strtolower($alias);
        } else {
            $last_part_index = \strrpos($alias, '\\');
            if ($last_part_index !== false) {
                // Convert the namespace to lowercase, but not the constant name.
                $alias = \strtolower(\substr($alias, 0, $last_part_index + 1)) . \substr($alias, $last_part_index + 1);
            }
        }
        // we may have imported this namespace map from the parse phase, making the target already exist
        // TODO: Warn if namespace_map already exists? Then again, `php -l` already does.
        $parse_entry = $this->parse_namespace_map[$flags][$alias] ?? null;
        if ($parse_entry !== null) {
            // We add entries to namespace_map only after encountering them
            // This is because statements can appear before 'use Foo\Bar;' (and those don't use the 'use' statement.)
            $this->namespace_map[$flags][$alias] = $parse_entry;
            return $this;
        }
        $this->namespace_map[$flags][$alias] = new NamespaceMapEntry($target, $original_alias, $lineno);
        return $this;
    }

    /**
     * @param int $strict_types
     * The strict_type setting for the file
     *
     * @return Context
     * This context with the given value is returned
     */
    public function withStrictTypes(int $strict_types) : Context
    {
        $this->strict_types = $strict_types;
        return $this;
    }

    /**
     * @return bool
     * True if strict_types is set to 1 in this
     * context.
     */
    public function getIsStrictTypes() : bool
    {
        return (1 === $this->strict_types);
    }

    /**
     * @return Scope
     * An object describing the contents of the current
     * scope.
     */
    public function getScope() : Scope
    {
        return $this->scope;
    }

    /**
     * Set the scope on the context
     *
     * @return void
     */
    public function setScope(Scope $scope)
    {
        $this->scope = $scope;
        // TODO: Less aggressive? ConditionVisitor creates a lot of scopes
        $this->cache = [];
    }

    /**
     * @return Context
     * A new context with the given scope
     */
    public function withScope(Scope $scope) : Context
    {
        $context = clone($this);
        $context->setScope($scope);
        return $context;
    }

    /**
     * @return Context
     *
     * A new context with the a clone of the current scope.
     * This is useful when using AssignmentVisitor for things that aren't actually assignment operations.
     * (AssignmentVisitor modifies the passed in scope variables in place)
     */
    public function withClonedScope() : Context
    {
        $context = clone($this);
        $context->scope = clone($context->scope);
        return $context;
    }

    /**
     * @param Variable $variable
     * A variable to add to the scope for the new
     * context
     *
     * @return Context
     * A new context based on this with a variable
     * as defined by the parameters in scope
     */
    public function withScopeVariable(
        Variable $variable
    ) : Context {
        return $this->withScope(
            $this->scope->withVariable($variable)
        );
    }

    /**
     * @param Variable $variable
     * A variable to add to the scope for the new
     * context
     *
     * @return void
     */
    public function addGlobalScopeVariable(Variable $variable)
    {
        $this->scope->addGlobalVariable($variable);
    }

    /**
     * Add a variable to this context's scope. Note that
     * this does not create a new context. You're actually
     * injecting the variable into the context. Use with
     * caution.
     *
     * @param Variable $variable
     * A variable to inject into this context
     *
     * @return void
     */
    public function addScopeVariable(
        Variable $variable
    ) {
        $this->scope->addVariable($variable);
    }

    /**
     * Unset a variable in this context's scope. Note that
     * this does not create a new context. You're actually
     * removing the variable from the context. Use with
     * caution.
     *
     * @param string $variable_name
     * The name of a variable to remove from the context.
     *
     * @return void
     */
    public function unsetScopeVariable(
        string $variable_name
    ) {
        $this->scope->unsetVariable($variable_name);
    }

    /**
     * Returns a string representing this Context for debugging
     * @suppress PhanUnreferencedPublicMethod kept around to make it easy to dump variables in a context
     */
    public function toDebugString() : string
    {
        $result = (string)$this;
        foreach ($this->getScope()->getVariableMap() as $variable) {
            $result .= "\n$variable";
        }
        return $result;
    }

    /**
     * @return bool
     * True if this context is currently within a class
     * scope, else false.
     */
    public function isInClassScope() : bool
    {
        return $this->scope->isInClassScope();
    }

    /**
     * @return FullyQualifiedClassName
     * A fully-qualified structural element name describing
     * the current class in scope.
     */
    public function getClassFQSEN() : FullyQualifiedClassName
    {
        return $this->scope->getClassFQSEN();
    }

    /**
     * @return ?FullyQualifiedClassName
     */
    public function getClassFQSENOrNull()
    {
        return $this->scope->getClassFQSENOrNull();
    }

    /**
     * @return bool
     * True if this context is currently within a property
     * scope, else false.
     * @suppress PhanUnreferencedPublicMethod
     */
    public function isInPropertyScope() : bool
    {
        return $this->scope->isInPropertyScope();
    }

    /**
     * @return FullyQualifiedPropertyName
     * A fully-qualified structural element name describing
     * the current property in scope.
     */
    public function getPropertyFQSEN() : FullyQualifiedPropertyName
    {
        return $this->scope->getPropertyFQSEN();
    }

    /**
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return Clazz
     * Get the class in this scope, or fail real hard
     *
     * @throws CodeBaseException
     * Thrown if we can't find the class in scope within the
     * given codebase.
     */
    public function getClassInScope(CodeBase $code_base) : Clazz
    {
        if (!$this->scope->isInClassScope()) {
            throw new AssertionError("Must be in class scope to get class");
        }

        if (!$code_base->hasClassWithFQSEN($this->getClassFQSEN())) {
            throw new CodeBaseException(
                $this->getClassFQSEN(),
                "Cannot find class with FQSEN {$this->getClassFQSEN()} in context {$this}"
            );
        }

        return $code_base->getClassByFQSEN(
            $this->getClassFQSEN()
        );
    }

    /**
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return Property
     * Get the property in this scope, or fail real hard
     *
     * @throws CodeBaseException
     * Thrown if we can't find the property in scope within the
     * given codebase.
     */
    public function getPropertyInScope(CodeBase $code_base) : Property
    {
        if (!$this->scope->isInPropertyScope()) {
            throw new AssertionError("Must be in property scope to get property");
        }

        $property_fqsen = $this->getPropertyFQSEN();
        if (!$code_base->hasPropertyWithFQSEN($property_fqsen)) {
            throw new CodeBaseException(
                $property_fqsen,
                "Cannot find class with FQSEN {$property_fqsen} in context {$this}"
            );
        }

        return $code_base->getPropertyByFQSEN(
            $property_fqsen
        );
    }

    /**
     * @return bool
     * True if this context is currently within a method,
     * function or closure scope.
     */
    public function isInFunctionLikeScope() : bool
    {
        return $this->scope->isInFunctionLikeScope();
    }

    /**
     * @return bool
     * True if this context is currently within a method.
     */
    public function isInMethodScope() : bool
    {
        return $this->scope->isInMethodLikeScope();
    }

    /**
     * @return FullyQualifiedFunctionLikeName|FullyQualifiedMethodName|FullyQualifiedFunctionName
     * A fully-qualified structural element name describing
     * the current function or method in scope.
     */
    public function getFunctionLikeFQSEN()
    {
        $scope = $this->scope;
        if (!$scope->isInFunctionLikeScope()) {
            throw new AssertionError("Must be in function-like scope to get function-like FQSEN");
        }
        return $scope->getFunctionLikeFQSEN();
    }

    /**
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @return Element\Func|Element\Method
     * Get the method in this scope or fail real hard
     */
    public function getFunctionLikeInScope(
        CodeBase $code_base
    ) : FunctionInterface {
        $fqsen = $this->getFunctionLikeFQSEN();

        if ($fqsen instanceof FullyQualifiedFunctionName) {
            if (!$code_base->hasFunctionWithFQSEN($fqsen)) {
                throw new RuntimeException("The function $fqsen does not exist, but Phan is in that function's scope");
            }
            return $code_base->getFunctionByFQSEN($fqsen);
        }

        if ($fqsen instanceof FullyQualifiedMethodName) {
            if (!$code_base->hasMethodWithFQSEN($fqsen)) {
                throw new RuntimeException("Method does not exist");
            }
            return $code_base->getMethodByFQSEN($fqsen);
        }

        throw new AssertionError("FQSEN must be for a function or method");
    }

    /**
     * @return bool
     * True if we're within the scope of a class, method,
     * function or closure. False if we're in the global
     * scope
     * @suppress PhanUnreferencedPublicMethod
     */
    public function isInElementScope() : bool
    {
        return $this->scope->isInElementScope();
    }

    /**
     * @return bool
     * True if we're in the global scope (not in a class,
     * method, function, closure).
     */
    public function isInGlobalScope() : bool
    {
        return !$this->scope->isInElementScope();
    }

    /**
     * @param CodeBase $code_base
     * The code base from which to retrieve the TypedElement
     *
     * @return TypedElement
     * The element whose scope we're in. If we're in the global
     * scope this method will go down in flames and take your
     * process with it.
     *
     * @throws CodeBaseException if this was called without first checking
     * if this context is in an element scope
     */
    public function getElementInScope(CodeBase $code_base) : TypedElement
    {
        if ($this->scope->isInFunctionLikeScope()) {
            return $this->getFunctionLikeInScope($code_base);
        } elseif ($this->scope->isInPropertyScope()) {
            return $this->getPropertyInScope($code_base);
        } elseif ($this->scope->isInClassScope()) {
            return $this->getClassInScope($code_base);
        }

        throw new CodeBaseException(
            null,
            "Cannot get element in scope if we're in the global scope"
        );
    }

    /**
     * @param CodeBase $code_base
     * The code base from which to retrieve a possible TypedElement
     * that contains an issue suppression list
     *
     * @return bool
     * True if issues with the given name are suppressed within
     * this context.
     */
    public function hasSuppressIssue(
        CodeBase $code_base,
        string $issue_name
    ) : bool {
        if ($code_base->hasFileLevelSuppression($this->getFile(), $issue_name)) {
            return true;
        }
        if (!$this->scope->isInElementScope()) {
            return false;
        }

        $has_suppress_issue =
            $this->getElementInScope($code_base)->hasSuppressIssue(
                $issue_name
            );

        // Increment the suppression use count
        if ($has_suppress_issue) {
            $this->getElementInScope($code_base)->incrementSuppressIssueCount($issue_name);
        }

        return $has_suppress_issue;
    }

    /**
     * $this->cache is reused for multiple types of caches
     * We xor the node ids with the following bits so that the values don't overlap.
     * (The node id is based on \spl_object_id(), which is the object ID number.
     *
     * (This caching scheme makes a reasonable assumption
     * that there are less than 1 billion Node objects on 32-bit systems,
     * (It'd run out of memory with more than 4 bytes needed per Node)
     * and less than (1 << 62) objects on 64-bit systems.)
     *
     * It also assumes that nodes won't be freed while this Context still exists
     *
     * 0x00(node_id) is used for getUnionTypeOfNodeIfCached(int $node_id, false)
     * 0x10(node_id) is used for getUnionTypeOfNodeIfCached(int $node_id, true)
     * 0x01(node_id) is used for getCachedClassListOfNode(int $node_id)
     */
    const HIGH_BIT_1 = (1 << (\PHP_INT_SIZE * 8) - 1);
    const HIGH_BIT_2 = (1 << (\PHP_INT_SIZE * 8) - 2);

    /**
     * @param int $node_id \spl_object_id($node)
     * @param bool $should_catch_issue_exception the value passed to UnionTypeVisitor
     * @return ?UnionType
     */
    public function getUnionTypeOfNodeIfCached(int $node_id, bool $should_catch_issue_exception)
    {
        if ($should_catch_issue_exception) {
            return $this->cache[$node_id] ?? null;
        }
        return $this->cache[$node_id ^ self::HIGH_BIT_1] ?? null;
    }

    /**
     * TODO: This may be unsafe? Clear the cache after a function goes out of scope.
     *
     * A UnionType is only cached if there is no exception.
     *
     * @param int $node_id \spl_object_id($node)
     * @param UnionType $type the type to cache.
     * @param bool $should_catch_issue_exception the value passed to UnionTypeVisitor
     * @return void
     */
    public function setCachedUnionTypeOfNode(int $node_id, UnionType $type, bool $should_catch_issue_exception)
    {
        if (!$should_catch_issue_exception) {
            $this->cache[$node_id ^ self::HIGH_BIT_1] = $type;
            // If we weren't suppressing exceptions and setCachedUnionTypeOfNode was called,
            // that would mean that there were no exceptions to catch.
            // So, that means the UnionType for should_catch_issue_exception = true will be the same
        }
        $this->cache[$node_id] = $type;
    }

    /**
     * @param int $node_id
     * @return ?array{0:UnionType,1:Clazz[]} $result
     * @suppress PhanPartialTypeMismatchReturn cache is mixed with other cache objects
     */
    public function getCachedClassListOfNode(int $node_id)
    {
        return $this->cache[$node_id ^ self::HIGH_BIT_2] ?? null;
    }

    /**
     * TODO: This may be unsafe? Clear the cache after a function goes out of scope.
     * @param int $node_id \spl_object_id($node)
     * @param array{0:UnionType,1:Clazz[]} $result
     * @return void
     */
    public function setCachedClassListOfNode(int $node_id, array $result)
    {
        $this->cache[$node_id ^ self::HIGH_BIT_2] = $result;
    }

    /**
     * @return void
     */
    public function clearCachedUnionTypes()
    {
        $this->cache = [];
    }

    /**
     * Gets Phan's internal representation of all of the 'use elem;' statements in a namespace.
     * Use hasNamespaceMapFor and getNamespaceMapFor instead.
     *
     * @internal
     *
     * @return array<int,array<string,NamespaceMapEntry>>
     */
    public function getNamespaceMap() : array
    {
        return $this->namespace_map;
    }

    /**
     * Warn about any unused \ast\AST_USE or \ast\AST_GROUP_USE nodes (`use Foo\Bar;`)
     * This should be called after analyzing the end of a namespace (And before analyzing the next namespace)
     *
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @internal
     *
     * @return void
     */
    public function warnAboutUnusedUseElements(CodeBase $code_base)
    {
        foreach ($this->namespace_map as $flags => $entries_for_flag) {
            foreach ($entries_for_flag as $namespace_map_entry) {
                if ($namespace_map_entry->is_used) {
                    continue;
                }
                switch ($flags) {
                    case \ast\flags\USE_NORMAL:
                    default:
                        $issue_type = Issue::UnreferencedUseNormal;
                        break;
                    case \ast\flags\USE_FUNCTION:
                        $issue_type = Issue::UnreferencedUseFunction;
                        break;
                    case \ast\flags\USE_CONST:
                        $issue_type = Issue::UnreferencedUseConstant;
                        break;
                }
                Issue::maybeEmit(
                    $code_base,
                    $this,
                    $issue_type,
                    $namespace_map_entry->lineno,
                    $namespace_map_entry->original_name,
                    (string)$namespace_map_entry->fqsen
                );
            }
        }
    }

    /**
     * @return void
     * @internal
     * @suppress PhanAccessMethodInternal
     */
    public function importNamespaceMapFromParsePhase(CodeBase $code_base)
    {
        $this->parse_namespace_map = $code_base->getNamespaceMapFromParsePhase($this->getFile(), $this->namespace, $this->namespace_id);
    }

    /**
     * Copy private properties of $other to this
     * @suppress PhanTypeSuspiciousNonTraversableForeach
     * @return void
     */
    final protected function copyPropertiesFrom(Context $other)
    {
        foreach ($other as $k => $v) {
            $this->{$k} = $v;
        }
    }

    /**
     * This name is internally used by Phan to track the properties of $this similarly to the way array shapes are represented.
     */
    const VAR_NAME_THIS_PROPERTIES = "phan\0\$this";

    /**
     * Analyzes the side effects of setting the type of $this->property to $type
     */
    public function withThisPropertySetToType(Property $property, UnionType $type) : Context
    {
        $old_union_type = $property->getUnionType();
        if ($this->scope->hasVariableWithName(self::VAR_NAME_THIS_PROPERTIES)) {
            $variable = clone($this->scope->getVariableByName(self::VAR_NAME_THIS_PROPERTIES));
            $old_type = $variable->getUnionType();
            $override_type = ArrayShapeType::fromFieldTypes([$property->getName() => $type], false);
            $override_type = self::addArrayShapeTypes($override_type, $old_type->getTypeSet());

            $variable->setUnionType($override_type->asUnionType());
        } else {
            // There is nothing inferred about any type

            if ($old_union_type->isEqualTo($type)) {
                // And this new type is what we already inferred, so there's nothing to do
                return $this;
            }
            $override_type = ArrayShapeType::fromFieldTypes([$property->getName() => $type], false);
            $variable = new Variable(
                $this,
                self::VAR_NAME_THIS_PROPERTIES,
                $override_type->asUnionType(),
                0
            );
        }
        return $this->withScopeVariable($variable);
    }

    /**
     * Analyzes the side effects of setting the type of $this->property_name to $type
     *
     * The caller should check if it is necessary to do this.
     */
    public function withThisPropertySetToTypeByName(string $property_name, UnionType $type) : Context
    {
        if ($this->scope->hasVariableWithName(self::VAR_NAME_THIS_PROPERTIES)) {
            $variable = clone($this->scope->getVariableByName(self::VAR_NAME_THIS_PROPERTIES));
            $old_type = $variable->getUnionType();
            $override_type = ArrayShapeType::fromFieldTypes([$property_name => $type], false);
            $override_type = self::addArrayShapeTypes($override_type, $old_type->getTypeSet());

            $variable->setUnionType($override_type->asUnionType());
        } else {
            // There is nothing inferred about any type

            $override_type = ArrayShapeType::fromFieldTypes([$property_name => $type], false);
            $variable = new Variable(
                $this,
                self::VAR_NAME_THIS_PROPERTIES,
                $override_type->asUnionType(),
                0
            );
        }
        return $this->withScopeVariable($variable);
    }

    /**
     * @param array<int,Type> $type_set
     */
    private static function addArrayShapeTypes(ArrayShapeType $override_type, array $type_set) : ArrayShapeType
    {
        if (!$type_set) {
            return $override_type;
        }
        $array_shape_type_set = [];
        foreach ($type_set as $type) {
            if ($type instanceof ArrayShapeType) {
                $array_shape_type_set[] = $type;
            }
        }
        if ($array_shape_type_set) {
            // Add in all of the locally known types for other properties
            $override_type = ArrayShapeType::combineWithPrecedence($override_type, ArrayShapeType::union($array_shape_type_set));
        }
        return $override_type;
    }

    /**
     * @return ?UnionType
     */
    public function getThisPropertyIfOverridden(string $name)
    {
        if (!$this->scope->hasVariableWithName(self::VAR_NAME_THIS_PROPERTIES)) {
            return null;
        }
        $types = $this->scope->getVariableByName(self::VAR_NAME_THIS_PROPERTIES)->getUnionType();
        if ($types->isEmpty()) {
            return null;
        }

        $result = UnionType::empty();
        foreach ($types->getTypeSet() as $type) {
            if (!$type instanceof ArrayShapeType) {
                return null;
            }
            $extra = $type->getFieldTypes()[$name] ?? null;
            if (!$extra || $extra->getIsPossiblyUndefined()) {
                return null;
            }
            $result = $result->withUnionType($extra);
        }
        return $result;
    }
}
