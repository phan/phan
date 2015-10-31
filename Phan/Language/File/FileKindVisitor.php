<?php declare(strict_types=1);
namespace Phan\Language\File;

use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Context;
use \Phan\Language\Element\Clazz;
use \Phan\Language\Element\Comment;
use \Phan\Language\Element\Constant;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Property;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;
use \Phan\Log;
use \ast\Node;

class FileKindVisitor extends KindVisitorImplementation {
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
     */
    public function visit(Node $node) {
        // Many nodes don't change the context and we
        // don't need to read them.
        return $this->context;
    }

    public function visitNamespace(Node $node) : Context {
        return $this->context->withNamespace(
            (string)$node->children[0].'\\'
        );
    }

    public function visitIf(Node $node) : Context {
        $context = $this->context->withIsConditional(true);
        $context->getCodeBase()->incrementConditionals();
        return $context;
    }

    public function visitDim(Node $node) : Context {
        if (!Configuration::instance()->bc_checks) {
            return $this->context;
        }

        if(!($node->children[0] instanceof \ast\Node
            && $node->children[0]->children[0] instanceof \ast\Node)
        ) {
            return $this->context;
        }

        // check for $$var[]
        if($node->children[0]->kind == \ast\AST_VAR
            && $node->children[0]->children[0]->kind == \ast\AST_VAR
        ) {
            $temp = $node->children[0]->children[0];
            $depth = 1;
            while($temp instanceof \ast\Node) {
                $temp = $temp->children[0];
                $depth++;
            }
            $dollars = str_repeat('$',$depth);
            $ftemp = new \SplFileObject($file);
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            unset($ftemp);
            if(strpos($line,'{') === false
                || strpos($line,'}') === false
            ) {
                Log::err(
                    Log::ECOMPAT,
                    "{$dollars}{$temp}[] expression may not be PHP 7 compatible",
                    $file,
                    $node->lineno
                );
            }
        }

        // $foo->$bar['baz'];
        else if(!empty($node->children[0]->children[1])
            && ($node->children[0]->children[1] instanceof \ast\Node)
            && ($node->children[0]->kind == \ast\AST_PROP)
            && ($node->children[0]->children[0]->kind == \ast\AST_VAR)
            && ($node->children[0]->children[1]->kind == \ast\AST_VAR)
        ) {
            $ftemp = new \SplFileObject($file);
            $ftemp->seek($node->lineno-1);
            $line = $ftemp->current();
            unset($ftemp);
            if(strpos($line,'{') === false
                || strpos($line,'}') === false
            ) {
                Log::err(
                    Log::ECOMPAT,
                    "expression may not be PHP 7 compatible",
                    $file,
                    $node->lineno
                );
            }
        }

        return $this->context;
    }

    public function visitUse(Node $node) : Context {
        $context = $this->context;

        foreach($node->children as $elem) {
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

            $context = $context->withNamespaceMap(
                $node->flags, $alias, $target
            );
        }

        return $context;
    }

