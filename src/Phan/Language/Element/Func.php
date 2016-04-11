<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Scope\FunctionLikeScope;
use Phan\Language\Context;
use Phan\Language\Element\Parameter;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use ast\Node;
use ast\Node\Decl;

class Func extends AddressableElement implements FunctionInterface
{
    use \Phan\Analysis\Analyzable;
    use \Phan\Memoize;
    use FunctionTrait;
    use ClosedScopeElement;

    /**
     * @param \phan\Context $context
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
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedFunctionName $fqsen
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        $this->setInternalScope(new FunctionLikeScope(
            $context->getScope(), $fqsen
        ));
    }

    /**
     * @param Context $context
     * The context in which the node appears
     *
     * @param CodeBase $code_base
     *
     * @param Node $node
     * An AST node representing a function
     *
     * @param FQSEN $fqsen
     * A fully qualified name for the function
     *
     * @return Func
     * A Func representing the AST node in the
     * given context
     */
    public static function fromNode(
        Context $context,
        CodeBase $code_base,
        Decl $node,
        FQSEN $fqsen
    ) : Func {

        // Create the skeleton function object from what
        // we know so far
        $func = new Func(
            $context,
            (string)$node->name,
            new UnionType(),
            $node->flags ?? 0,
            $fqsen
        );

        // Parse the comment above the function to get
        // extra meta information about the function.
        $comment = Comment::fromStringInContext(
            $node->docComment ?? '',
            $context
        );

        // @var Parameter[]
        // The list of parameters specified on the
        // function
        $parameter_list = Parameter::listFromNode(
            $context,
            $code_base,
            $node->children['params']
        );

        // Add each parameter to the scope of the function
        foreach ($parameter_list as $parameter) {
            $func->getInternalScope()->addVariable(
                $parameter
            );
        }

        // If the function is Analyzable, set the node so that
        // we can come back to it whenever we like and
        // rescan it
        $func->setNode($node);

        // Set the parameter list on the function
        $func->setParameterList($parameter_list);

        $func->setNumberOfRequiredParameters(array_reduce(
            $parameter_list,
            function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isRequired() ? 1 : 0));
            }, 0)
        );

        $func->setNumberOfOptionalParameters(array_reduce(
            $parameter_list, function (int $carry, Parameter $parameter) : int {
                return ($carry + ($parameter->isOptional() ? 1 : 0));
            }, 0)
        );

        // Check to see if the comment specifies that the
        // function is deprecated
        $func->setIsDeprecated($comment->isDeprecated());

        $func->setSuppressIssueList(
            $comment->getSuppressIssueList()
        );

        // Take a look at function return types
        if($node->children['returnType'] !== null) {
            // Get the type of the parameter
            $union_type = UnionType::fromNode(
                $context,
                $code_base,
                $node->children['returnType']
            );

            $func->getUnionType()->addUnionType($union_type);
        }

        if ($comment->hasReturnUnionType()) {

            // See if we have a return type specified in the comment
            $union_type = $comment->getReturnType();

            assert(!$union_type->hasSelfType(),
                "Function referencing self in $context");

            $func->getUnionType()->addUnionType($union_type);
        }

        // Add params to local scope for user functions
        if(!$func->isInternal()) {

            $parameter_offset = 0;
            foreach ($func->getParameterList() as $i => $parameter) {
                if ($parameter->getUnionType()->isEmpty()) {
                    // If there is no type specified in PHP, check
                    // for a docComment with @param declarations. We
                    // assume order in the docComment matches the
                    // parameter order in the code
                    if ($comment->hasParameterWithNameOrOffset(
                        $parameter->getName(),
                        $parameter_offset
                    )) {
                        $comment_type =
                            $comment->getParameterWithNameOrOffset(
                                $parameter->getName(),
                                $parameter_offset
                            )->getUnionType();

                        $parameter->getUnionType()->addUnionType(
                            $comment_type
                        );
                    }
                }

                // If there's a default value on the parameter, check to
                // see if the type of the default is cool with the
                // specified type.
                if ($parameter->hasDefaultValue()) {
                    $default_type = $parameter->getDefaultValueType();

                    if (!$default_type->isEqualTo(
                        NullType::instance()->asUnionType()
                    )) {
                        if (!$default_type->isEqualTo(NullType::instance()->asUnionType())
                            && !$default_type->canCastToUnionType(
                                $parameter->getUnionType()
                        )) {
                            Issue::maybeEmit(
                                $code_base,
                                $context,
                                Issue::TypeMismatchDefault,
                                $node->lineno ?? 0,
                                (string)$parameter->getUnionType(),
                                $parameter->getName(),
                                (string)$default_type
                            );
                        }

                        $parameter->getUnionType()->addUnionType(
                            $default_type
                        );
                    }

                    // If we have no other type info about a parameter,
                    // just because it has a default value of null
                    // doesn't mean that is its type. Any type can default
                    // to null
                    if ((string)$default_type === 'null'
                        && !$parameter->getUnionType()->isEmpty()
                    ) {
                        $parameter->getUnionType()->addType(
                            NullType::instance()
                        );
                    }
                }

                ++$parameter_offset;
            }

        }

        return $func;
    }

    /**
     * @return FullyQualifiedFunctionName
     */
    public function getFQSEN() : FullyQualifiedFunctionName {
        return $this->fqsen;
    }

    /**
     * @return Func[]|\Generator
     * The set of all alternates to this function
     */
    public function alternateGenerator(CodeBase $code_base) : \Generator {
        $alternate_id = 0;
        $fqsen = $this->getFQSEN();

        while ($code_base->hasFunctionWithFQSEN($fqsen)) {
            yield $code_base->getFunctionByFQSEN($fqsen);
            $fqsen = $fqsen->withAlternateId(++$alternate_id);
        }
    }

    /**
     * @return string
     * A string representation of this function signature
     */
    public function __toString() : string {
        $string = '';

        $string .= 'function ' . $this->getName();

        $string .= '(' . implode(', ', $this->getParameterList()) . ')';

        if (!$this->getUnionType()->isEmpty()) {
            $string .= ' : ' . (string)$this->getUnionType();
        }

        $string .= ';';

        return $string;
    }

}
