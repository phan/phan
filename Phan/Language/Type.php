<?php declare(strict_types=1);
namespace Phan\Language;

use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\AST\KindVisitorImplementation;
use \Phan\Language\Type\NodeTypeKindVisitor;
use \ast\Node;

/**
 * Static data defining type names for builtin classes
 */
$BUILTIN_CLASS_TYPES =
    require(__DIR__.'/Type/BuiltinClassTypes.php');

/**
 * Static data defining types for builtin functions
 */
$BUILTIN_FUNCTION_ARGUMENT_TYPES =
    require(__DIR__.'/Type/BuiltinFunctionArgumentTypes.php');

class Type {
    use \Phan\Language\AST;

    /**
     * @var string[]
     * A list of type names
     */
    private $type_name_list = [];

    /**
     * @var bool
     * True if this
     */
    private $is_tainted = false;

    /**
     * @param string[] $type_name_list
     * A list of type names
     */
    public function __construct(array $type_name_list) {
        $this->type_name_list = array_map(function(string $type_name) {
            return $this->toCanonicalName($type_name);
        }, $type_name_list);
    }

    public function __toString() : string {
        return implode('|', $this->type_name_list);
    }

    /**
     * Get a Type specifying that there are no
     * known types on a thing.
     */
    public static function none() : Type {
        return new Type([]);
    }

    /**
     * @return Type
     * A Type for the given object
     */
    public static function typeForObject($object) : Type {
        return new Type([gettype($object)]);
    }

    /**
     * @param string $type_string
     * A '|' delimited string representing a type in the form
     * 'int|string|null|ClassName'.
     *
     * @return Type
     */
    public static function typeFromString(string $type_string) : Type {
        return new Type(explode('|', $type_string));
    }

    /**
     * ast_node_type() is for places where an actual type
     * name appears. This returns that type name. Use node_type()
     * instead to figure out the type of a node
     *
     * @param Context $context
     * @param null|string|Node $node
     *
     * @see \Phan\Deprecated\AST::ast_node_type
     */
    public static function typeFromSimpleNode(
        Context $context,
        $node
    ) : Type {
        if($node instanceof \ast\Node) {
            switch($node->kind) {
            case \ast\AST_NAME:
                $result = static::astQualifiedName($context, $node);
                break;
            case \ast\AST_TYPE:
                if($node->flags == \ast\flags\TYPE_CALLABLE) {
                    $result = 'callable';
                } else if($node->flags == \ast\flags\TYPE_ARRAY) {
                    $result = 'array';
                }
                else assert(false, "Unknown type: {$node->flags}");
                break;
            default:
                Log::err(
                    Log::EFATAL,
                    "ast_node_type: unknown node type: "
                    . \ast\get_kind_name($node->kind)
                );
                break;
            }
        } else {
            $result = (string)$node;
        }
        return Type::typeFromString($result);
    }

    /**
     * @return bool
     * True if this is a native type (like int, string, etc.)
     */
    public function isNativeType() : bool {
        return in_array(
            str_replace('[]', '', (string)$this), [
                'int',
                'float',
                'bool',
                'true',
                'string',
                'callable',
                'array',
                'null',
                'object',
                'resource',
                'mixed',
                'void'
            ]
        );
    }

    /**
     * @param Context $context
     * @param Node|string|null $node
     *
     * @return Type
     *
     * @see \Phan\Deprecated\Pass2::node_type
     * Formerly 'function node_type'
     */
    public static function typeFromNode(
        Context $context,
        $node
    ) : Type {
        if(!($node instanceof Node)) {
            if($node === null) {
                return Type::none();
            }
            return self::typeForObject($node);
        }

        return (new Element($node))->acceptKindVisitor(
            new NodeTypeKindVisitor($context)
        );
	}

