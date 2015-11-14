<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\Element\Comment\Parameter as CommentParameter;
use \Phan\Language\UnionType;

/**
 */
class Comment {

    /**
     * @var bool
     * Set to true if the comment contains a 'deprecated'
     * directive.
     */
    private $is_deprecated = false;

    /**
     * @var CommentParameter[]
     * A list of CommentParameters from var declarations
     */
    private $variable_list = [];

    /**
     * @var CommentParameter[]
     * A list of CommentParameters from param declarations
     */
    private $parameter_list = [];

    /**
     * @var UnionType
     * A UnionType defined by a @return directive
     */
    private $return = null;

    /**
     * @var string $string;
     * The original comment string
     */
    private $string;

    /**
     * A private constructor meant to ingest a parsed comment
     * docblock.
     *
     * @param bool $is_deprecated
     * Set to true if the comment contains a 'deprecated'
     * directive.
     *
     * @param string $string
     * The raw string of the comment
     *
     * @param array $variable_list
     * @param array $parameter_list
     * @param UnionType $return
     */
    private function __construct(
        bool $is_deprecated,
        array $variable_list,
        array $parameter_list,
        UnionType $return,
        string $string
    ) {
        $this->is_deprecated = $is_deprecated;
        $this->variable_list = $variable_list;
        $this->parameter_list = $parameter_list;
        $this->return = $return;
        $this->string = $string;
    }

    /**
     * @return
     * An empty type
     */
    public static function none() : Comment {
        return new Comment(
            false, [], [], new UnionType(), ''
        );
    }

    /**
     * @return Comment
     * A comment built by parsing the given doc block
     * string.
     */
    public static function fromStringInContext(
        string $comment,
        Context $context
    ) : Comment {

        $is_deprecated = false;
        $variable_list = [];
        $parameter_list = [];
        $return = null;

        $lines = explode("\n",$comment);

        foreach($lines as $line) {

            if(($pos=strpos($line, '@param')) !== false) {
                $match = [];
                if(preg_match('/@param\s+(\S+)\s*(?:(\$\S+))*/', $line, $match)) {
                    if(stripos($match[1],'\\') === 0
                        && strpos($match[1],'\\', 1) === false) {
                        $type = trim($match[1], '\\');
                    } else {
                        $type = $match[1];
                    }

                    $variable_name =
                        empty($match[2]) ? '' : trim($match[2], '$');

                    // If the type looks like a variable name,
                    // make it an empty type so that other stuff
                    // can match it. We can't just skip it or
                    // we'd mess up the parameter order.
                    $union_type = null;
                    if (0 !== strpos($type, '$')) {
                        $union_type =
                            UnionType::fromStringInContext(
                                $type,
                                $context
                            );
                    } else {
                        $union_type = new UnionType();
                    }

                    $comment_parameter = new CommentParameter(
                        $variable_name, $union_type, $line
                    );

                    $parameter_list[] = $comment_parameter;
                }
            }

            if(($pos=stripos($line, '@var')) !== false) {
                $match = [];
                if(preg_match('/@var\s+(\S+)\s*(?:(\S+))*/', $line, $match)) {
                    if(strpos($match[1], '\\') === 0 &&
                        strpos($match[1], '\\', 1) === false
                    ) {
                        $type = trim($match[1],'\\');
                    } else {
                        $type = $match[1];
                    }

                    $var_name =
                        empty($match[2])?'':trim($match[2],'$');

                    $var_type = UnionType::fromStringInContext(
                        $type,
                        $context
                    );

                    $comment_parameter = new CommentParameter(
                        $var_name, $var_type, $line
                    );

                    $variable_list[] = $comment_parameter;
                }
            }

            if(($pos=stripos($line, '@return')) !== false) {
                $match = [];
                if(preg_match('/@return\s+(\S+)/', $line, $match)) {
                    if(strpos($match[1],'\\')===0 && strpos($match[1],'\\',1)===false) {
                        $return = trim($match[1],'\\');
                    } else {
                        $return = $match[1];
                    }
                }
            }

            if(($pos=stripos($line, '@deprecated')) !== false) {
                if(preg_match('/@deprecated\b/', $line, $match)) {
                    $is_deprecated = true;
                }
            }
        }

        $return_type = UnionType::fromStringInContext(
            $return ?: '', $context
        );

        return new Comment(
            $is_deprecated,
            $variable_list,
            $parameter_list,
            $return_type,
            $comment
        );
    }

    /**
     * @return bool
     * Set to true if the comment contains a 'deprecated'
     * directive.
     */
    public function isDeprecated() : bool {
        return $this->is_deprecated;
    }

    /**
     * @return UnionType
     * A UnionType defined by a @return directive
     */
    public function getReturnType() : UnionType {
        return $this->return;
    }

    /**
     * @return bool
     * True if this doc block contains a @return
     * directive specifying a type.
     */
    public function hasReturnUnionType() : bool {
        return !empty($this->return);
    }

    /**
     * @return CommentParameter[]
     */
    public function getParameterList() : array {
        return $this->parameter_list;
    }

    /**
     * @return bool
     * True if we have a parameter at the given offset
     */
    public function hasParameterAtOffset(int $offset) : bool {
        return !empty($this->parameter_list[$offset]);
    }

    /**
     * @return CommentParameter
     * The paramter at the given offset
     */
    public function getParameterAtOffset(int $offset) : CommentParameter {
        return $this->parameter_list[$offset];
    }

    /**
     * @return CommentParameter[]
     */
    public function getVariableList() : array {
        return $this->variable_list;
    }

    public function __toString() : string {
        return $this->string;
    }

}
