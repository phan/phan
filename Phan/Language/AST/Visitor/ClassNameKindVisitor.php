<?php declare(strict_types=1);
namespace Phan\Language\AST\Visitor;

use \Phan\Debug;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\UnionType;
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

            if(in_array($class_name, ['self', 'static', 'parent'])) {
                if (!$this->context->isClassScope()) {
                    Log::err(
                        Log::ESTATIC,
                        "Cannot access {$class_name}:: when no class scope is active",
                        $this->context->getFile(),
                        $node->lineno
                    );
                    return '';
                }

                if($class_name == 'static') {
                    $class_name =
                        (string)$this->context->getClassFQSEN()->getClassName();
                } else if($class_name == 'self') {
                    // TODO
                    if ($this->context->isGlobalScope()) {
                        list($class_name,) = explode('::', $current_scope);
                    } else {
                        $class_name =
                            (string)$this->context->getClassFQSEN()->getClassName();
                    }
                } else if($class_name == 'parent') {
                    $clazz =
                        $this->context->getClassInScope();

                    $class_name = (string)$clazz->getParentClassFQSEN();
                }

                // TODO
                $static_call_ok = true;
            } else {
                // qualified_name($file, $node->children[0], $namespace);
                $class_name =
                    self::astQualifiedName(
                        $this->context,
                        $node->children[0]
                    );
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
        $class_name = '';

        if($node->children[0]->kind == \ast\AST_VAR) {
            if(!($node->children[0]->children[0] instanceof \ast\Node)) {
                // $var->method()
                if($node->children[0]->children[0] == 'this') {
                    if(!$this->context->isClassScope()) {
                        Log::err(
                            Log::ESTATIC,
                            'Using $this when not in object context',
                            $this->context->getFile(),
                            $node->lineno
                        );
                        return '';
                    }
                }

                $variable_name =
                    $node->children[0]->children[0];

                if (!$this->context->getScope()->hasVariableWithName(
                    $variable_name
                )) {
                    // Got lost, couldn't find the variable in the current scope
                    // If it really isn't defined, it will be caught by the undefined var error
                    return '';
                }

                $variable =
                    $this->context->getScope()->getVariableWithName($variable_name);

                // Hack - loop through the possible types of the var and assume
                // first found class is correct
                foreach($variable->getUnionType()->nonGenericTypes() as $type_name) {
                    if ($this->context->getCodeBase()->hasClassWithFQSEN(
                        $this->context->getScopeFQSEN()->withClassName(
                            $this->context,
                            (string)$type_name
                        )
                    )) {
                        break;
                    }
                }

                if(empty($type_name)) {
                    return '';
                }

                $class_fqsen = $this->context->getScopeFQSEN()->withClassName(
                    $this->context,
                    (string)$type_name
                );

                $class_name = $type_name;
                if ($this->context->getCodeBase()->hasClassWithFQSEN($class_fqsen)) {
                    $class_name =
                        $this->context->getCodeBase()->getClassByFQSEN($class_fqsen)->getName();
                }

                return $class_name;
            }
        } else if($node->children[0]->kind == \ast\AST_PROP) {
            $prop = $node->children[0];
            if($prop->children[0]->kind == \ast\AST_VAR && !($prop->children[0]->children[0] instanceof \ast\Node)) {
                // $var->prop->method()
                $var = $prop->children[0];
                if($var->children[0] == 'this') {
                    if(!$this->context->isClassScope()) {
                        Log::err(
                            Log::ESTATIC,
                            'Using $this when not in object context',
                            $this->context->getFile(),
                            $node->lineno
                        );

                        return '';
                    }

                    $clazz = $this->context->getCodeBase()->getClassByFQSEN(
                        $this->context->getClassFQSEN()
                    );

                    if(!($prop->children[1] instanceof Node)) {
                        $property_name = $prop->children[1];
                        if ($clazz->hasPropertyWithName($property_name)) {
                            $property =
                                $clazz->getPropertyWithName($property_name);

                            // Find the first viable property type
                            foreach ($property->getUnionType()->nongenerics() as $class_name) {
                                if ($this->context->getCodeBase()->hasClassWithFQSEN(
                                    $this->context->getScopeFQSEN()->withClassName(
                                        $this->context,
                                        $class_name
                                    )
                                )) {
                                    break;
                                }
                            }
                        }

                        /*
                        if(!empty($current_class['properties'][$prop->children[1]])) {
                            $prop = $current_class['properties'][$prop->children[1]];
                            foreach(explode('|', nongenerics($prop['type'])) as $class_name) {
                                if(!empty($classes[strtolower($class_name)]))
                                    break;
                            }
                            if(empty($class_name)) return '';
                            $class_name = $classes[strtolower($class_name)]['name'] ?? $class_name;
                        }
                         */
                    } else {
                        // $this->$prop->method() - too dynamic, give up
                        return '';
                    }
                }
            }
        }

        return $class_name;
    }

    public function visitProp(Node $node) : string {
        return $this->visitMethodCall($node);
    }

}
