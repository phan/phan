<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\Type;

class Parameter extends TypedStructuralElement {

    /**
     * @var $def
     */
    private $def = '';

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
     * @param string $def
     * ?
     *
     * @return null
     */
    public function setDef($def) {
        $this->def = $def;
    }

    /**
     * @return bool
     * True if 'def' is defined.
     */
    public function hasDef() : bool {
        return !empty($this->def);
    }

    /**
     * @return string
     * The 'def' paramter
     */
    public function getDef() {
        return $this->def;
    }

    /**
     * @return ParameterElement[]
     */
    public static function listFromAST(
        Context $context,
        \ast\Node $node
    ) : array {

        if(!$node instanceof \ast\Node) {
            assert(false, ast_dump($node)." was not an \\ast\\Node");
        }

        return array_map(function(\ast\Node $child) use ($context) {
            return Parameter::fromAST($context, $child);
        }, $node->children);
    }

    /**
     *
     */
    public static function fromAST(
        Context $context,
        \ast\Node $node
    ) : Parameter {
        /*
        $result[] = node_param($file, $param_node, $dc, $i, $namespace);
        if($param_node->children[2]===null) {
            if($opt) {
                Log::err(Log::EPARAM, "required arg follows optional", $file, $node->lineno);
            }
            $req++;
        } else $opt++;
        $i++;
         */

        assert($node instanceof \ast\Node,
            "node was not an \\ast\\Node");

        /*
        $type = ast_node_type(
            $file,
            $node->children[0],
            $context->getNamespace()
        );
         */

        $taint = false;
        $type = Type::typeFromSimpleNode(
            $context,
            $node->children[0],
            $taint
        );

        if(empty($type)
            && !empty($dc['params'][$i]['type'])
        ) {
            $type = $dc['params'][$i]['type'];
        }

        $parameter = new Parameter(
            $context,
            Comment::fromString($node->docComment ?? ''),
            (string)$node->children[1],
            $type,
            $node->flags
        );

        if($node->children[2] !== null) {
            $parameter->setDef(
                $node->children[2]
            );
        }

        return $parameter;
    }

}
