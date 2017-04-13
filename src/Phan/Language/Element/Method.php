<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\Scope\FunctionLikeScope;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use ast\Node;
use ast\Node\Decl;

class Method extends ClassElement implements FunctionInterface
{
    use \Phan\Analysis\Analyzable;
    use \Phan\Memoize;
    use FunctionTrait;
    use ClosedScopeElement;

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param UnionType $type,
     * A '|' delimited set of types satisfyped by this
     * typed structural element.
     *
     * @param int $flags,
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     *
     * @param FullyQualifiedMethodName $fqsen
     * A fully qualified name for the element
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedMethodName $fqsen
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        // Presume that this is the original definition
        // of this method, and let it be overwritten
        // if it isn't.
        $this->setDefiningFQSEN($fqsen);

        $this->setInternalScope(new FunctionLikeScope(
            $context->getScope(), $fqsen
        ));
    }


    /**
     * @return bool
     * True if this is an abstract method
     */
    public function isAbstract() : bool {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\MODIFIER_ABSTRACT
        );
    }

    /**
     * @return bool
     * True if this method returns reference
     */
    public function returnsRef() : bool {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\RETURNS_REF
        );
    }

    /**
     * @return bool
     * True if this is a magic method
     */
    public function getIsMagic() : bool {
        return in_array($this->getName(), [
            '__call',
            '__callStatic',
            '__clone',
            '__construct',
            '__debugInfo',
            '__destruct',
            '__get',
            '__invoke',
            '__isset',
            '__set',
            '__set_state',
            '__sleep',
            '__toString',
            '__unset',
            '__wakeup',
        ]);
    }

    /**
     * @return bool
     * True if this is the magic `__call` method
     */
    public function getIsMagicCall() : bool {
        return ($this->getName() === '__call');
    }

    /**
     * @return bool
     * True if this is the magic `__callStatic` method
     */
    public function getIsMagicCallStatic() : bool {
        return ($this->getName() === '__callStatic');
    }

    /**
     * @return bool
     * True if this is the magic `__get` method
     */
    public function getIsMagicGet() : bool {
        return ($this->getName() === '__get');
    }

    /**
     * @return bool
     * True if this is the magic `__set` method
     */
    public function getIsMagicSet() : bool {
        return ($this->getName() === '__set');
    }

    /**
     * @return Method
     * A default constructor for the given class
     */
    public static function defaultConstructorForClassInContext(
        Clazz $clazz,
        Context $context,
        CodeBase $code_base
    ) : Method {

        $method_fqsen = FullyQualifiedMethodName::make(
            $clazz->getFQSEN(),
            '__construct'
        );

        $method = new Method(
            $context,
            '__construct',
            $clazz->getUnionType(),
            0,
            $method_fqsen
        );

        if ($clazz->hasMethodWithName($code_base, $clazz->getName())) {
            $old_style_constructor = $clazz->getMethodByName($code_base, $clazz->getName());
            $parameter_list = $old_style_constructor->getParameterList();
            $method->setParameterList($parameter_list);
            $method->setRealParameterList($parameter_list);
            $method->setNumberOfRequiredParameters($old_style_constructor->getNumberOfRequiredParameters());
            $method->setNumberOfOptionalParameters($old_style_constructor->getNumberOfOptionalParameters());
        }

        return $method;
    }

    /**
     * @param Context $context
     * The context in which the node appears
     *
     * @param CodeBase $code_base
     *
     * @param Decl $node
     * An AST node representing a method
     *
     * @return Method
     * A Method representing the AST node in the
     * given context
     */
    public static function fromNode(
        Context $context,
        CodeBase $code_base,
        Decl $node,
        FullyQualifiedMethodName $fqsen
    ) : Method {

        // Create the skeleton method object from what
        // we know so far
        $method = new Method(
            $context,
            (string)$node->name,
            new UnionType(),
            $node->flags ?? 0,
            $fqsen
        );

        // Parse the comment above the method to get
        // extra meta information about the method.
        $comment = Comment::fromStringInContext(
            $node->docComment ?? '',
            $context
        );

        // @var Parameter[]
        // The list of parameters specified on the
        // method
        $parameter_list =
            Parameter::listFromNode(
                $context,
                $code_base,
                $node->children['params']
            );

        // Add each parameter to the scope of the function
        foreach ($parameter_list as $parameter) {
            $method->getInternalScope()->addVariable(
                $parameter
            );
        }

        // If the method is Analyzable, set the node so that
        // we can come back to it whenever we like and
        // rescan it
        $method->setNode($node);

        // Set the parameter list on the method
        $method->setParameterList($parameter_list);
        // Keep an copy of the original parameter list, to check for fatal errors later on.
        $method->setRealParameterList($parameter_list);

        $method->setNumberOfRequiredParameters(array_reduce(
            $parameter_list,
            function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isRequired() ? 1 : 0));
            }, 0)
        );

        $method->setNumberOfOptionalParameters(array_reduce(
            $parameter_list, function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isOptional() ? 1 : 0));
            }, 0)
        );

        // Check to see if the comment specifies that the
        // method is deprecated
        $method->setIsDeprecated($comment->isDeprecated());

        // Set whether or not the element is internal to
        // the namespace.
        $method->setIsNSInternal($comment->isNSInternal());

        $method->setSuppressIssueList($comment->getSuppressIssueList());

        if ($method->getIsMagicCall() || $method->getIsMagicCallStatic()) {
            $method->setNumberOfOptionalParameters(999);
            $method->setNumberOfRequiredParameters(0);
        }

        // Add the syntax-level return type to the method's union type
        // if it exists
        $return_union_type = new UnionType;
        if($node->children['returnType'] !== null) {
            $return_union_type = UnionType::fromNode(
                $context,
                $code_base,
                $node->children['returnType']
            );
            $method->getUnionType()->addUnionType($return_union_type);
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
                    $comment_return_union_type->addUnionType(
                        $context->getClassFQSEN()->asUnionType()
                    );
                }
            }

            if (Config::get()->check_docblock_signature_return_type_match) {
                // Make sure that the commented type is a narrowed
                // or equivalent form of the syntax-level declared
                // return type.
                if (!$comment_return_union_type->isExclusivelyNarrowedFormOrEquivalentTo(
                        $return_union_type,
                        $context,
                        $code_base
                    )
                ) {
                    Issue::maybeEmit(
                        $code_base,
                        $context,
                        Issue::TypeMismatchDeclaredReturn,
                        $node->lineno ?? 0,
                        $comment_return_union_type->__toString(),
                        $return_union_type->__toString()
                    );
                }
            }

            $method->getUnionType()->addUnionType($comment_return_union_type);
        }

        // Add params to local scope for user functions
        FunctionTrait::addParamsToScopeOfFunctionOrMethod($context, $code_base, $node, $method, $comment);

        return $method;
    }

    /**
     * @param Context $context
     *
     * @return UnionType
     * The type of this method in its given context.
     */
    public function getUnionType() : UnionType
    {
        $union_type = parent::getUnionType();

        // If the type is 'static', add this context's class
        // to the return type
        if ($union_type->hasStaticType()) {
            $union_type = clone($union_type);
            $union_type->addType(
                $this->getFQSEN()->getFullyQualifiedClassName()->asType()
            );
        }

        // If the type is a generic array of 'static', add
        // a generic array of this context's class to the return type
        if ($union_type->genericArrayElementTypes()->hasStaticType()) {
            $union_type = clone($union_type);
            $union_type->addType(
                $this->getFQSEN()->getFullyQualifiedClassName()->asType()->asGenericArrayType()
            );
        }

        return $union_type;
    }

    /**
     * @return FullyQualifiedMethodName
     */
    public function getFQSEN() : FullyQualifiedMethodName {
        return $this->fqsen;
    }

    /**
     * @return \Generator
     * The set of all alternates to this method
     */
    public function alternateGenerator(CodeBase $code_base) : \Generator {
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
     * @return Method
     * The Method that this Method is overriding
     */
    public function getOverriddenMethod(
        CodeBase $code_base
    ) : Method {
        // Get the class that defines this method
        $class = $this->getClass($code_base);

        // Get the list of ancestors of that class
        $ancestor_class_list = $class->getAncestorClassList(
            $code_base
        );

        // Hunt for any ancestor class that defines a method with
        // the same name as this one
        foreach ($ancestor_class_list as $ancestor_class) {
            if ($ancestor_class->hasMethodWithName($code_base, $this->getName())) {
                return $ancestor_class->getMethodByName(
                    $code_base,
                    $this->getName()
                );
            }
        }

        // Throw an exception if this method doesn't override
        // anything
        throw new CodeBaseException(
            $this->getFQSEN(),
            "Method $this with FQSEN {$this->getFQSEN()} does not override another method"
        );
    }

    /**
     * @return string
     * A string representation of this method signature
     */
    public function __toString() : string {
        $string = '';

        $string .= 'function ';
        if ($this->returnsRef()) {
            $string .= '&';
        }
        $string .= $this->getName();

        $string .= '(' . implode(', ', $this->getParameterList()) . ')';

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
    public function toRealSignatureString() : string {
        $string = '';

        $string .= 'function ';
        if ($this->returnsRef()) {
            $string .= '&';
        }
        $string .= $this->getName();

        $string .= '(' . implode(', ', $this->getRealParameterList()) . ')';

        if (!$this->getRealReturnType()->isEmpty()) {
            $string .= ' : ' . (string)$this->getRealReturnType();
        }

        return $string;
    }
}
