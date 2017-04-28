<?php declare(strict_types=1);
namespace Phan\AST;

use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\TypeException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\ClassConstant;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Func;
use Phan\Language\Element\FunctionInterface;
use Phan\Language\Element\GlobalConstant;
use Phan\Language\Element\Method;
use Phan\Language\Element\Property;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StringType;
use Phan\Language\UnionType;
use Phan\Library\None;
use Phan\Library\Some;
use ast\Node;

/**
 * Methods for an AST node in context
 */
class ContextNode
{

    /** @var CodeBase */
    private $code_base;

    /** @var Context */
    private $context;

    /** @var Node|string|null */
    private $node;

    /**
     * @param CodeBase $code_base
     * @param Context $context
     * @param Node|string|null $node
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        $node
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->node = $node;
    }

    /**
     * Get a list of fully qualified names from a node
     *
     * @return string[]
     */
    public function getQualifiedNameList() : array
    {
        if (!($this->node instanceof Node)) {
            return [];
        }

        return array_map(function ($name_node) {
            return (new ContextNode(
                $this->code_base,
                $this->context,
                $name_node
            ))->getQualifiedName();
        }, $this->node->children ?? []);
    }

    /**
     * Get a fully qualified name form a node
     *
     * @return string
     */
    public function getQualifiedName() : string
    {
        return $this->getClassUnionType()->__toString();
    }

    /**
     * @return string
     * A variable name associated with the given node
     */
    public function getVariableName() : string
    {
        if (!$this->node instanceof \ast\Node) {
            return (string)$this->node;
        }

        $node = $this->node;
        $parent = $node;

        while (($node instanceof \ast\Node)
            && ($node->kind != \ast\AST_VAR)
            && ($node->kind != \ast\AST_STATIC)
            && ($node->kind != \ast\AST_MAGIC_CONST)
        ) {
            $parent = $node;
            $node = array_values($node->children ?? [])[0];
        }

        if (!$node instanceof \ast\Node) {
            return (string)$node;
        }

        if (empty($node->children['name'])) {
            return '';
        }

        if ($node->children['name'] instanceof \ast\Node) {
            return '';
        }

        return (string)$node->children['name'];
    }

    /**
     * @return UnionType the union type of the class for this class node. (Should have just one Type)
     */
    public function getClassUnionType() : UnionType
    {
        return UnionTypeVisitor::unionTypeFromClassNode(
            $this->code_base,
            $this->context,
            $this->node
        );
    }

    /**
     * @param bool $ignore_missing_classes
     * If set to true, missing classes will be ignored and
     * exceptions will be inhibited
     *
     * @return Clazz[]
     * A list of classes representing the non-native types
     * associated with the given node
     *
     * @throws CodeBaseException
     * An exception is thrown if a non-native type does not have
     * an associated class
     */
    public function getClassList($ignore_missing_classes = false)
    {
        $union_type = $this->getClassUnionType();

        $class_list = [];

        if ($ignore_missing_classes) {
            try {
                foreach ($union_type->asClassList(
                    $this->code_base,
                    $this->context
                ) as $i => $clazz) {
                    $class_list[] = $clazz;
                }
            } catch (CodeBaseException $exception) {
                // swallow it
            }
        } else {
            foreach ($union_type->asClassList(
                $this->code_base,
                $this->context
            ) as $i => $clazz) {
                $class_list[] = $clazz;
            }
        }

        return $class_list;
    }

