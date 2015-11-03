<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Element\Comment\Parameter as CommentParameter;
use \Phan\Language\Type;

/**
 */
class Comment {

    /**
     * @var bool
     * Set to true if the comment contains a @deprecated
     * directive.
     */
    private $is_deprecated = false;

    /**
     * @var CommentParameter[]
     * A list of CommentParameters from @var declarations
     */
    private $variable_list = [];

    /**
     * @var CommentParameter[]
     * A list of CommentParameters from @param declarations
     */
    private $parameter_list = [];

    /**
     * @var Type
     * A Type defined by a @return directive
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
     * Set to true if the comment contains a @deprecated
     * directive.
     *
     * @param array $variable_list
     * @param array $parameter_list
     * @param Type $return
     */
    private function __construct(
        bool $is_deprecated,
        array $variable_list,
        array $parameter_list,
        Type $return
    ) {
        $this->deprecated = $is_deprecated;
        $this->variable_list = $variable_list;
        $this->parameter_list = $parameter_list;
        $this->return = $return;
    }

    /**
     * @return
     * An empty type
     */
    public static function none() : Comment {
        return new Comment(
            false, [], [], Type::none()
        );
    }

    /**
     * @return Comment
     * A comment built by parsing the given doc block
     * string.
     */
    public static function fromString(
        string $comment
    ) : Comment {

        $is_deprecated = false;
        $variable_list = [];
        $parameter_list = [];
        $return = null;

        $lines = explode("\n",$comment);

        foreach($lines as $line) {
            $line = strtolower($line);

            if(($pos=strpos($line, '@param')) !== false) {
                if(preg_match('/@param\s+(\S+)\s*(?:(\S+))*/', $line, $match)) {
                    if(strpos($match[1],'\\')===0
                        && strpos($match[1],'\\',1)===false) {
                        $type = trim($match[1],'\\');
                    } else {
                        $type = $match[1];
                    }

                    $parameter_list[] = new CommentParameter(
                        empty($match[2])?'':trim($match[2],'$'),
                        Type::typeFromString($type),
                        ''
                    );

                    /*
                    $parameter_list[] = [
                        'name' => empty($match[2])?'':trim($match[2],'$'),
                        'type' => $type
                    ];
                     */
                }
            }

            if(($pos=strpos($line, '@var')) !== false) {
                if(preg_match('/@var\s+(\S+)\s*(?:(\S+))*/', $line, $match)) {
                    if(strpos($match[1],'\\')===0 && strpos($match[1],'\\',1)===false) {
                        $type = trim($match[1],'\\');
                    } else {
                        $type = $match[1];
                    }


                    $variable_list[] = new CommentParameter(
                        empty($match[2])?'':trim($match[2],'$'),
                        Type::typeFromString($type),
                        ''
                    );

                    /*
                    $var_list[] = [
                        'name' => empty($match[2])?'':trim($match[2],'$'),
                        'type' => $type
                    ];
                     */
                }
            }

            if(($pos=strpos($line, '@return')) !== false) {
                if(preg_match('/@return\s+(\S+)/', $line, $match)) {
                    if(strpos($match[1],'\\')===0 && strpos($match[1],'\\',1)===false) {
                        $return = trim($match[1],'\\');
                    } else {
                        $return = $match[1];
                    }
                }
            }

            if(($pos=strpos($line, '@deprecated')) !== false) {
                if(preg_match('/@deprecated\b/', $line, $match)) {
                    $is_deprecated = true;
                }
            }

            // TODO: add support for properties

            return new Comment(
                $is_deprecated,
                $variable_list,
                $parameter_list,
                Type::typeFromString($return ?: '')
            );
        }
    }

    /**
     * @return bool
     * Set to true if the comment contains a @deprecated
     * directive.
     */
    public function isDeprecated() : bool {
        return $this->is_deprecated;
    }

    /**
     * @return Type
     * A Type defined by a @return directive
     */
    public function getReturnType() : Type {
        return $this->return;
    }

    /**
     * @return bool
     * True if this doc block contains a @return
     * directive specifying a type.
     */
    public function hasReturnType() : bool {
        return !empty($this->return);
    }

    /**
     * @return CommentParameter[]
     */
    public function getParameterList() : array {
        return $this->parameter_list;
    }

    /**
     * @return CommentParameter[]
     */
    public function getVariableList() : array {
        return $this->variable_list;
    }
}
