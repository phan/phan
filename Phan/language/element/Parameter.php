<?php
declare(strict_types=1);
namespace phan\element;

require_once(__DIR__.'/../AST.php');
require_once(__DIR__.'/TypedStructuralElement.php');

class ParameterElement extends TypedStructuralElement {

    /**
     * @var $def
     */
    private $def = '';

    /**
     * @param string $file
     * The path to the file in which the class is defined
     *
     * @param string $namespace,
     * The namespace of the class
     *
     * @param int $line_number_start,
     * The starting line number of the class within the $file
     *
     * @param int $line_number_end,
     * The ending line number of the class within the $file
     *
     * @param string $comment,
     * Any comment block associated with the class
     */
    public function __construct(
        string $file,
        string $namespace,
        int $line_number_start,
        int $line_number_end,
        string $comment,
        bool $is_conditional,
        int $flags,
        string $name,
        string $type
    ) {
        parent::__construct(
            $file,
            $namespace,
            $line_number_start,
            $line_number_end,
            $comment,
            $is_conditional,
            $flags,
            $name,
            $type
        );
    }

    /**
     *
     */
    public function setDef(string $def) {
        $this->def = $def;
    }

    /**
     * @return ParameterElement[]
     */
    public static function listFromAST(
        \phan\State $state,
        \ast\Node $node
    ) : array {
        if(!$node instanceof \ast\Node) {
            assert(false, ast_dump($node)." was not an \\ast\\Node");
        }

        return array_map(function(\ast\Node $child) use ($state) {
            return ParameterElement::fromAST($state, $child);
        }, $node->children);
    }

    /**
     *
     */
    public static function fromAST(
        \phan\State $state,
        \ast\Node $node
    ) : ParameterElement {
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


        if($node instanceof \ast\Node) {
            assert(false, "$node was not an \\ast\\Node");
        }

        $type = ast_node_type(
            $file,
            $node->children[0],
            $namespace
        );

        if(empty($type)
            && !empty($dc['params'][$i]['type'])
        ) {
            $type = $dc['params'][$i]['type'];
        }

        $parameter_element = new ParameterElement(
            $state->getFile(),
            $state->getNamespace(),
            $ast->lineno,
            $ast->endLineno,
            $ast->docComment,
            $state->getIsConditional(),
            $ast->flags,
            (string)$node->children[1],
            $type
        );

        if($node->children[2]!==null) {
            $parameter_element->setDef(
                $node->children[2]
            );
        }

        return $parameter_element;
    }

}
