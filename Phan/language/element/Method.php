<?php
declare(strict_types=1);
namespace phan\language\element;

require_once(__DIR__.'/../Context.php');
require_once(__DIR__.'/../Type.php');
require_once(__DIR__.'/Comment.php');
require_once(__DIR__.'/Parameter.php');
require_once(__DIR__.'/Property.php');
require_once(__DIR__.'/TypedStructuralElement.php');

use \phan\language\Context;
use \phan\language\Type;
use \phan\language\element\Comment;
use \phan\language\element\Parameter;

class Method extends TypedStructuralElement {

    /**
     * @var int
     */
    private $number_of_required_parameters;

    /**
     * @var int
     */
    private $number_of_optional_parameters;

    /**
     * @var
     */
    private $parameter_list;


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

        $method_element = new Method(
            $context,
            Comment::none(),
            $method->name,
            Type::none(),
            $reflection_method->getModifiers(),
            $number_of_required_parameters,
            $number_of_optional_parameters
        );

        $fqsen = $method_element->getFQSEN();
        // $fqsen = "{$class->getName()}::{$method->name}";

        $name_method_info_map = [
            $fqsen->__toString() => $method_element
        ];

        // Populate multiple-dispatch alternate method
        foreach ($fqsen->alternateFQSENInfiniteList() as $alt_fqsen) {
            if (!Type::builtinExists($alt_fqsen)) {
                break;
            }

            $alt_method_element = $method_element;
            $alt_method_element->withName(
                $method->name . ' ' . $alt_fqsen->getAlternateId()
            );

            $name_method_info_map = array_merge($name_method_info_map, [
                $alt_fqsen->__toString() => $alt_method_element,
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

        foreach($method->getParameters() as $param) {
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
        string $file,
        bool $is_conditional,
        \ast\Node $node,
        $current_scope,
        $current_class,
        string $namespace
    ) : MethodElement {
        $number_of_required_parameters = 0;
        $number_of_optional_parameters = 0;

        $comment_element = CommentElement::none();
        if(!empty($node->docComment)) {
            $comment_element = CommentElement::fromString($node->docComment);
        }

        $method_element = new MethodElement(
            $file,
            $namespace,
            $node->lineno,
            $node->endLineno,
            $node->docComment,
            $is_conditional,
            $node->flags,
            (strpos($current_scope,'::')===false) ? $namespace.$node->name : $node->name,
            '',
            $number_of_required_parameters,
            $number_of_optional_parameters
        );

        $method_element->scope = $current_scope;

        $method_element->parameter_list =
            ParameterElement::listFromAST($state, $node->children[0]);
            // node_paramlist($file, $node->children[0], $req, $opt, $dc, $namespace);


        $result = [
            'oret'=>'',
            'ast'=>$node->children[2]
        ];

        if($comment_element->isDeprecated()) {
            $result['deprecated'] = true;
        }

        if($node->children[3] !== null) {
            $result['oret'] = ast_node_type($file, $node->children[3], $namespace); // Original return type
            $result['ret'] = ast_node_type($file, $node->children[3], $namespace); // This one changes as we walk the tree
        } else {
            // Check if the docComment has a return value specified
            if(!empty($dc['return'])) {
                // We can't actually figure out 'static' at this point, but fill it in regardless. It will be partially correct
                if($dc['return'] == 'static' || $dc['return'] == 'self' || $dc['return'] == '$this') {
                    if(strpos($current_scope,'::')!==false) list($dc['return'],) = explode('::',$current_scope);
                }
                $result['oret'] = $dc['return'];
                $result['ret'] = $dc['return'];
            }
        }
        // Add params to local scope for user functions
        if($file != 'internal') {
            $i = 1;
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

}
