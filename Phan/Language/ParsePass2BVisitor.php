<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\Element\{Clazz, Comment, Constant, Method, Property};
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Log;
use \ast\Node;

/**
 * The class is a visitor for AST nodes that does parsing. Each
 * visitor populates the $context->getCodeBase() with any
 * globally accessible structural elements and will return a
 * possibly new context as modified by the given node.
 *
 * # Example Usage
 * ```
 * $context =
 *     (new Element($node))->acceptKindVisitor(
 *         new ParseVisitor($context)
 *     );
 * ```
 */
class ParsePass2BVisitor extends KindVisitorImplementation {
    use \Phan\Language\AST;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    private $context;

    /**
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(Context $context) {
        $this->context = $context;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node) : Context {
        // Many nodes don't change the context and we
        // don't need to read them.
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssign(Node $node) : Context {

        /*
        if($ast->children[0] instanceof \ast\Node && $ast->children[0]->kind == \ast\AST_LIST) {
            // TODO: Very simplistic here - we can be smarter
            $rtype = node_type($file, $namespace, $ast->children[1], $current_scope, $current_class, $taint);
            $type = generics($rtype);
            foreach($ast->children[0]->children as $c) {
                $name = var_name($c);
                if(!empty($name)) add_var_scope($current_scope, $name, $type);
            }
            break;
        }

        var_assign($file, $namespace, $ast, $current_scope, $current_class, $vars);
        foreach($vars as $k=>$v) {
            if(empty($v)) $v = ['type'=>'', 'tainted'=>false, 'tainted_by'=>''];
            if(empty($v['type'])) $v['type'] = '';
            if(strpos($k, '::') === false) $cs = $current_scope;
            else $cs = 'global';  // Put static properties in the global scope TODO: revisit

            // Check if we are assigning something to $GLOBALS[key]
            if($k=='GLOBALS' && $ast->children[0]->kind == \ast\AST_DIM) {
                $temp = $ast;
                $depth=0;
                while($temp->children[0]->kind == \ast\AST_DIM) {
                    $depth++;
                    $temp=$temp->children[0];
                }
                // If the index is a simple scalar, set it in the global scope
                if(!empty($temp->children[1]) && !($temp->children[1] instanceof \ast\Node)) {
                    $cs = 'global';
                    $k = $temp->children[1];
                    if($depth==1) {
                        $taint = false;
                        $tainted_by = '';
                        $v['type'] = node_type($file, $namespace, $ast->children[1], $current_scope, $current_class, $taint);
                        $v['tainted'] = $taint;
                        $v['tainted_by'] = $tainted_by;
                    } else {
                        // This is a $GLOBALS['a']['b'] type of assignment
                        // TODO: track array content types
                        $v['type'] = 'array';
                        $v['tainted'] = false;
                        $v['tainted_by'] = '';
                    }
                }
            }
            if($k=='GLOBALS') break;
            add_var_scope($cs, $k, strtolower($v['type']));
            $scope[$cs]['vars'][$k]['tainted'] = $v['tainted'];
            $scope[$cs]['vars'][$k]['tainted_by'] = $v['tainted_by'];
        }
        */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitAssignRef(Node $node) : Context {
        return $this->visitAssign($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitList(Node $node) : Context {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIfElem(Node $node) : Context {
        /*
        // Just to check for errors in the expression
        node_type($file, $namespace, $ast->children[0], $current_scope, $current_class, $taint);
        */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitWhile(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitSwitch(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitSwitchCase(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitExprList(Node $node) : Context {
        return $this->visitIfElem($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDoWhile(Node $node) : Context {
        /*
        node_type($file, $namespace, $ast->children[1], $current_scope, $current_class, $taint);
         */
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitGlobal(Node $node) : Context {
        /*
        if(!array_key_exists($current_scope, $scope)) $scope[$current_scope] = [];
        if(!array_key_exists('vars', $scope[$current_scope])) $scope[$current_scope]['vars'] = [];
        $name = var_name($ast);
        if(empty($name)) break;
        if(!array_key_exists($name, $scope['global']['vars'])) {
            add_var_scope('global', $name, '');
        }
        $scope[$current_scope]['vars'][$name] = &$scope['global']['vars'][$name];
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitForeach(Node $node) : Context {
        /*
        // check the array, the key,value part was checked on in the non-DPS part above
        $type = node_type($file, $namespace, $ast->children[0], $current_scope, $current_class);
        if(type_scalar($type)) {
            Log::err(Log::ETYPE, "$type passed to foreach instead of array", $file, $ast->lineno);
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitStatic(Node $node) : Context {
        /*
        $name = var_name($ast);
        $type = node_type($file, $namespace, $ast->children[1], $current_scope, $current_class, $taint);
        if(!empty($name)) {
            add_var_scope($current_scope, $name, $type);
            $scope[$current_scope]['vars'][$name]['tainted'] = $taint;
            $scope[$current_scope]['vars'][$name]['tainted_by'] = $tainted_by;
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEcho(Node $node) : Context {
        return $this->visitPrint($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPrint(Node $node) : Context {
        /*
        $taint = false;
        $tainted_by = '';
        $type = node_type($file, $namespace, $ast->children[0], $current_scope, $current_class, $taint);
        if($type == 'array' || (strlen($type) > 2 && substr($type,-2)=='[]')) {
            Log::err(Log::ETYPE, "array to string conversion", $file, $ast->lineno);
        }
        if($taint) {
            if(empty($tainted_by)) {
                Log::err(Log::ETAINT, "possibly tainted output.", $file, $ast->lineno);
            } else {
                Log::err(Log::ETAINT, "possibly tainted output. Data tainted at $tainted_by", $file, $ast->lineno);
            }
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitVar(Node $node) : Context {
        /*
        if($parent_kind == \ast\AST_STMT_LIST) {
            Log::err(Log::ENOOP, "no-op variable", $file, $ast->lineno);
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitArray(Node $node) : Context {
        /*
        if($parent_kind == \ast\AST_STMT_LIST) {
            Log::err(Log::ENOOP, "no-op array", $file, $ast->lineno);
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitConst(Node $node) : Context {
        /*
        if($parent_kind == \ast\AST_STMT_LIST) {
            Log::err(Log::ENOOP, "no-op constant", $file, $ast->lineno);
        }
        */


        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClosure(Node $node) : Context {
        /*
        if($parent_kind == \ast\AST_STMT_LIST) {
            Log::err(Log::ENOOP, "no-op closure", $file, $ast->lineno);
        }
        */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitReturn(Node $node) : Context {
        /*
        // a return from within a trait context is meaningless
        if($current_class['flags'] & \ast\flags\CLASS_TRAIT) break;
        // Check if there is a return type on the current function
        if(!empty($current_function['oret'])) {
            $ret = $ast->children[0];
            if($ret instanceof \ast\Node) {
                #	if($ast->children[0]->kind == \ast\AST_ARRAY) $ret_type='array';
                #	else $ret_type = node_type($file, $namespace, $ret, $current_scope, $current_class);
                $ret_type = node_type($file, $namespace, $ret, $current_scope, $current_class);
            } else {
                $ret_type = type_map(gettype($ret));
                // This is distinct from returning actual NULL which doesn't hit this else since it is an AST_CONST node
                if($ret_type=='null') $ret_type='void';
            }
            $check_type = $current_function['oret'];
            if(strpos("|$check_type|",'|self|')!==false) {
                $check_type = preg_replace("/\bself\b/", $current_class['name'], $check_type);
            }
            if(strpos("|$check_type|",'|static|')!==false) {
                $check_type = preg_replace("/\bstatic\b/", $current_class['name'], $check_type);
            }
            if(strpos("|$check_type|",'|\$this|')!==false) {
                $check_type = preg_replace("/\b\$this\b/", $current_class['name'], $check_type);
            }
            if(!type_check(all_types($ret_type), all_types($check_type), $namespace)) {
                Log::err(Log::ETYPE, "return $ret_type but {$current_function['name']}() is declared to return {$current_function['oret']}", $file, $ast->lineno);
            }
        } else {
            $lcs = strtolower($current_scope);
            $type = node_type($file, $namespace, $ast->children[0], $current_scope, $current_class);
            if(!empty($functions[$lcs]['oret'])) { // The function has a return type declared
                if(!type_check(all_types($type), all_types($functions[$lcs]['oret']), $namespace)) {
                    Log::err(Log::ETYPE, "return $type but {$functions[$lcs]['name']}() is declared to return {$functions[$lcs]['oret']}", $file, $ast->lineno);
                }
            } else {
                if(strpos($current_scope, '::') !== false) {
                    list($class_name,$method_name) = explode('::',$current_scope,2);
                    $idx = find_method_class($class_name, $method_name);
                    if($idx) {
                        $classes[$idx]['methods'][strtolower($method_name)]['ret'] = merge_type($classes[$idx]['methods'][strtolower($method_name)]['ret'], strtolower($type));
                    }
                } else {
                    if(!empty($functions[$lcs]['ret'])) {
                        $functions[$lcs]['ret'] = merge_type($functions[$lcs]['ret'], $type);
                    } else {
                        if($current_scope != 'global') {
                            $functions[$lcs]['ret'] = $type;
                        }
                    }
                }
            }
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitClassConstDecl(Node $node) : Context {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitPropDecl(Node $node) : Context {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCall(Node $node) : Context {
        /*
        $found = false;
        $call = $ast->children[0];

        if($call->kind == \ast\AST_NAME) {
            $func_name = $call->children[0];
            $found = null;
            if($call->flags & \ast\flags\NAME_NOT_FQ) {
                if(!empty($namespace_map[T_FUNCTION][$file][strtolower($namespace.$func_name)])) {
                    $cs = $namespace_map[T_FUNCTION][$file][strtolower($namespace.$func_name)];
                    $found = $functions[strtolower($cs)];
                } else if(!empty($namespace_map[T_FUNCTION][$file][strtolower($func_name)])) {
                    $cs = $namespace_map[T_FUNCTION][$file][strtolower($func_name)];
                    $found = $functions[strtolower($cs)];
                } else if(!empty($functions[strtolower($namespace.$func_name)])) {
                    $cs = $namespace.$func_name;
                    $found = $functions[strtolower($cs)];
                } else if(!empty($functions[strtolower($func_name)])) {
                    $cs = $func_name;
                    $found = $functions[strtolower($func_name)];
                }
            } else {
                if(!empty($functions[strtolower($func_name)])) {
                    $cs = $func_name;
                    $found = $functions[strtolower($func_name)];
                }
            }
            if(!$found) Log::err(Log::EUNDEF, "call to undefined function {$func_name}()", $file, $ast->lineno);
            else {
                // Ok, the function exists, but are we calling it correctly?
                if($found instanceof ReflectionType) echo "oops at $file:{$ast->lineno}\n";  // DEBUG
                arg_check($file, $namespace, $ast, $func_name, $found, $current_scope, $current_class);
                if($found['file'] != 'internal') {
                    // re-check the function's ast with these args
                    if(!$quick_mode) pass2($found['file'], $found['namespace'], $found['ast'], $found['scope'], $ast, $current_class, $found, $parent_scope);
                } else {
                    if(!$found['avail']) {
                        if(!$found) Log::err(Log::EAVAIL, "function {$func_name}() is not compiled into this version of PHP", $file, $ast->lineno);
                    }
                }
            }
        } else if ($call->kind == \ast\AST_VAR) {
            $name = var_name($call);
            if(!empty($name)) {
            // $var() - hopefully a closure, otherwise we don't know
                if(array_key_exists($name, $scope[$current_scope]['vars'])) {
                    if(($pos=strpos($scope[$current_scope]['vars'][$name]['type'], '{closure '))!==false) {
                        $closure_id = (int)substr($scope[$current_scope]['vars'][$name]['type'], $pos+9);
                        $func_name = '{closure '.$closure_id.'}';
                        $found = $functions[$func_name];
                        arg_check($file, $namespace, $ast, $func_name, $found, $current_scope, $current_class);
                        if(!$quick_mode) pass2($found['file'], $found['namespace'], $found['ast'], $found['scope'], $ast, $current_class, $found, $parent_scope);
                    }
                }
            }
        }
        */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitNew(Node $node) : Context {
        /*
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
        if($class_name) {
            $method_name = '__construct';  // No type checking for PHP4-style constructors
            $method = find_method($class_name, $method_name);
            if($method) { // Found a constructor
                arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
                if($method['file'] != 'internal') {
                    // re-check the function's ast with these args
                    if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
                }
            }
        }
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitInstanceof(Node $node) : Context {
        /*
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
         */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitStaticCall(Node $node) : Context {
        /*
        $static_call_ok = false;
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope, $static_call_ok);
        if($class_name) {
            // The class is declared, but does it have the method?
            $method_name = $ast->children[1];
            $static_class = '';
            if($ast->children[0]->kind == \ast\AST_NAME) {
                $static_class = $ast->children[0]->children[0];
            }

            $method = find_method($class_name, $method_name, $static_class);
            if(is_array($method) && array_key_exists('avail', $method) && !$method['avail']) {
                Log::err(Log::EAVAIL, "method {$class_name}::{$method_name}() is not compiled into this version of PHP", $file, $ast->lineno);
            }
            if($method === false) {
                Log::err(Log::EUNDEF, "static call to undeclared method {$class_name}::{$method_name}()", $file, $ast->lineno);
            } else if($method != 'dynamic') {
                // Was it declared static?
                if(!($method['flags'] & \ast\flags\MODIFIER_STATIC)) {
                    if(!$static_call_ok) {
                        Log::err(Log::ESTATIC, "static call to non-static method {$class_name}::{$method_name}() defined at {$method['file']}:{$method['lineno']}", $file, $ast->lineno);
                    }
                }
                arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
                if($method['file'] != 'internal') {
                    // re-check the function's ast with these args
                    if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
                }
            }
        }
        */

        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitMethodCall(Node $node) : Context {
        /*
        $class_name = find_class_name($file, $ast, $namespace, $current_class, $current_scope);
        if($class_name) {
            $method_name = $ast->children[1];
            $method = find_method($class_name, $method_name);
            if($method === false) {
                Log::err(Log::EUNDEF, "call to undeclared method {$class_name}->{$method_name}()", $file, $ast->lineno);
            } else if($method != 'dynamic') {
                if(array_key_exists('avail', $method) && !$method['avail']) {
                    Log::err(Log::EAVAIL, "method {$class_name}::{$method_name}() is not compiled into this version of PHP", $file, $ast->lineno);
                }
                arg_check($file, $namespace, $ast, $method_name, $method, $current_scope, $current_class, $class_name);
                if($method['file'] != 'internal') {
                    // re-check the function's ast with these args
                    if(!$quick_mode) pass2($method['file'], $method['namespace'], $method['ast'], $method['scope'], $ast, $classes[strtolower($class_name)], $method, $parent_scope);
                }
            }
        }
         */

        return $this->context;
    }

}
