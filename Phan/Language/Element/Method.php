<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\Element\Comment;
use \Phan\Language\Element\Parameter;
use \Phan\Language\FQSEN;
use \Phan\Language\Type;

class Method extends TypedStructuralElement {

    /**
     * @var int
     */
    private $number_of_required_parameters = 0;

    /**
     * @var int
     */
    private $number_of_optional_parameters = 0;

    /**
     * @var
     */
    private $parameter_list = [];

    /**
     * @var ?
     */
    private $original_return_type = null;

    /**
     * @var ?
     */
    private $ret = null;

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
     *
     * @param int $number_of_required_paramters
     *
     * @param int $number_of_optional_parameters
     */
    public function __construct(
        Context $context,
        Comment $comment,
        string $name,
        Type $type,
        int $flags,
        int $number_of_required_parameters,
        int $number_of_optional_parameters
    ) {
        parent::__construct(
            $context,
            $comment,
            $name,
            $type,
            $flags
        );

        $this->number_of_required_parameters =
            $number_of_required_parameters;

        $this->number_of_optional_parameters =
            $number_of_optional_parameters;
    }

    /**
     * @return map[string,Method];
     */
    public static function mapFromReflectionClassAndMethod(
        Context $context,
        \ReflectionClass $class,
        \ReflectionMethod $method,
        array $parents
    ) {
        $reflection_method =
            new \ReflectionMethod($class->getName(), $method->name);

        $number_of_required_parameters =
            $reflection_method->getNumberOfRequiredParameters();

        $number_of_optional_parameters =
            $reflection_method->getNumberOfParameters()
            - $number_of_required_parameters;

        $canonical_method_name =
            strtolower($method->name);

        $method = new Method(
            $context,
            Comment::none(),
            $method->name,
            Type::none(),
            $reflection_method->getModifiers(),
            $number_of_required_parameters,
            $number_of_optional_parameters
        );

        $fqsen = $method->getFQSEN();
        // $fqsen = "{$class->getName()}::{$method->name}";

        $name_method_info_map = [
            $fqsen->__toString() => $method
        ];

        // Populate multiple-dispatch alternate method
        foreach ($fqsen->alternateFQSENInfiniteList() as $alt_fqsen) {
            if (!Type::builtinExists($alt_fqsen)) {
                break;
            }

            $alt_method = clone($method);
            $alt_method->withName(
                $method->name . ' ' . $alt_fqsen->getAlternateId()
            );

            $name_method_info_map = array_merge($name_method_info_map, [
                $alt_fqsen->__toString() => $alt_method,
            ]);
        }

        /*
        global $INTERNAL_ARG_INFO;
        if(!empty($INTERNAL_ARG_INFO[$fqsen])) {
            $arginfo =
                $INTERNAL_ARG_INFO[$fqsen];

            $method_info->type = $arginfo[0];


            $alt_fqsen =
                "{$class->getName()}::{$method->name} $alt";
            while(!empty($INTERNAL_ARG_INFO[$alt_fqsen])) {

                $method_info_alt = $method_info;

                ${"arginfo{$alt}"} = $INTERNAL_ARG_INFO[$alt_fqsen];
                unset(${"arginfo".($alt+1)});

                $method_info_alt->type =
                    ${"arginfo{$alt}"}[0];

                $name_method_info_map = array_merge($name_method_info_map, [
                    "$canonical_method_name $alt" => $method_info_alt
                ]);

                $alt++;

                $alt_fqsen =
                    "{$class->getName()}::{$method->name} $alt";

            }
         */

        /*
        if(!empty($parents)) {
            foreach($parents as $parent_name) {
                $parent_fqsen = "{$parent_name}::{$method->name}";
                if(!empty($INTERNAL_ARG_INFO[$parent_fqsen])) {

                    $alt_name = "$canonical_method_name $alt";
                    $method_info_alt = $method_info;

                    $name_method_info_map =
                        array_merge($name_method_info_map, [
                            $alt_name => $method_info_alt
                        ]);

                    $arginfo =
                        $INTERNAL_ARG_INFO[$parent_fqsen];

                    $method_info_alt->type = $arginfo[0];

                    $parent_fqsen_alt =
                        "{$parent_name}::{$method->name} $alt";

                    while(!empty($INTERNAL_ARG_INFO[$parent_fqsen_alt])) {
                        ${"arginfo{$alt}"} =
                            $INTERNAL_ARG_INFO[$parent_fqsen_alt];

                        unset(${"arginfo".($alt+1)});

                        $name_method_info_map[$alt_name]->type =
                            ${"arginfo{$alt}"}[0];

                        $alt++;

                        $parent_fqsen_alt =
                            "{$parent_name}::{$method->name} $alt";
                    }
                    break;
                }
            }
        }
         */

        foreach($method->getParameterList() as $param) {
            $alt = 1;
            $flags = 0;
            if($param->isPassedByReference()) {
                $flags |= \ast\flags\PARAM_REF;
            }

            if($param->isVariadic()) {
                $flags |= \ast\flags\PARAM_VARIADIC;
            }

            $name_method_info_map[strtolower($method->name)]->parameter_list[] =
                new Parameter(
                    $context,
                    Comment::none(),
                    $param->name,
                    new Type([(empty($arginfo) ? '' : (next($arginfo) ?: ''))]),
                    $flags
                );

            while(!empty(${"arginfo{$alt}"})) {
                $name_alt = strtolower($method->name).' '.$alt;

                $name_method_info_map[$name_alt]->parameter_list[] =
                    new ParameterElement(
                        'internal',
                        '',
                        0,
                        0,
                        '',
                        false,
                        $flags,
                        $param->name,
                        (empty(${"arginfo{$alt}"}) ? '' : (next(${"arginfo{$alt}"}) ?: '')),
                        null
                    );

                $alt++;
            }
        }

        return $name_method_info_map;
    }