    /**
     * Looks for any suspicious GPSC variables in the given node
     *
     * @return bool
     */
    private function isTainted(
        Context $context,
        Node $node,
        string $current_scope
    ) : bool {

        // global $scope, $tainted_by;

        static $tainted = [
            '_GET' => '*',
            '_POST' => '*',
            '_COOKIE' => '*',
            '_REQUEST' => '*',
            '_FILES' => '*',
            '_SERVER' => [
                'QUERY_STRING',
                'HTTP_HOST',
                'HTTP_USER_AGENT',
                'HTTP_ACCEPT_ENCODING',
                'HTTP_ACCEPT_LANGUAGE',
                'REQUEST_URI',
                'PHP_SELF',
                'argv'
            ]
        ];

        if(!$node instanceof Node) {
            return false;
        }

        $parent = $node;
        while(($node instanceof Node)
            && ($node->kind != \ast\AST_VAR)
            && ($node->kind != \ast\AST_MAGIC_CONST)
        ) {
            $parent = $node;
            if(empty($node->children[0])) {
                break;
            }
            $node = $node->children[0];
        }

        if($parent->kind == \ast\AST_DIM) {
            if($node->children[0] instanceof Node) {
                // $$var or something else dynamic is going on, not direct access to a suspivious var
                return false;
            }
            foreach($tainted as $name=>$index) {
                if($node->children[0] === $name) {
                    if($index=='*') {
                        return true;
                    }
                    if($parent->children[1] instanceof Node) {
                        // Dynamic index, give up
                        return false;
                    }
                    if(in_array($parent->children[1], $index, true)) {
                        return true;
                    }
                }
            }
        } else if($parent->kind == \ast\AST_VAR
            && !($parent->children[0] instanceof Node)
        ) {
            $variable_name = $parent->children[0];
            if (empty($context->getScope()->getVariableNameList()[$variable_name])) {
            }

            if(empty($scope[$current_scope]['vars'][$parent->children[0]])) {
                if(!superglobal($parent->children[0]))
                    Log::err(
                        Log::EVAR,
                        "Variable \${$parent->children[0]} is not defined",
                        $file,
                        $parent->lineno
                    );
            } else {
                if(!empty($scope[$current_scope]['vars'][$parent->children[0]]['tainted'])
                ) {
                    $tainted_by =
                        $scope[$current_scope]['vars'][$parent->children[0]]['tainted_by'];
                    return true;
                }
            }
        }

        return false;
    }


    public static function builtinClassPropertyType(
        string $class_name,
        string $property_name
    ) : Type {
        $class_property_type_map =
            $BUILTIN_CLASS_TYPES[strtolower($class_name)]['properties'];

        $property_type_name =
            $class_property_type_map[$property_name];

        return new Type($property_type_name);
    }

    /**
     * @return Type[]
     * A list of types for parameters associated with the
     * given builtin function with the given name
     */
    public static function builtinFunctionPropertyNameTypeMap(
        FQSEN $function_fqsen
    ) : array {
        $type_name_struct =
            $BUILTIN_FUNCTION_ARGUMENT_TYPES[$function_fqsen->__toString()];

        if (!$type_name_struct) {
            return [];
        }

        $type_return = array_shift($type_name_struct);
        $name_type_name_map = $type_name_struct;

        $property_name_type_map = [];

        foreach ($name_type_name_map as $name => $type_name) {
            $property_name_type_map[$name] =
                new Type($type_name);
        }

        return $property_name_type_map;
    }

    /**
     * @return bool
     * True if a builtin with the given FQSEN exists, else
     * flase.
     */
    public static function builtinExists(FQSEN $fqsen) : bool {
        return !empty(
            $BUILTIN_FUNCTION_ARGUMENT_TYPES[$fqsen->__toString()]
        );
    }

    /**
     * @param string $type_name
     * Any type name
     *
     * @return string
     * A canonical name for the given type name
     */
    private static function toCanonicalName(string $type_name) : string {
        static $repmaps = [
            [
                'integer',
                'double',
                'boolean',
                'false',
                'true',
                'callback',
                'closure',
                'NULL'
            ],
            [
                'int',
                'float',
                'bool',
                'bool',
                'bool',
                'callable',
                'callable',
                'null'
            ]
        ];

        return str_replace(
            $repmaps[0],
            $repmaps[1],
            $type_name
        );
    }

    /**
     * Add a type name to the list of types
     *
     * @return null
     */
    public function addTypeName($type_name) {
        $type_name_list[] = $type_name;

        // Only allow unique elements
        $type_name_list = array_unique($type_name_list);
    }

    /**
     * Add the given types to this type
     *
     * @return null
     */
    public function addType(Type $type) {
        foreach ($type->type_name_list as $i => $type_name) {
            $this->addTypeName($type_name);
        }
    }

