<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Deprecated;
use \Phan\Language\Context;
use \Phan\Language\Type;
use \ast\Node;

class Variable extends TypedStructuralElement {

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
     * @param Type $type,
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
        Type $type,
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

        $variable_name = Deprecated::var_name($node);

        return new Variable(
            $context
                ->withLineNumberStart($node->lineno ?? 0)
                ->withLineNumberEnd($node->endLineno ?? 0),
            Comment::fromString($node->docComment ?? ''),
            $variable_name,
            Type::none(),
            $node->flags
        );
    }
}