    public function visitClass(Node $node) : Context {
        // Get an FQSEN for this class
        $class_name = $node->name;

        $class_fqsen = FQSEN::fromContext($this->context)
            ->withClassName($class_name);

        // Hunt for an available alternate ID if necessary
        $alternate_id = 0;
        while($this->context->getCodeBase()->classExists($class_fqsen)) {
            $class_fqsen = $class_fqsen->withAlternateId(
                ++$alternate_id
            );
        }

        // Update the context to signal that we're now
        // within a class context.
        $context = $this->context->withClassFQSEN($class_fqsen);

        if(!empty($node->children[0])) {
            $parent_class_name = $node->children[0]->children[0];

            if($node->children[0]->flags & \ast\flags\NAME_NOT_FQ) {
                if(($pos = strpos($parent,'\\')) !== false) {

                    if ($context->hasNamespaceMapFor(
                        T_CLASS,
                        substr($parent, 0, $pos)
                    )) {
                        $parent_class_name =
                            $context->getNamespaceMapfor(
                                T_CLASS,
                                substr($parent, 0, $pos)
                            );
                    }
                }
            }
        }

        if(!empty($node->children[0])) {
            $parent = $node->children[0]->children[0];
            if($node->children[0]->flags & \ast\flags\NAME_NOT_FQ) {
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

        $current_clazz = new Clazz(
            $context
                ->withLineNumberStart($node->lineno)
                ->withLineNumberEnd($node->endLineno ?: -1),
            Comment::fromString($node->docComment ?: ''),
            $node->name,
            new Type([$node->name]),
            $node->flags
        );

        $this->context->getCodeBase()->addClass($current_clazz);
        $this->context->getCodeBase()->incrementClasses();

        return $context;
    }

    public function visitUseTrait(Node $node) : Context {
        $clazz = $this->getContextClass();

        // TODO
        $trait_name_list =
            node_namelist(
                $this->context->getFile(),
                $node->children[0],
                $this->context->getNamespace()
            );

        foreach ($trait_name_list as $trait_name) {
            $clazz->addTraintFQSEN(
                FQSEN::fromContext(
                    $this->context
                )->withClassName($trait_name)
            );
        }

        $this->context->getCodeBase()->incrementTraits();

        return $this->context;
    }

    public function visitMethod(Node $node) : Context {
        $clazz = $this->getContextClass();

        $method_name = $node->name;

        $method_fqsen = FQSEN::fromContext(
            $this->context
        )->withMethodName($method_name);

        // Hunt for an available alternate ID if necessary
        $alternate_id = 0;
        while($clazz->hasMethodWithFQSEN($method_fqsen)) {
            $method_fqsen = $method_fqsen->withAlternateId(
                ++$alternate_id
            );
        }

        $method =
            new Method(
                $this->context
                    ->withLineNumberStart($node->lineno ?: 0)
                    ->withLineNumberEnd($node->endLineno ?? -1),
                Comment::fromString($node->docComment ?: ''),
                $method_name,
                Type::none(),
                0, // flags
                0, // number_of_required_parameters
                0  // number_of_optional_parameters
            );

        $clazz->addMethod($method);
        $this->context->getCodeBase()->incrementMethods();

        $context = $this->context->withMethodFQSEN(
            $method->getFQSEN()
        );

        if ('__construct' == $method_name) {
            $clazz->setIsParentConstructorCalled(false);
        }

        if ('__invoke' == $method_name) {
            $clazz->getType()->addTypeName('callable');
        }

        return $context;
    }

    public function visitPropDecl(Node $node) : Context {
        if(!$this->context->hasClassFQSEN()) {
            Log::err(
                Log::EFATAL,
                "Invalid property declaration",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $comment = Comment::fromString($node->docComment ?? '');

        foreach($node->children as $i=>$node) {
            // Ignore children which are not property elements
            if (!$node || $node->kind != \ast\AST_PROP_ELEM) {
                continue;
            }

            // @var Type
            $type = Type::typeFromNode(
                $this->context,
                $node->children[1]
            );

            $property_name = $node->children[0];

            assert(is_string($property_name),
                'Property name must be a string. '
                . 'Got '
                . print_r($property_name, true)
                . ' at '
                . $this->context);

            $clazz = $this->getContextClass();

            $clazz->addProperty(
                new Property(
                    $this->context
                        ->withLineNumberStart($node->lineno)
                        ->withLineNumberEnd($node->endLineno ?? -1),
                    Comment::fromString($node->docComment ?? ''),
                    is_string($node->children[0])
                    ? $node->children[0]
                    : '_error_',
                    $type,
                    $node->flags
                )
            );

            // TODO
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
                $property_name = $node->children[0];

                assert(is_string($property_name),
                    'Property name must be a string. Got '
                    . print_r($property_name, true)
                    . ' at '
                    . $this->context->__toString());

                if ($clazz->hasPropertyWithName($property_name)) {
                    $property =
                        $clazz->getPropertyWithName($property_name);
                    $property->setDType(Type::none());
                    $property->setType($type);
                }
            }
        }

        // TODO
        $done = true;

        return $this->context;
    }

    public function visitClassConstDecl(Node $node) : Context {
        if(!$this->context->hasClassFQSEN()) {
            Log::err(
                Log::EFATAL,
                "Invalid constant declaration",
                $this->context->getFile(),
                $node->lineno
            );
        }

        $clazz = $this->getContextClass();

        foreach($node->children as $node) {
            $clazz->addConstant(
                new Constant(
                    $this->context
                        ->withLineNumberStart($node->lineno ?? 0)
                        ->withLineNumberEnd($node->endLineno ?? 0),
                    Comment::fromString($node->docComment ?? ''),
                    $node->children[0],
                    Type::typeFromNode(
                        $this->context,
                        $node->children[1]
                    ),
                    0
                )
            );
        }

        // TODO
        $done = true;

        return $this->context;
    }

    public function visitFuncDecl(Node $node) : Context {
        $function_name =
            strtolower($this->context->getNamespace() . $node->name);

        // TODO
        if(!empty($functions[$function_name])) {
            for($i=1;;$i++) {
                if(empty($functions[$i.":".$function_name])) break;
            }
            $function_name = $i.':'.$function_name;
        }

        $this->context->getCodeBase()->addMethod(
            Method::fromAST(
                $context
                    ->withLineNumberStart($node->lineno ?? 0)
                    ->withLineNumberEnd($node->endLineno ?? 0),
                $node
            )
        );

        $this->context->getCodeBase()->incrementFunctions();

        // TODO
        // $context->setFunctionName($function_name);
        // $context->setScope($function_name);

        // Not $done=true here since nested function declarations are allowed

        return $context;
    }

    public function visitClosure(Node $node) : Context {
        $this->context->getCodeBase()->incrementClosures();

        // TODO
        $current_scope = "{closure}";

        return $this->context;
    }

    public function visitCall(Node $node) : Context {
        $found = false;
        $call = $node->children[0];
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
        if(Configuration::instance()->bc_checks) {
            \Phan\Deprecated::bc_check(
                $this->context->getFile(),
                $node
            );
        }

        return $this->context;
    }

    public function visitStaticCall(Node $node) : Context {
        $call = $node->children[0];
        if($call->kind == \ast\AST_NAME) {
            $func_name = strtolower($call->children[0]);
            if($func_name == 'parent') {
                $meth = strtolower($node->children[1]);
                if($meth == '__construct') {
                    $classes[strtolower($current_class)]['pc_called'] = true;
                }
            }
        }

        return $this->context;
    }

    public function visitReturn(Node $node) : Context {
        if (Configuration::instance()->bc_checks) {
            Deprecated::bc_check($this->context->getFile(), $node);
        }

        return $this->context;
    }

    public function visitPrint(Node $node) : Context {
        return $this->visitReturn($node);
    }

    public function visitEcho(Node $node) : Context {
        return $this->visitReturn($node);
    }

    public function visitMethodCall(Node $node) : Context {
        return $this->visitReturn($node);
    }

    /**
     * @return Clazz
     * Get the class on this scope, or fail real hard
     */
    private function getContextClass() : Clazz {
        assert($this->context->hasClassFQSEN(),
            "Must be in class context to use a trait");

        $class_fqsen = $this->context->getClassFQSEN();

        $clazz = $this->context->getCodeBase()->getClassByFQSEN(
            $class_fqsen
        );

        return $clazz;
    }
}
