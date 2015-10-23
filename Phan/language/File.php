<?php
declare(strict_types=1);
namespace phan;

require_once(__DIR__.'/CodeBase.php');
require_once(__DIR__.'/Options.php');
require_once(__DIR__.'/State.php');
require_once(__DIR__.'/element/MethodElement.php');

class File {

    /**
     * @var CodeBase
     */
    private $code_base = null;

    /**
     * @var string
     */
    private $file = null;

    /**
     * @var \ast
     */
    private $ast = null;

    /**
     * @param string $file
     */
    public function __construct(
        CodeBase $code_base,
        string $file
    ) {
        $this->code_base = $code_base;
        $this->file = $file;
        $this->ast = \ast\parse_file($file);
    }

    /**
     * @return string
     * The namespace of the file
     */
    public function passOne() {
        return $this->passOneRecursive(
            $this->ast,
            new State()
        );
    }

    /**
     * @param \ast\Node $ast
     *
     * @param State $state
     *
     * @return string
     * The namespace of the file
     */
    public function passOneRecursive(
        \ast\Node $ast,
        State $state
    ) : State {
        $done = false;

        switch($ast->kind) {
            case \ast\AST_NAMESPACE:
                $state->setNamespace(
                    (string)$ast->children[0].'\\'
                );
                break;

            case \ast\AST_IF:
                $state->setIsConditional(true);
                $this->code_base->incrementConditionals();
                break;

            case \ast\AST_DIM:
                if (!Options::instance()->isEnabledBCChecks()) {
                    break;
                }

                if(!($ast->children[0] instanceof \ast\Node
                    && $ast->children[0]->children[0] instanceof \ast\Node)
                ) {
                    break;
                }

                // check for $$var[]
                if($ast->children[0]->kind == \ast\AST_VAR
                    && $ast->children[0]->children[0]->kind == \ast\AST_VAR
                ) {
                    $temp = $ast->children[0]->children[0];
                    $depth = 1;
                    while($temp instanceof \ast\Node) {
                        $temp = $temp->children[0];
                        $depth++;
                    }
                    $dollars = str_repeat('$',$depth);
                    $ftemp = new \SplFileObject($file);
                    $ftemp->seek($ast->lineno-1);
                    $line = $ftemp->current();
                    unset($ftemp);
                    if(strpos($line,'{') === false
                        || strpos($line,'}') === false
                    ) {
                        Log::err(
                            Log::ECOMPAT,
                            "{$dollars}{$temp}[] expression may not be PHP 7 compatible",
                            $file,
                            $ast->lineno
                        );
                    }
                }

                // $foo->$bar['baz'];
                else if(!empty($ast->children[0]->children[1]) && ($ast->children[0]->children[1] instanceof \ast\Node) && ($ast->children[0]->kind == \ast\AST_PROP) &&
                        ($ast->children[0]->children[0]->kind == \ast\AST_VAR) && ($ast->children[0]->children[1]->kind == \ast\AST_VAR)) {
                    $ftemp = new \SplFileObject($file);
                    $ftemp->seek($ast->lineno-1);
                    $line = $ftemp->current();
                    unset($ftemp);
                    if(strpos($line,'{') === false
                        || strpos($line,'}') === false
                    ) {
                        Log::err(
                            Log::ECOMPAT,
                            "expression may not be PHP 7 compatible",
                            $file,
                            $ast->lineno
                        );
                    }
                }

            case \ast\AST_USE:
                foreach($ast->children as $elem) {
                    $target = $elem->children[0];
                    if(empty($elem->children[1])) {
                        if(($pos=strrpos($target, '\\'))!==false) {
                            $alias = substr($target, $pos + 1);
                        } else {
                            $alias = $target;
                        }
                    } else {
                        $alias = $elem->children[1];
                    }

                    $state->setNamespaceMap(
                        $ast->flags, $file, $alias, $target
                    );
                }
                break;

            case \ast\AST_CLASS:
                if(!empty($classes[strtolower($namespace.$ast->name)])) {
                    for($i=1;;$i++) {
                        if(empty($classes[$i.":".strtolower($namespace.$ast->name)])) break;
                    }
                    $state->setClassName(
                        $i.":".$namespace.$ast->name
                    );
                } else {
                    $state->setClassName(
                        $current_class = $namespace.$ast->name
                    );
                }
                $lc = $state->getCanonicalClassName();
                if(!empty($ast->children[0])) {
                    $parent = $ast->children[0]->children[0];
                    if($ast->children[0]->flags & \ast\flags\NAME_NOT_FQ) {
                        if(($pos = strpos($parent,'\\')) !== false) {
                            // extends A\B
                            // check if we have a namespace alias for A
                            if(!empty($namespace_map[T_CLASS][$file][strtolower(substr($parent,0,$pos))])) {
                                $parent = $namespace_map[T_CLASS][$file][strtolower(substr($parent,0,$pos))] . substr($parent,$pos);
                                goto done;
                            }
                        }
                        $parent = $namespace_map[T_CLASS][$file][strtolower($parent)] ?? $namespace.$parent;
                        done:
                    }
                } else {
                    $parent = null;
                }

                $class_element = new ClassElement(
                    $this->file, // or $state->getFile()
                    $state->getNamespace(),
                    $ast->lineno,
                    $ast->endLineno,
                    $ast->docComment,
                    $state->getIsConditional(),
                    $ast->flags,
                    $state->getNamespace() . $ast->name,
                    '',
                    []
                );

                $this->code_base->addClassElement($class_element);

                /*
                $classes[$lc] = [
                    'file'		 => $file,
                    'namespace'	 => $namespace,
                    'conditional'=> $is_conditional,
                    'flags'		 => $ast->flags,
                    'lineno'	 => $ast->lineno,
                    'endLineno'  => $ast->endLineno,
                    'name'		 => $namespace.$ast->name,
                    'docComment' => $ast->docComment,
                    'parent'	 => $parent,
                    'pc_called'  => true,
                    'type'	     => '',
                    'properties' => [],
                    'constants'  => [],
                    'traits'	 => [],
                    'interfaces' => [],
                    'methods'	 => []
                ];
                 */

                $classes[$lc]['interfaces'] = array_merge(
                    $classes[$lc]['interfaces'],
                    node_namelist($file, $ast->children[1], $namespace)
                );

                $this->code_base->incrementClasses();

                break;

            case \ast\AST_USE_TRAIT:
                $classes[$lc]['traits'] =
                    array_merge($classes[$lc]['traits'], node_namelist($file, $ast->children[0], $namespace));
                $this->code_base->incrementTraits();
                break;

            case \ast\AST_METHOD:
                if(!empty($classes[$lc]['methods'][strtolower($ast->name)])) {
                    for($i=1;;$i++) {
                        if(empty($classes[$lc]['methods'][$i.':'.strtolower($ast->name)])) break;
                    }
                    $method = $i.':'.$ast->name;
                } else {
                    $method = $ast->name;
                }
                $classes[$lc]['methods'][strtolower($method)] =
                    MethodElement::fromAST(
                        $this->file,
                        $is_conditional,
                        $ast,
                        "{$current_class}::{$method}",
                        $current_class,
                        $namespace
                    );
                $this->code_base->incrementMethods();
                $current_function = $method;
                $current_scope = "{$current_class}::{$method}";
                if($method == '__construct') $classes[$lc]['pc_called'] = false;
                if($method == '__invoke') $classes[$lc]['type'] = merge_type($classes[$lc]['type'], 'callable');
                break;

            case \ast\AST_PROP_DECL:
                if(empty($current_class)) Log::err(Log::EFATAL, "Invalid property declaration", $file, $ast->lineno);
                $dc = null;
                if(!empty($ast->docComment)) $dc = parse_doc_comment($ast->docComment);

                foreach($ast->children as $i=>$node) {
                    $classes[$lc]['properties'][$node->children[0]] = [ 'flags'=>$ast->flags,
                                                                        'name'=>$node->children[0],
                                                                        'lineno'=>$node->lineno];
                    $type = node_type($file, $namespace, $node->children[1], $current_scope, empty($classes[$lc]) ? null : $classes[$lc]);
                    if(!empty($dc['vars'][$i]['type'])) {
                        if($type !=='null' && !type_check($type, $dc['vars'][$i]['type'])) {
                            Log::err(Log::ETYPE, "property is declared to be {$dc['vars'][$i]['type']} but was assigned $type", $file, $node->lineno);
                        }
                        // Set the declarted type to the doc-comment type and add |null if the default value is null
                        $classes[$lc]['properties'][$node->children[0]]['dtype'] = $dc['vars'][$i]['type'] . (($type==='null')?'|null':'');
                        $classes[$lc]['properties'][$node->children[0]]['type'] = $dc['vars'][$i]['type'];
                        if(!empty($type) && $type != $classes[$lc]['properties'][$node->children[0]]['type']) {
                            $classes[$lc]['properties'][$node->children[0]]['type'] = merge_type($classes[$lc]['properties'][$node->children[0]]['type'], strtolower($type));
                        }
                    } else {
                        $classes[$lc]['properties'][$node->children[0]]['dtype'] = '';
                        $classes[$lc]['properties'][$node->children[0]]['type'] = $type;
                    }
                }
                $done = true;
                break;

            case \ast\AST_CLASS_CONST_DECL:
                if(empty($current_class)) Log::err(Log::EFATAL, "Invalid constant declaration", $file, $ast->lineno);

                foreach($ast->children as $node) {
                    $classes[$lc]['constants'][$node->children[0]] = [
                    'name'=>$node->children[0],
                    'lineno'=>$node->lineno,
                    'type'=>node_type($file, $namespace, $node->children[1], $current_scope, empty($classes[$lc]) ? null : $classes[$lc])
                    ];
                }
                $done = true;
                break;

            case \ast\AST_FUNC_DECL:
                $function_name = strtolower($state->getNamespace() . $ast->name);
                if(!empty($functions[$function_name])) {
                    for($i=1;;$i++) {
                        if(empty($functions[$i.":".$function_name])) break;
                    }
                    $function_name = $i.':'.$function_name;
                }

                $this->code_base->addMethodElement(
                    element\MethodElement::fromAST(
                        $this->file,
                        $state->getIsConditional(),
                        $ast,
                        $state->getScope(),
                        $state->getClassName(),
                        $state->getNamespace()
                    )
                );

                /*
                $functions[$function_name] =
                    node_func(
                        $file,
                        $is_conditional,
                        $ast,
                        $function,
                        $current_class,
                        $namespace
                    );
                 */

                $this->code_base->incrementFunctions();
                $state->setFunctionName($function_name);
                $state->setScope($function_name);

                // Not $done=true here since nested function declarations are allowed
                break;

            case \ast\AST_CLOSURE:
                $this->code_base->incrementClosures();
                $current_scope = "{closure}";
                break;

            case \ast\AST_CALL: // Looks odd to check for AST_CALL in pass1, but we need to see if a function calls func_get_arg/func_get_args/func_num_args
                $found = false;
                $call = $ast->children[0];
                if($call->kind == \ast\AST_NAME) {
                    $func_name = strtolower($call->children[0]);
                    if($func_name == 'func_get_args' || $func_name == 'func_get_arg' || $func_name == 'func_num_args') {
                        if(!empty($current_class)) {
                            $classes[$lc]['methods'][strtolower($current_function)]['optional'] = 999999;
                        } else {
                            $functions[strtolower($current_function)]['optional'] = 999999;
                        }
                    }
                }
                if($bc_checks) bc_check($file, $ast);
                break;

            case \ast\AST_STATIC_CALL: // Indicate whether a class calls its parent constructor
                $call = $ast->children[0];
                if($call->kind == \ast\AST_NAME) {
                    $func_name = strtolower($call->children[0]);
                    if($func_name == 'parent') {
                        $meth = strtolower($ast->children[1]);
                        if($meth == '__construct') {
                            $classes[strtolower($current_class)]['pc_called'] = true;
                        }
                    }
                }
                break;

            case \ast\AST_RETURN:
            case \ast\AST_PRINT:
            case \ast\AST_ECHO:
            case \ast\AST_STATIC_CALL:
            case \ast\AST_METHOD_CALL:
                if($bc_checks) {
                    bc_check($file, $ast);
                }
                break;
        }

        if(!$done) {
            foreach($ast->children as $child) {
                if ($child instanceof \ast\Node) {
                    $child_state =
                        $this->passOneRecursive(
                            $child,
                            $state
                        );

                    $state->setNamespace($child_state->getNamespace());
                }
            }
        }

        return $state;
    }

}