    /**
     * @return bool
     * True if this union type contains the given named
     * type.
     */
    public function hasTypeName(string $type_name) : bool {
        return in_array($type_name, $this->type_name_list);
    }

    /**
     * @return bool
     * True if this union type contains any of the given
     * named types
     */
    public function hasAnyTypeName(array $type_name_list) : bool {
        return array_reduce(
            $type_name_list,
            function(bool $carry, string $type_name)  {
                return $carry || $this->hasTypeName($type_name);
            },
            false
        );
    }

    /**
     * @return bool
     * True if this union type contains any types.
     */
    public function hasAnyType() : bool {
        return empty($this->type_name_list);
    }

    /**
     * @return int
     * The number of types in this union type
     */
    public function typeCount() : int {
        return count($this->type_name_list);
    }

    /**
     * @return string[]
     */
    public function typeNameList() : array {
        return $this->type_name_list;
    }

    /**
     * @param Type $target_type
     * A type to check to see if this can cast to it
     *
     * @param Context $context
     * The context in which we'd like to perform the
     * cast
     *
     * @return bool
     * True if this type is allowed to cast to the given type
     * i.e. int->float is allowed  while float->int is not.
     *
     * @see \Phan\Deprecated\Pass2::type_check
     * Formerly 'function type_check'
     */
    public function canCastToTypeInContext(
        Type $type_target,
        Context $context
    ) : bool {
        $type_name_source = (string)$this;
        $type_name_target = (string)$type_target;

        // Fast-track most common cases first
        if($type_name_source === $type_name_target) {
            return true;
        }

        // If either type is unknown, we can't call it
        // a failure
        if(empty($type_name_source) || empty($type_name_target)) {
            return true;
        }

        if(strpos("|$type_name_source|", '|mixed|') !== false) {
            return true;
        }

        if(strpos("|$type_name_target|", '|mixed|') !== false) {
            return true;
        }

        if($type_name_source ==='int' && $type_name_target === 'float') {
            return true;
        }

        // $src = type_map(strtolower($src));
        // $dst = type_map(strtolower($dst));

        // our own union types
        foreach($this->type_name_list as $s) {
            if(empty($s)) {
                continue;
            }

            foreach($type_target->type_name_list as $d) {
                if(empty($d)) {
                    continue;
                }

                if(substr($s,0,9)=='callable:') {
                    $s = 'callable';
                }

                if(substr($d,0,9)=='callable:') {
                    $d = 'callable';
                }

                if($s[0]=='\\') {
                    $s = substr($s,1);
                }

                if($d[0]=='\\') {
                    $d = substr($d,1);
                }

                if($s===$d) {
                    return true;
                }

                if($s==='int' && $d==='float') {
                    return true; // int->float is ok
                }

                if(($s==='array'
                    || $s==='string'
                    || (strpos($s,'[]')!==false))
                    && $d==='callable'
                ) {
                    return true;
                }
                if($s === 'object'
                    && !type_scalar($d)
                    && $d!=='array'
                ) {
                    return true;
                }

                if($d === 'object' &&
                    !type_scalar($s)
                    && $s!=='array'
                ) {
                    return true;
                }

                if(strpos($s,'[]') !== false
                    && $d==='array'
                ) {
                    return true;
                }

                if(strpos($d,'[]') !== false
                    && $s==='array'
                ) {
                    return true;
                }

                if(($pos=strrpos($d,'\\'))!==false) {
                    if ($context->hasNamespace()) {
                        if(trim(strtolower($context->getNamespace().'\\'.$s),
                            '\\') == $d
                        ) {
                            return true;
                        }
                    } else {
                        if(substr($d, $pos+1) === $s) {
                            return true; // Lazy hack, but...
                        }
                    }
                }

                if(($pos=strrpos($s,'\\'))!==false) {
                    if ($context->hasNamespace()) {
                        if(trim(strtolower($context->getNamespace().'\\'.$d),
                            '\\') == $s
                        ) {
                            return true;
                        }
                    } else {
                        if(substr($s, $pos+1) === $d) {
                            return true; // Lazy hack, but...
                        }
                    }
                }
            }
        }

        return false;
    }

}
