<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\AST\UnionTypeVisitor;
use Phan\CodeBase;
use Phan\Exception\CodeBaseException;
use Phan\Language\Context;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope\FunctionLikeScope;
use Phan\Language\Type\GenericArrayType;
use Phan\Language\UnionType;
use ast\Node;

/**
 * @phan-file-suppress PhanPartialTypeMismatchArgument
 */
class Method extends ClassElement implements FunctionInterface
{
    use \Phan\Analysis\Analyzable;
    use \Phan\Memoize;
    use FunctionTrait;
    use ClosedScopeElement;

    /**
     * @var ?FullyQualifiedMethodName If this was originally defined in a trait, this is the trait's defining fqsen.
     * This is tracked separately from getDefiningFQSEN() in order to not break access checks on protected/private methods.
     * Used for dead code detection.
     */
    private $real_defining_fqsen;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name
     * The name of the typed structural element
     *
     * @param UnionType $type
     * A '|' delimited set of types satisfied by this
     * typed structural element.
     *
     * @param int $flags
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     *
     * @param FullyQualifiedMethodName $fqsen
     * A fully qualified name for the element
     *
     * @param ?array<int,Parameter> $parameter_list
     * A list of parameters to set on this method
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedMethodName $fqsen,
        $parameter_list
    ) {
        parent::__construct(
            $context,
            FullyQualifiedMethodName::canonicalName($name),
            $type,
            $flags,
            $fqsen
        );

        // Presume that this is the original definition
        // of this method, and let it be overwritten
        // if it isn't.
        $this->setDefiningFQSEN($fqsen);
        $this->real_defining_fqsen = $fqsen;

        // Record the FQSEN of this method (With the current Clazz),
        // to prevent recursing from a method into itself in non-quick mode.
        $this->setInternalScope(new FunctionLikeScope(
            $context->getScope(),
            $fqsen
        ));

        if ($parameter_list !== null) {
            $this->setParameterList($parameter_list);
        }
    }

    /**
     * @return bool
     * True if this is a magic phpdoc method (declared via (at)method on class declaration phpdoc)
     */
    public function isFromPHPDoc() : bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_FROM_PHPDOC);
    }

    /**
     * @param bool $from_phpdoc - True if this is a magic phpdoc method (declared via (at)method on class declaration phpdoc)
     * @return void
     */
    public function setIsFromPHPDoc(bool $from_phpdoc)
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::IS_FROM_PHPDOC,
                $from_phpdoc
            )
        );
    }

    /**
     * @return bool
     * True if this method is intended to be an override of another method (contains (at)override)
     */
    public function isOverrideIntended() : bool
    {
        return $this->getPhanFlagsHasState(Flags::IS_OVERRIDE_INTENDED);
    }

    /**
     * @param bool $is_override_intended - True if this method is intended to be an override of another method (contains (at)override)

     * @return void
     */
    public function setIsOverrideIntended(bool $is_override_intended)
    {
        $this->setPhanFlags(
            Flags::bitVectorWithState(
                $this->getPhanFlags(),
                Flags::IS_OVERRIDE_INTENDED,
                $is_override_intended
            )
        );
    }

    /**
     * @return bool
     * True if this is an abstract method
     */
    public function isAbstract() : bool
    {
        return $this->getFlagsHasState(\ast\flags\MODIFIER_ABSTRACT);
    }

    /**
     * @return bool
     * True if this is a final method
     */
    public function isFinal() : bool
    {
        return $this->getFlagsHasState(\ast\flags\MODIFIER_FINAL);
    }

    /**
     * @return bool
     * True if this method returns a reference
     */
    public function returnsRef() : bool
    {
        return $this->getFlagsHasState(\ast\flags\RETURNS_REF);
    }

    /**
     * @return bool
     * True if this is a magic method
     * (Names are all normalized in FullyQualifiedMethodName::make())
     */
    public function getIsMagic() : bool
    {
        return \array_key_exists($this->getName(), FullyQualifiedMethodName::MAGIC_METHOD_NAME_SET);
    }

    /**
     * @return bool
     * True if this is a magic method which should have return type of void
     * (Names are all normalized in FullyQualifiedMethodName::make())
     */
    public function getIsMagicAndVoid() : bool
    {
        return \array_key_exists($this->getName(), FullyQualifiedMethodName::MAGIC_VOID_METHOD_NAME_SET);
    }

    /**
     * @return bool
     * True if this is the `__construct` method
     * (Does not return true for php4 constructors)
     */
    public function getIsNewConstructor() : bool
    {
        return ($this->getName() === '__construct');
    }

    /**
     * @return bool
     * True if this is the magic `__call` method
     */
    public function getIsMagicCall() : bool
    {
        return ($this->getName() === '__call');
    }

    /**
     * @return bool
     * True if this is the magic `__callStatic` method
     */
    public function getIsMagicCallStatic() : bool
    {
        return ($this->getName() === '__callStatic');
    }

    /**
     * @return Method
     * A default constructor for the given class
     */
    public static function defaultConstructorForClass(
        Clazz $clazz,
        CodeBase $code_base
    ) : Method {
        if ($clazz->getFQSEN()->getNamespace() === '\\' && $clazz->hasMethodWithName($code_base, $clazz->getName())) {
            $old_style_constructor = $clazz->getMethodByName($code_base, $clazz->getName());
        } else {
            $old_style_constructor = null;
        }

        $method_fqsen = FullyQualifiedMethodName::make(
            $clazz->getFQSEN(),
            '__construct'
        );

        $method = new Method(
            $old_style_constructor ? $old_style_constructor->getContext() : $clazz->getContext(),
            '__construct',
            $clazz->getUnionType(),
            0,
            $method_fqsen,
            $old_style_constructor ? $old_style_constructor->getParameterList() : null
        );

        if ($old_style_constructor) {
            $method->setRealParameterList($old_style_constructor->getRealParameterList());
            $method->setNumberOfRequiredParameters($old_style_constructor->getNumberOfRequiredParameters());
            $method->setNumberOfOptionalParameters($old_style_constructor->getNumberOfOptionalParameters());
            $method->setRealReturnType($old_style_constructor->getRealReturnType());
            $method->setUnionType($old_style_constructor->getUnionType());
        }

        return $method;
    }

    /**
     * @param Clazz $clazz - The class to treat as the defining class of the alias. (i.e. the inheriting class)
     * @param string $alias_method_name - The alias method name.
     * @param int $new_visibility_flags (0 if unchanged)
     * @return Method
     *
     * An alias from a trait use, which is treated as though it was defined in $clazz
     * E.g. if you import a trait's method as private/protected, it becomes private/protected **to the class which used the trait**
     *
     * The resulting alias doesn't inherit the \ast\Node of the method body, so aliases won't have a redundant analysis step.
     */
    public function createUseAlias(
        Clazz $clazz,
        string $alias_method_name,
        int $new_visibility_flags
    ) : Method {

        $method_fqsen = FullyQualifiedMethodName::make(
            $clazz->getFQSEN(),
            $alias_method_name
        );

        $method = new Method(
            $this->getContext(),
            $alias_method_name,
            $this->getUnionType(),
            $this->getFlags(),
            $method_fqsen,
            $this->getParameterList()
        );
        switch ($new_visibility_flags) {
            case \ast\flags\MODIFIER_PUBLIC:
            case \ast\flags\MODIFIER_PROTECTED:
            case \ast\flags\MODIFIER_PRIVATE:
                // Replace the visibility with the new visibility.
                $method->setFlags(Flags::bitVectorWithState(
                    Flags::bitVectorWithState(
                        $method->getFlags(),
                        \ast\flags\MODIFIER_PUBLIC | \ast\flags\MODIFIER_PROTECTED | \ast\flags\MODIFIER_PRIVATE,
                        false
                    ),
                    $new_visibility_flags,
                    true
                ));
                break;
            default:
                break;
        }

        $defining_fqsen = $this->getDefiningFQSEN();
        if ($method->isPublic()) {
            $method->setDefiningFQSEN($defining_fqsen);
        }
        $method->real_defining_fqsen = $defining_fqsen;

        $method->setRealParameterList($this->getRealParameterList());
        $method->setRealReturnType($this->getRealReturnType());
        $method->setNumberOfRequiredParameters($this->getNumberOfRequiredParameters());
        $method->setNumberOfOptionalParameters($this->getNumberOfOptionalParameters());

        return $method;
    }

    /**
     * @param Context $context
     * The context in which the node appears
     *
     * @param CodeBase $code_base
     *
     * @param Node $node
     * An AST node representing a method
     *
     * @return Method
     * A Method representing the AST node in the
     * given context
     */
    public static function fromNode(
        Context $context,
        CodeBase $code_base,
        Node $node,
        FullyQualifiedMethodName $fqsen
    ) : Method {

        // @var array<int,Parameter>
        // The list of parameters specified on the
        // method
        $parameter_list =
            Parameter::listFromNode(
                $context,
                $code_base,
                $node->children['params']
            );

        // Create the skeleton method object from what
        // we know so far
        $method = new Method(
            $context,
            (string)$node->children['name'],
            UnionType::empty(),
            $node->flags ?? 0,
            $fqsen,
            $parameter_list
        );

        // Parse the comment above the method to get
        // extra meta information about the method.
        $comment = Comment::fromStringInContext(
            $node->children['docComment'] ?? '',
            $code_base,
            $context,
            $node->lineno ?? 0,
            Comment::ON_METHOD
        );

        // Add each parameter to the scope of the function
        // NOTE: it's important to clone this,
        // because we don't want any assignments to modify the original Parameter
        foreach ($parameter_list as $parameter) {
            $method->getInternalScope()->addVariable(
                $parameter->cloneAsNonVariadic()
            );
        }

        if (!$method->isPHPInternal()) {
            // If the method is Analyzable, set the node so that
            // we can come back to it whenever we like and
            // rescan it
            $method->setNode($node);
        }

        // Keep an copy of the original parameter list, to check for fatal errors later on.
        $method->setRealParameterList($parameter_list);

        $method->setNumberOfRequiredParameters(array_reduce(
            $parameter_list,
            function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isRequired() ? 1 : 0));
            },
            0
        ));

        $method->setNumberOfOptionalParameters(array_reduce(
            $parameter_list,
            function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isOptional() ? 1 : 0));
            },
            0
        ));

        // Check to see if the comment specifies that the
        // method is deprecated
        $method->setIsDeprecated($comment->isDeprecated());

        // Set whether or not the element is internal to
        // the namespace.
        $method->setIsNSInternal($comment->isNSInternal());

        // Set whether or not the comment indicates that the method is intended
        // to override another method.
        $method->setIsOverrideIntended($comment->isOverrideIntended());
        $method->setSuppressIssueList($comment->getSuppressIssueList());

        if ($method->getIsMagicCall() || $method->getIsMagicCallStatic()) {
            $method->setNumberOfOptionalParameters(FunctionInterface::INFINITE_PARAMETERS);
            $method->setNumberOfRequiredParameters(0);
        }

        // Add the syntax-level return type to the method's union type
        // if it exists
        $return_union_type = UnionType::empty();
        if ($node->children['returnType'] !== null) {
            $return_union_type = UnionTypeVisitor::unionTypeFromNode(
                $code_base,
                $context,
                $node->children['returnType']
            );
            $method->setUnionType($method->getUnionType()->withUnionType($return_union_type));
        }
        $method->setRealReturnType($return_union_type);

        // If available, add in the doc-block annotated return type
        // for the method.
        if ($comment->hasReturnUnionType()) {
            $comment_return_union_type = $comment->getReturnType();
            if ($comment_return_union_type->hasSelfType()) {
                // We can't actually figure out 'static' at this
                // point, but fill it in regardless. It will be partially
                // correct
                if ($context->isInClassScope()) {
                    // n.b.: We're leaving the reference to self, static
                    //       or $this in the type because I'm guessing
                    //       it doesn't really matter. Apologies if it
                    //       ends up being an issue.
                    $comment_return_union_type = $comment_return_union_type->withType(
                        $context->getClassFQSEN()->asType()
                    );
                    // $comment->setReturnType($comment_return_union_type);
                }
            }

            $method->setUnionType($method->getUnionType()->withUnionType($comment_return_union_type));
            $method->setPHPDocReturnType($comment_return_union_type);
        }

        // Defer adding params to the local scope for user functions. (FunctionTrait::addParamsToScopeOfFunctionOrMethod)
        // See PostOrderAnalysisVisitor->analyzeCallToMethod
        $method->setComment($comment);

        return $method;
    }

    /**
     * @return UnionType
     * The type of this method in its given context.
     */
    public function getUnionType() : UnionType
    {
        $union_type = parent::getUnionType();

        // If the type is 'static', add this context's class
        // to the return type
        if ($union_type->hasStaticType()) {
            $union_type = $union_type->withType(
                $this->getFQSEN()->getFullyQualifiedClassName()->asType()
            );
        }

        // If the type is a generic array of 'static', add
        // a generic array of this context's class to the return type
        if ($union_type->genericArrayElementTypes()->hasStaticType()) {
            // TODO: Base this on the static array type...
            $key_type_enum = GenericArrayType::keyTypeFromUnionTypeKeys($union_type);
            $union_type = $union_type->withType(
                $this->getFQSEN()->getFullyQualifiedClassName()->asType()->asGenericArrayType($key_type_enum)
            );
        }

        return $union_type;
    }

    /**
     * @return FullyQualifiedMethodName
     */
    public function getFQSEN() : FullyQualifiedMethodName
    {
        return $this->fqsen;
    }

    /**
     * @return \Generator
     * The set of all alternates to this method
     */
    public function alternateGenerator(CodeBase $code_base) : \Generator
    {
        $alternate_id = 0;
        $fqsen = $this->getFQSEN();

        while ($code_base->hasMethodWithFQSEN($fqsen)) {
            yield $code_base->getMethodByFQSEN($fqsen);
            $fqsen = $fqsen->withAlternateId(++$alternate_id);
        }
    }

    /**
     * @param CodeBase $code_base
     * The code base with which to look for classes
     *
     * @return Method[]
     * The Methods that this Method is overriding
     * (Abstract methods are returned before concrete methods)
     *
     * @throws CodeBaseException if 0 methods were found.
     */
    public function getOverriddenMethods(
        CodeBase $code_base
    ) : array {
        // Get the class that defines this method
        $class = $this->getClass($code_base);

        // Get the list of ancestors of that class
        $ancestor_class_list = $class->getAncestorClassList(
            $code_base
        );

        $defining_fqsen = $this->getDefiningFQSEN();

        $method_list = [];
        $abstract_method_list = [];
        // Hunt for any ancestor classes that define a method with
        // the same name as this one.
        foreach ($ancestor_class_list as $ancestor_class) {
            // TODO: Handle edge cases in traits.
            // A trait may be earlier in $ancestor_class_list than the parent, but the parent may define abstract classes.
            // TODO: What about trait aliasing rules?
            if ($ancestor_class->hasMethodWithName($code_base, $this->getName())) {
                $method = $ancestor_class->getMethodByName(
                    $code_base,
                    $this->getName()
                );
                if ($method->getDefiningFQSEN() === $defining_fqsen) {
                    // Skip it, this method **is** the one which defined this.
                    continue;
                }
                // We initialize the overridden method's scope to ensure that
                // analyzers are aware of the full param/return types of the overridden method.
                $method->ensureScopeInitialized($code_base);
                if ($method->isAbstract()) {
                    // TODO: check for trait conflicts, etc.
                    $abstract_method_list[] = $method;
                    continue;
                }
                $method_list[] = $method;
            }
        }
        // Return abstract methods before concrete methods, in order to best check method compatibility.
        $method_list = array_merge($abstract_method_list, $method_list);
        if (count($method_list) > 0) {
            return $method_list;
        }

        // Throw an exception if this method doesn't override
        // anything
        throw new CodeBaseException(
            $this->getFQSEN(),
            "Method $this with FQSEN {$this->getFQSEN()} does not override another method"
        );
    }

    /**
     * @return FullyQualifiedMethodName the FQSEN with the original definition (Even if this is private/protected and inherited from a trait). Used for dead code detection.
     *                                  Inheritance tests use getDefiningFQSEN() so that access checks won't break.
     *
     * @suppress PhanPartialTypeMismatchReturn TODO: Allow subclasses to make property types more specific
     */
    public function getRealDefiningFQSEN() : FullyQualifiedMethodName
    {
        return $this->real_defining_fqsen ?? $this->getDefiningFQSEN();
    }

    /**
     * @return string
     * A string representation of this method signature (preferring phpdoc types)
     */
    public function __toString() : string
    {
        $string = '';
        // TODO: should this representation and other representations include visibility?

        $string .= 'function ';
        if ($this->returnsRef()) {
            $string .= '&';
        }
        $string .= $this->getName();

        $string .= '(' . \implode(', ', $this->getParameterList()) . ')';

        if (!$this->getUnionType()->isEmpty()) {
            $string .= ' : ' . (string)$this->getUnionType();
        }

        return $string;
    }

    /**
     * @return string
     * A string representation of this method signature
     * (Based on real types only, instead of phpdoc+real types)
     */
    public function toRealSignatureString() : string
    {
        $string = '';

        $string .= 'function ';
        if ($this->returnsRef()) {
            $string .= '&';
        }
        $string .= $this->getName();

        $string .= '(' . \implode(', ', $this->getRealParameterList()) . ')';

        if (!$this->getRealReturnType()->isEmpty()) {
            $string .= ' : ' . (string)$this->getRealReturnType();
        }

        return $string;
    }

    public function toStub(bool $class_is_interface = false) : string
    {
        $string = '    ';
        // It's an error to have visibility or abstract in an interface's stub (e.g. JsonSerializable)
        if (!$class_is_interface) {
            if ($this->isPrivate()) {
                $string .= 'private ';
            } elseif ($this->isProtected()) {
                $string .= 'protected ';
            } else {
                $string .= 'public ';
            }

            if ($this->isAbstract()) {
                $string .= 'abstract ';
            }
        }

        if ($this->isStatic()) {
            $string .= 'static ';
        }

        $string .= 'function ';
        if ($this->returnsRef()) {
            $string .= '&';
        }
        $string .= $this->getName();

        $string .= '(' . implode(', ', array_map(function (Parameter $parameter) : string {
            return $parameter->toStubString();
        }, $this->getRealParameterList())) . ')';

        if (!$this->getRealReturnType()->isEmpty()) {
            $string .= ' : ' . (string)$this->getRealReturnType();
        }
        if ($this->isAbstract()) {
            $string .= ';';
        } else {
            $string .= ' {}';
        }

        return $string;
    }
}