    /**
     * @param Node|string $method_name
     * Either then name of the method or a node that
     * produces the name of the method.
     *
     * @param bool $is_static
     * Set to true if this is a static method call
     *
     * @return Method
     * A method with the given name on the class referenced
     * from the given node
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * method
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     *
     * @throws IssueException
     */
    public function getMethod(
        $method_name,
        bool $is_static
    ) : Method {

        if ($method_name instanceof Node) {
            // The method_name turned out to be a variable.
            // There isn't much we can do to figure out what
            // it's referring to.
            throw new NodeException(
                $method_name,
                "Unexpected method node"
            );
        }

        assert(
            is_string($method_name),
            "Method name must be a string. Found non-string in context."
        );

        assert(
            $this->node instanceof \ast\Node,
            '$this->node must be a node'
        );

        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $this->node->children['expr']
                    ?? $this->node->children['class']
            ))->getClassList();
        } catch (CodeBaseException $exception) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredClassMethod)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ $method_name, (string)$exception->getFQSEN() ]
                )
            );
        }

        // If there were no classes on the left-type, figure
        // out what we were trying to call the method on
        // and send out an error.
        if (empty($class_list)) {
            $union_type = UnionTypeVisitor::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $this->node->children['expr']
                    ?? $this->node->children['class']
            );

            if (!$union_type->isEmpty()
                && $union_type->isNativeType()
                && !$union_type->hasAnyType([
                    MixedType::instance(false),
                    ObjectType::instance(false),
                    StringType::instance(false)
                ])
                && !(
                    Config::get()->null_casts_as_any_type
                    && $union_type->hasType(NullType::instance(false))
                )
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::NonClassMethodCall)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ $method_name, (string)$union_type ]
                    )
                );
            }

            throw new NodeException(
                $this->node,
                "Can't figure out method call for $method_name"
            );
        }

        // Hunt to see if any of them have the method we're
        // looking for
        foreach ($class_list as $i => $class) {
            if ($class->hasMethodWithName($this->code_base, $method_name)) {
                return $class->getMethodByNameInContext(
                    $this->code_base,
                    $method_name,
                    $this->context
                );
            } else if (!$is_static && $class->allowsCallingUndeclaredInstanceMethod($this->code_base)) {
                return $class->getCallMethod($this->code_base);
            } else if ($is_static && $class->allowsCallingUndeclaredStaticMethod($this->code_base)) {
                return $class->getCallStaticMethod($this->code_base);
            }
        }

        // Figure out an FQSEN for the method we couldn't find
        $method_fqsen = FullyQualifiedMethodName::make(
            $class_list[0]->getFQSEN(),
            $method_name
        );

        if ($is_static) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredStaticMethod)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ (string)$method_fqsen ]
                )
            );
        }

        throw new IssueException(
            Issue::fromType(Issue::UndeclaredMethod)(
                $this->context->getFile(),
                $this->node->lineno ?? 0,
                [ (string)$method_fqsen ]
            )
        );
    }

    /**
     * @param string $function_name
     * The name of the function we'd like to look up
     *
     * @param bool $is_function_declaration
     * This must be set to true if we're getting a function
     * that is being declared and false if we're getting a
     * function being called.
     *
     * @return FunctionInterface
     * A method with the given name in the given context
     *
     * @throws IssueException
     * An exception is thrown if we can't find the given
     * function
     */
    public function getFunction(
        string $function_name,
        bool $is_function_declaration = false
    ) : FunctionInterface {

        if ($is_function_declaration) {
            $function_fqsen =
                FullyQualifiedFunctionName::make(
                    $this->context->getNamespace(),
                    $function_name
                );
        } else {
            $function_fqsen =
                FullyQualifiedFunctionName::make(
                    $this->context->getNamespace(),
                    $function_name
                );

            // If it doesn't exist in the local namespace, try it
            // in the global namespace
            if (!$this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
                $function_fqsen =
                    FullyQualifiedFunctionName::fromStringInContext(
                        $function_name,
                        $this->context
                    );
            }
        }

        assert(
            $this->node instanceof \ast\Node,
            '$this->node must be a node'
        );

        // Make sure the method we're calling actually exists
        if (!$this->code_base->hasFunctionWithFQSEN($function_fqsen)) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredFunction)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ "$function_fqsen()" ]
                )
            );
        }

        return $this->code_base->getFunctionByFQSEN($function_fqsen);
    }

    /**
     * @return Variable
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws IssueException
     * A IssueException is thrown if the variable doesn't
     * exist
     */
    public function getVariable() : Variable
    {
        assert(
            $this->node instanceof \ast\Node,
            '$this->node must be a node'
        );

        // Get the name of the variable
        $variable_name = $this->getVariableName();

        if (empty($variable_name)) {
            throw new NodeException(
                $this->node,
                "Variable name not found"
            );
        }

        // Check to see if the variable exists in this scope
        if (!$this->context->getScope()->hasVariableWithName($variable_name)) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredVariable)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ $variable_name ]
                )
            );
        }

        return $this->context->getScope()->getVariableByName(
            $variable_name
        );
    }

    /**
     * @return Variable
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     */
    public function getOrCreateVariable() : Variable
    {
        try {
            return $this->getVariable();
        } catch (IssueException $exception) {
            // Swallow it
        }

        assert(
            $this->node instanceof \ast\Node,
            '$this->node must be a node'
        );

        // Create a new variable
        $variable = Variable::fromNodeInContext(
            $this->node,
            $this->context,
            $this->code_base,
            false
        );

        $this->context->addScopeVariable($variable);

        return $variable;
    }

    /**
     * @param string|Node $property_name
     * The name of the property we're looking up
     *
     * @param bool $is_static
     * True if we're looking for a static property,
     * false if we're looking for an instance property.
     *
     * @return Property
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws IssueException
     * An exception is thrown if we can't find the given
     * class or if we don't have access to the property (its
     * private or protected)
     * or if the property is static and missing.
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     *
     * @throws UnanalyzableException
     * An exception is thrown if we hit a construct in which
     * we can't determine if the property exists or not
     */
    public function getProperty(
        $property_name,
        bool $is_static
    ) : Property {

        assert(
            $this->node instanceof \ast\Node,
            '$this->node must be a node'
        );

        $property_name = $this->node->children['prop'];

        // Give up for things like C::$prop_name
        if (!is_string($property_name)) {
            throw new NodeException(
                $this->node,
                "Cannot figure out non-string property name"
            );
        }

        $class_fqsen = null;

        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $this->node->children['expr'] ??
                    $this->node->children['class']
            ))->getClassList(true);
        } catch (CodeBaseException $exception) {
            if ($is_static) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredStaticProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ $property_name, (string)$exception->getFQSEN() ]
                    )
                );
            } else {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ "{$exception->getFQSEN()}->$property_name" ]
                    )
                );
            }
        }

        foreach ($class_list as $i => $class) {
            $class_fqsen = $class->getFQSEN();

            // Keep hunting if this class doesn't have the given
            // property
            if (!$class->hasPropertyWithName(
                $this->code_base,
                $property_name
            )) {
                // (if fetching an instance property)
                // If there's a getter on properties then all
                // bets are off. However, @phan-forbid-undeclared-magic-properties
                // will make this method analyze the code as if all properties were declared or had @property annotations.
                if (!$is_static && $class->hasGetMethod($this->code_base) && !$class->getForbidUndeclaredMagicProperties($this->code_base)) {
                    throw new UnanalyzableException(
                        $this->node,
                        "Can't determine if property {$property_name} exists in class {$class->getFQSEN()} with __get defined"
                    );
                }

                continue;
            }

            $property = $class->getPropertyByNameInContext(
                $this->code_base,
                $property_name,
                $this->context,
                $is_static
            );

            if ($property->isDeprecated()) {
                throw new IssueException(
                    Issue::fromType(Issue::DeprecatedProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [
                            (string)$property->getFQSEN(),
                            $property->getFileRef()->getFile(),
                            $property->getFileRef()->getLineNumberStart(),
                        ]
                    )
                );
            }

            if ($property->isNSInternal($this->code_base)
                && !$property->isNSInternalAccessFromContext(
                    $this->code_base,
                    $this->context
                )
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyInternal)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [
                            (string)$property->getFQSEN(),
                            $property->getFileRef()->getFile(),
                            $property->getFileRef()->getLineNumberStart(),
                        ]
                    )
                );
            }

            return $property;
        }

        // Since we didn't find the property on any of the
        // possible classes, check for classes with dynamic
        // properties
        if (!$is_static) {
            foreach ($class_list as $i => $class) {
                if (Config::get()->allow_missing_properties
                    || $class->getHasDynamicProperties($this->code_base)
                ) {
                    return $class->getPropertyByNameInContext(
                        $this->code_base,
                        $property_name,
                        $this->context,
                        $is_static
                    );
                }
            }
        }

        /*
        $std_class_fqsen =
            FullyQualifiedClassName::getStdClassFQSEN();

        // If missing properties are cool, create it on
        // the first class we found
        if (!$is_static && ($class_fqsen && ($class_fqsen === $std_class_fqsen))
            || Config::get()->allow_missing_properties
        ) {
            if (count($class_list) > 0) {
                $class = $class_list[0];
                return $class->getPropertyByNameInContext(
                    $this->code_base,
                    $property_name,
                    $this->context,
                    $is_static
                );
            }
        }
        */

        // If the class isn't found, we'll get the message elsewhere
        if ($class_fqsen) {
            if ($is_static) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredStaticProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ $property_name, (string)$class_fqsen ]
                    )
                );
            } else {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredProperty)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ "$class_fqsen->$property_name" ]
                    )
                );
            }
        }

        throw new NodeException(
            $this->node,
            "Cannot figure out property from {$this->context}"
        );
    }

    /**
     * @return Property
     * A variable in scope or a new variable
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws UnanalyzableException
     * An exception is thrown if we can't find the given
     * class
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     *
     * @throws TypeException
     * An exception may be thrown if the only viable candidate
     * is a non-class type.
     *
     * @throws IssueException
     * An exception is thrown if $is_static, but the property doesn't exist.
     */
    public function getOrCreateProperty(
        string $property_name,
        bool $is_static
    ) : Property {

        try {
            return $this->getProperty($property_name, $is_static);
        } catch (IssueException $exception) {
            if ($is_static) {
                throw $exception;
            }
            // TODO: log types of IssueException that aren't for undeclared properties?
            // (in another PR)

            // For instance properties, ignore it,
            // because we'll create our own property
        } catch (UnanalyzableException $exception) {
            if ($is_static) {
                throw $exception;
            }
            // For instance properties, ignore it,
            // because we'll create our own property
        }

        assert(
            $this->node instanceof \ast\Node,
            '$this->node must be a node'
        );

        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $this->node->children['expr'] ?? null
            ))->getClassList();
        } catch (CodeBaseException $exception) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredClassReference)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ $exception->getFQSEN() ]
                )
            );
        }

        if (empty($class_list)) {
            throw new UnanalyzableException(
                $this->node,
                "Could not get class name from node"
            );
        }

        $class = array_values($class_list)[0];

        $flags = 0;
        if ($this->node->kind == \ast\AST_STATIC_PROP) {
            $flags |= \ast\flags\MODIFIER_STATIC;
        }

        $property_fqsen = FullyQualifiedPropertyName::make(
            $class->getFQSEN(),
            $property_name
        );

        // Otherwise, we'll create it
        $property = new Property(
            $this->context,
            $property_name,
            new UnionType(),
            $flags,
            $property_fqsen
        );

        $class->addProperty($this->code_base, $property, new None);

        return $property;
    }

    /**
     * @return GlobalConstant
     * Get the (non-class) constant associated with this node
     * in this context
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     */
    public function getConst() : GlobalConstant
    {
        assert(
            $this->node instanceof \ast\Node,
            '$this->node must be a node'
        );

        assert(
            $this->node->kind === \ast\AST_CONST,
            "Node must be of type \ast\AST_CONST"
        );

        if ($this->node->children['name']->kind !== \ast\AST_NAME) {
            throw new NodeException(
                $this->node,
                "Can't determine constant name"
            );
        }

        $constant_name =
            $this->node->children['name']->children['name'];

        $fqsen = FullyQualifiedGlobalConstantName::fromStringInContext(
            $constant_name,
            $this->context
        );

        if (!$this->code_base->hasGlobalConstantWithFQSEN($fqsen)) {

            $fqsen = FullyQualifiedGlobalConstantName::fromFullyQualifiedString(
                $constant_name
            );

            if (!$this->code_base->hasGlobalConstantWithFQSEN($fqsen)) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredConstant)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [ $fqsen ]
                    )
                );
            }
        }

        $constant = $this->code_base->getGlobalConstantByFQSEN($fqsen);

        if ($constant->isNSInternal($this->code_base)
            && !$constant->isNSInternalAccessFromContext(
                $this->code_base,
                $this->context
            )
        ) {
            throw new IssueException(
                Issue::fromType(Issue::AccessConstantInternal)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [
                        (string)$constant->getFQSEN(),
                        $constant->getFileRef()->getFile(),
                        $constant->getFileRef()->getLineNumberStart(),
                    ]
                )
            );
        }

        return $constant;
    }

    /**
     * @return ClassConstant
     * Get the (non-class) constant associated with this node
     * in this context
     *
     * @throws NodeException
     * An exception is thrown if we can't understand the node
     *
     * @throws CodeBaseExtension
     * An exception is thrown if we can't find the given
     * class
     *
     * @throws UnanalyzableException
     * An exception is thrown if we hit a construct in which
     * we can't determine if the property exists or not
     *
     * @throws IssueException
     * An exception is thrown if an issue is found while getting
     * the list of possible classes.
     */
    public function getClassConst() : ClassConstant
    {
        assert(
            $this->node instanceof \ast\Node,
            '$this->node must be a node'
        );

        assert(
            $this->node->kind === \ast\AST_CLASS_CONST,
            "Node must be of type \ast\AST_CLASS_CONST"
        );

        $constant_name = $this->node->children['const'];

        $class_fqsen = null;

        try {
            $class_list = (new ContextNode(
                $this->code_base,
                $this->context,
                $this->node->children['class']
            ))->getClassList();
        } catch (CodeBaseException $exception) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredClassConstant)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ $constant_name, $exception->getFQSEN() ]
                )
            );
        }

        foreach ($class_list as $i => $class) {
            $class_fqsen = $class->getFQSEN();

            // Check to see if the class has the constant
            if (!$class->hasConstantWithName(
                $this->code_base,
                $constant_name
            )) {
                continue;
            }

            $constant = $class->getConstantByNameInContext(
                $this->code_base,
                $constant_name,
                $this->context
            );

            if ($constant->isNSInternal($this->code_base)
                && !$constant->isNSInternalAccessFromContext(
                    $this->code_base,
                    $this->context
                )
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessClassConstantInternal)(
                        $this->context->getFile(),
                        $this->node->lineno ?? 0,
                        [
                            (string)$constant->getFQSEN(),
                            $constant->getFileRef()->getFile(),
                            $constant->getFileRef()->getLineNumberStart(),
                        ]
                    )
                );
            }

            return $constant;
        }

        // If no class is found, we'll emit the error elsewhere
        if ($class_fqsen) {
            throw new IssueException(
                Issue::fromType(Issue::UndeclaredConstant)(
                    $this->context->getFile(),
                    $this->node->lineno ?? 0,
                    [ "$class_fqsen::$constant_name" ]
                )
            );
        }

        throw new NodeException(
            $this->node,
            "Can't figure out constant {$constant_name} in node"
        );
    }

    /**
     * @return string
     * A unique and stable name for an anonymous class
     */
    public function getUnqualifiedNameForAnonymousClass() : string
    {
        assert(
            $this->node instanceof \ast\Node,
            '$this->node must be a node'
        );

        assert(
            (bool)($this->node->flags & \ast\flags\CLASS_ANONYMOUS),
            "Node must be an anonymous class node"
        );

        $class_name = 'anonymous_class_'
            . substr(md5(implode('|', [
                $this->context->getFile(),
                $this->context->getLineNumberStart()
            ])), 0, 8);

        return $class_name;
    }

    /**
     * @return Func
     */
    public function getClosure() : Func
    {
        $closure_fqsen =
            FullyQualifiedFunctionName::fromClosureInContext(
                $this->context
            );

        if (!$this->code_base->hasFunctionWithFQSEN($closure_fqsen)) {
            throw new CodeBaseException(
                $closure_fqsen,
                "Could not find closure $closure_fqsen"
            );
        }

        return $this->code_base->getFunctionByFQSEN($closure_fqsen);
    }

    /**
     * Perform some backwards compatibility checks on a node
     *
     * @return void
     */
    public function analyzeBackwardCompatibility()
    {
        if (!Config::get()->backward_compatibility_checks) {
            return;
        }

        if (!($this->node instanceof \ast\Node) || empty($this->node->children['expr'])) {
            return;
        }

        if ($this->node->kind === \ast\AST_STATIC_CALL ||
           $this->node->kind === \ast\AST_METHOD_CALL) {
            return;
        }

        $llnode = $this->node;

        if ($this->node->kind !== \ast\AST_DIM) {
            if (!($this->node->children['expr'] instanceof Node)) {
                return;
            }

            if ($this->node->children['expr']->kind !== \ast\AST_DIM) {
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $this->node->children['expr']
                ))->analyzeBackwardCompatibility();
                return;
            }

            $temp = $this->node->children['expr']->children['expr'];
            $llnode = $this->node->children['expr'];
            $lnode = $temp;
        } else {
            $temp = $this->node->children['expr'];
            $lnode = $temp;
        }

        // Strings can have DIMs, it turns out.
        if (!($temp instanceof Node)) {
            return;
        }

        if (!($temp->kind == \ast\AST_PROP
            || $temp->kind == \ast\AST_STATIC_PROP
        )) {
            return;
        }

        while ($temp instanceof Node
            && ($temp->kind == \ast\AST_PROP
            || $temp->kind == \ast\AST_STATIC_PROP)
        ) {
            $llnode = $lnode;
            $lnode = $temp;

            // Lets just hope the 0th is the expression
            // we want
            $temp = array_values($temp->children)[0];
        }

        if (!($temp instanceof Node)) {
            return;
        }

        // Foo::$bar['baz'](); is a problem
        // Foo::$bar['baz'] is not
        if ($lnode->kind === \ast\AST_STATIC_PROP
            && $this->node->kind !== \ast\AST_CALL
        ) {
            return;
        }

        // $this->$bar['baz']; is a problem
        // $this->bar['baz'] is not
        if ($lnode->kind === \ast\AST_PROP
            && !($lnode->children['prop'] instanceof Node)
            && !($llnode->children['prop'] instanceof Node)
        ) {
            return;
        }

        if ((
                (
                    $lnode->children['prop'] instanceof Node
                    && $lnode->children['prop']->kind == \ast\AST_VAR
                )
                ||
                (
                    !empty($lnode->children['class'])
                    && $lnode->children['class'] instanceof Node
                    && (
                        $lnode->children['class']->kind == \ast\AST_VAR
                        || $lnode->children['class']->kind == \ast\AST_NAME
                    )
                )
                ||
                (
                    !empty($lnode->children['expr'])
                    && $lnode->children['expr'] instanceof Node
                    && (
                        $lnode->children['expr']->kind == \ast\AST_VAR
                        || $lnode->children['expr']->kind == \ast\AST_NAME
                    )
                )
            )
            &&
            (
                $temp->kind == \ast\AST_VAR
                || $temp->kind == \ast\AST_NAME
            )
        ) {
            $ftemp = new \SplFileObject($this->context->getFile());
            $ftemp->seek($this->node->lineno-1);
            $line = $ftemp->current();
            assert(is_string($line));
            unset($ftemp);
            if (strpos($line, '}[') === false
                || strpos($line, ']}') === false
                || strpos($line, '>{') === false
            ) {
                Issue::maybeEmit(
                    $this->code_base,
                    $this->context,
                    Issue::CompatiblePHP7,
                    $this->node->lineno ?? 0
                );
            }
        }
    }
}
