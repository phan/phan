<?php declare(strict_types=1);
namespace Phan\Language\AST\Visitor;

use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Type;
use \Phan\Language\Context;
use \Phan\Log;
use \ast\Node;

/**
 * A visitor that can extract a class name from a few
 * types of nodes
 */
class ClassNameKindVisitor extends KindVisitorImplementation {
    use \Phan\Language\AST;

    /**
     * @var $context
     * The context of the current execution
     */
    private $context;

    /**
     * @param Context $context
     * The context of the current execution
     */
    public function __construct(Context $context) {
        $this->context = $context;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     */
    public function visit(Node $node) : string {
        return '';
    }

    public function visitNew(Node $node) : string {
        if($node->children[0]->kind == \ast\AST_NAME) {
            $class_name = $node->children[0]->children[0];

            if($class_name == 'self'
                || $class_name == 'static'
                || $class_name == 'parent'
            ) {
                if (!$this->context->hasClassFQSEN()) {
                    Log::err(
                        Log::ESTATIC,
                        "Cannot access {$class_name}:: when no class scope is active",
                        $this->context->getFile(),
                        $node->lineno
                    );
                    return '';
                }

                if($class_name == 'static') {
                    $class_name = (string)$this->context->getClassFQSEN();
                } else if($class_name == 'self') {
                    // TODO
                    if ($this->context->isGlobalScope()) {
                        list($class_name,) = explode('::', $current_scope);
                    } else {
                        $class_name = (string)$this->context->getClassFQSEN();
                        // $class_name = $current_class['name'];
                    }
                } else if($class_name == 'parent') {
                    $class_name = $current_class['parent'];
                }

                $static_call_ok = true;
            } else {
                $class_name =
                    self::astQualifiedName(
                        $this->context,
                        $node->children[0]
                    );
                    // qualified_name($file, $node->children[0], $namespace);
            }
        }

        return $class_name;
    }

    public function visitStaticCall(Node $node) : string {
        return $this->visitNew($node);
    }

    public function visitClassConst(Node $node) : string {
        return $this->visitNew($node);
    }

    public function visitInstanceOf(Node $node) : string {
        if($node->children[1]->kind == \ast\AST_NAME) {
            return qualified_name($file, $node->children[1], $namespace);
        }

        return '';
    }

    public function visitMethodCall(Node $node) : string {
        if($node->children[0]->kind == \ast\AST_VAR) {
            if(!($node->children[0]->children[0] instanceof \ast\Node)) {
                // $var->method()
                if($node->children[0]->children[0] == 'this') {
                    if(!$current_class) {
                        Log::err(Log::ESTATIC, 'Using $this when not in object context', $file, $node->lineno);
                        return '';
                    }
                }
                if(empty($scope[$current_scope]['vars'][$node->children[0]->children[0]])) {
                    // Got lost, couldn't find the variable in the current scope
                    // If it really isn't defined, it will be caught by the undefined var error
                    return '';
                }
                $call = $scope[$current_scope]['vars'][$node->children[0]->children[0]]['type'];
                // Hack - loop through the possible types of the var and assume first found class is correct
                foreach(explode('|', nongenerics($call)) as $class_name) {
                    if(!empty($classes[strtolower($class_name)])) break;
                }
                if(empty($class_name)) return '';
                $class_name = $classes[strtolower($class_name)]['name'] ?? $class_name;
            }
        } else if($node->children[0]->kind == \ast\AST_PROP) {
            $prop = $node->children[0];
            if($prop->children[0]->kind == \ast\AST_VAR && !($prop->children[0]->children[0] instanceof \ast\Node)) {
                // $var->prop->method()
                $var = $prop->children[0];
                if($var->children[0] == 'this') {
                    if(!$current_class) {
                        Log::err(Log::ESTATIC, 'Using $this when not in object context', $file, $node->lineno);
                        return '';
                    }
                    if(!($prop->children[1] instanceof \ast\Node)) {
                        if(!empty($current_class['properties'][$prop->children[1]])) {
                            $prop = $current_class['properties'][$prop->children[1]];
                            foreach(explode('|', nongenerics($prop['type'])) as $class_name) {
                                if(!empty($classes[strtolower($class_name)])) break;
                            }
                            if(empty($class_name)) return '';
                            $class_name = $classes[strtolower($class_name)]['name'] ?? $class_name;
                        }
                    } else {
                        // $this->$prop->method() - too dynamic, give up
                        return '';
                    }
                }
            }
        }
    }

    public function visitProp(Node $node) : string {
        return $this->visitMethodCall($node);
    }

}
