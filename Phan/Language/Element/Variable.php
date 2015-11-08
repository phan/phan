<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Debug;
use \Phan\Deprecated;
use \Phan\Language\Context;
use \Phan\Language\UnionType;
use \ast\Node;

class Variable extends TypedStructuralElement {
    use \Phan\Language\AST;

    /**
     * @param \phan\Context $context
     * The context in which the structural element lives
     *
     * @param CommentElement $comment,
     * Any comment block associated with the class
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
        Comment $comment,
        string $name,
        UnionType $type,
        int $flags
    ) {
        parent::__construct(
            $context,
            $comment,
            $name,
            $type,
            $flags
        );
    }

    /**
     * @return Variable
     * A variable begotten from a node
     */
    public static function fromNodeInContext(
        Node $node,
        Context $context
    ) : Variable {

        $variable_name = self::astVariableName($node);

        // Get the type of the assignment
        $type =
            UnionType::fromNode($context, $node);

        return new Variable(
            $context
                ->withLineNumberStart($node->lineno ?? 0)
                ->withLineNumberEnd($node->endLineno ?? 0),
            Comment::fromString($node->docComment ?? ''),
            $variable_name,
            $type,
            $node->flags
        );
    }

    /**
     * @return bool
     * True if the variable with the given name is a
     * superglobal
     */
    public static function isSuperglobalVariableWithName(
        string $name
    ) : bool {
        return in_array($name, [
            '_GET',
            '_POST',
            '_COOKIE',
            '_REQUEST',
            '_SERVER',
            '_ENV',
            '_FILES',
            '_SESSION',
            'GLOBALS'
        ]);
    }

    public function __toString() : string {
        return "{$this->getUnionType()} {$this->getName()}";
    }

}