    /**
     *
     */
    public static function fromAST(
        Context $context,
        \ast\Node $node
    ) : MethodElement {
        $number_of_required_parameters = 0;
        $number_of_optional_parameters = 0;

        $comment =
            Comment::fromString($node->docComment ?? '');

        $method_name = $node->name;

        $method = new Method(
            $context,
            $comment,
            $method_name,
            Type::none(),
            $node->flags,
            $number_of_required_parameters,
            $number_of_optional_parameters
        );

        $method->parameter_list =
            Parameter::listFromAST($context, $node->children[0]);
            // node_paramlist($file, $node->children[0], $req, $opt, $dc, $namespace);

        /*
        $result = [
            'oret'=>'',
            'ast'=>$node->children[2]
        ];
         */

        $method->setIsDeprecated($comment->isDeprecated());

        if($node->children[3] !== null) {
            // Original return type
            $method->original_return_type =
                Type::typeFromSimpleNode(
                    $context,
                    $node->children[3]
                );

            // This one changes as we walk the tree
            $method->setType(
                Type::typeFromSimpleNode(
                    $context,
                    $node->children[3]
                )
            );

            /*
            $result['oret'] =
                ast_node_type($file, $node->children[3], $namespace); // Original return type
            $result['ret'] =
                ast_node_type($file, $node->children[3], $namespace); // This one changes as we walk the tree
             */
        } else if ($comment->hasReturnType()) {

            $type = $comment->getReturnType();
            if ($type->hasTypeName('static')
                || $type->hasTypeName('self')
                || $type->hasTypeName('$this')
            ) {
                // We can't actually figure out 'static' at this
                // point, but fill it in regardless. It will be partially
                // correct
                if ($context->hasClassFQSEN()) {
                    $type = $context->getClassFQSEN()->asType();
                }
            }

            $method->original_return_type = $type;
            $method->setType($type);

            /*
            // Check if the docComment has a return value specified
            if(!empty($dc['return'])) {
                // We can't actually figure out 'static' at this point, but fill it in regardless. It will be partially correct
                if($dc['return'] == 'static'
                    || $dc['return'] == 'self'
                    || $dc['return'] == '$this'
                ) {
                    if(strpos($current_scope,'::')!==false)
                        list($dc['return'],) = explode('::',$current_scope);
                }

                $result['oret'] = $dc['return'];
                $result['ret'] = $dc['return'];
            }
             */
        }

        // Add params to local scope for user functions
        if($context->getFile() != 'internal') {
            $i = 1;

            foreach ($method->parameter_list as $i => $parameter) {
                if (!$parameter->getType()->hasAnyType()) {
                    // If there is no type specified in PHP, check
                    // for a docComment. We assume order in the
                    // docComment matches the parameter order in the
                    // code
                    if (!empty($comment->getParameterList()[$i])) {
                        // ...
                        // $scope[$current_scope]['vars'][$v['name']] = ['type'=>$dc['params'][$k]['type'], 'tainted'=>false, 'tainted_by'=>'', 'param'=>$i];
                    } else {
                        // ...
                        // $scope[$current_scope]['vars'][$v['name']] = ['type'=>'', 'tainted'=>false, 'tainted_by'=>'', 'param'=>$i];
                    }
                } else {
                    // $scope[$current_scope]['vars'][$v['name']] = ['type'=>$v['type'], 'tainted'=>false, 'tainted_by'=>'', 'param'=>$i];
                }

                if ($parameter->hasDef()) {
                    $type =
                }

                if(array_key_exists('def', $v)) {
                    $type =
                        node_type(
                            $file,
                            $namespace,
                            $v['def'],
                            $current_scope,
                            empty($current_class)
                                ? null
                                : $classes[strtolower($current_class)]
                        );

                    if($scope[$current_scope]['vars'][$v['name']]['type'] !== '') {
                        // Does the default value match the declared type?
                        if($type!=='null' && !type_check($type, $scope[$current_scope]['vars'][$v['name']]['type'])) {
                            Log::err(Log::ETYPE, "Default value for {$scope[$current_scope]['vars'][$v['name']]['type']} \${$v['name']} can't be $type", $file, $node->lineno);
                        }
                    }

                    add_type(
                        $current_scope,
                        $v['name'],
                        strtolower($type)
                    );

                    // If we have no other type info about a parameter, just because it has a default value of null
                    // doesn't mean that is its type. Any type can default to null
                    if($type==='null' && !empty($result['params'][$k]['type'])) {
                        $result['params'][$k]['type'] =
                            merge_type(
                                $result['params'][$k]['type'],
                                strtolower($type)
                            );
                    }
                }
                $i++;


            }


            /*
            foreach($result['params'] as $k=>$v) {
                if(empty($v['type'])) {
                    // If there is no type specified in PHP, check for a docComment
                    // We assume order in the docComment matches the parameter order in the code
                    if(!empty($dc['params'][$k]['type'])) {
                        $scope[$current_scope]['vars'][$v['name']] = ['type'=>$dc['params'][$k]['type'], 'tainted'=>false, 'tainted_by'=>'', 'param'=>$i];
                    } else {
                        $scope[$current_scope]['vars'][$v['name']] = ['type'=>'', 'tainted'=>false, 'tainted_by'=>'', 'param'=>$i];
                    }
                } else {
                    $scope[$current_scope]['vars'][$v['name']] = ['type'=>$v['type'], 'tainted'=>false, 'tainted_by'=>'', 'param'=>$i];
                }
                if(array_key_exists('def', $v)) {
                    $type = node_type($file, $namespace, $v['def'], $current_scope, empty($current_class) ? null : $classes[strtolower($current_class)]);
                    if($scope[$current_scope]['vars'][$v['name']]['type'] !== '') {
                        // Does the default value match the declared type?
                        if($type!=='null' && !type_check($type, $scope[$current_scope]['vars'][$v['name']]['type'])) {
                            Log::err(Log::ETYPE, "Default value for {$scope[$current_scope]['vars'][$v['name']]['type']} \${$v['name']} can't be $type", $file, $node->lineno);
                        }
                    }
                    add_type($current_scope, $v['name'], strtolower($type));
                    // If we have no other type info about a parameter, just because it has a default value of null
                    // doesn't mean that is its type. Any type can default to null
                    if($type==='null' && !empty($result['params'][$k]['type'])) {
                        $result['params'][$k]['type'] = merge_type($result['params'][$k]['type'], strtolower($type));
                    }
                }
                $i++;
            }
             */


            if(!empty($dc['vars'])) {
                foreach($dc['vars'] as $var) {
                    if(empty($scope[$current_scope]['vars'][$var['name']])) {
                        $scope[$current_scope]['vars'][$var['name']] = ['type'=>$var['type'], 'tainted'=>false, 'tainted_by'=>''];
                    } else {
                        add_type($current_scope, $var['name'], $var['type']);
                    }
                }
            }
        }

        return $result;
    }

    public function getParameterList() : array {
        return $this->parameter_list;
    }

    /**
     * @return FQSEN
     */
    public function getFQSEN() : FQSEN {
        return FQSEN::fromContext(
            $this->getContext()
        )->withMethodName($this->getName());
    }

}
