<?php
declare(strict_types=1);

namespace Phan;

class Deprecated {
    use \Phan\Language\AST;

    public static function bc_check($file, $ast) {
        if($ast->children[0] instanceof \ast\Node) {
            if($ast->children[0]->kind == \ast\AST_DIM) {
                $temp = $ast->children[0]->children[0];
                $last = $temp;
                if($temp->kind == \ast\AST_PROP || $temp->kind == \ast\AST_STATIC_PROP) {
                    while($temp instanceof \ast\Node && ($temp->kind == \ast\AST_PROP || $temp->kind == \ast\AST_STATIC_PROP)) {
                        $last = $temp;
                        $temp = $temp->children[0];
                    }
                    if($temp instanceof \ast\Node) {
                        if(($last->children[1] instanceof \ast\Node && $last->children[1]->kind == \ast\AST_VAR) && ($temp->kind == \ast\AST_VAR || $temp->kind == \ast\AST_NAME)) {
                            $ftemp = new \SplFileObject($file);
                            $ftemp->seek($ast->lineno-1);
                            $line = $ftemp->current();
                            unset($ftemp);
                            if(strpos($line,'}[') === false || strpos($line,']}') === false || strpos($line,'>{') === false) {
                                Log::err(Log::ECOMPAT, "expression may not be PHP 7 compatible", $file, $ast->lineno);
                            }
                        }
                    }
                }
            }
        }
    }

    public static function node_namelist_deprecated(
        $file,
        $node,
        $namespace
    ) : array {
        $result = [];
        if($node instanceof \ast\Node) {
            foreach($node->children as $name_node) {
                $result[] = qualified_name($file, $name_node, $namespace);
            }
        }
        return $result;
    }

    /**
     * @return
     * Takes "a|b" and returns "a[]|b[]"
     */
    public static function mkgenerics(string $str) : string {
        $ret = [];
        foreach(explode('|', $str) as $type) {
            if(empty($type)) continue;
            if($type=='array'
                || $type=='mixed'
                || strpos($type,'[]')!==false
            ) {
                $ret[] = 'array';
            }
            else {
                $ret[] = $type.'[]';
            }
        }

        return implode('|', $ret);
    }

}
