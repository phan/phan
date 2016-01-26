<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use \Phan\Language\Context;
use \Phan\Language\Element\Comment\Parameter as CommentParameter;
use \Phan\Language\UnionType;

/**
 */
class Comment
{

    // A legal type identifier
    const simple_type_regex =
        '[a-zA-Z_\x7f-\xff\\\][a-zA-Z0-9_\x7f-\xff\\\]*';

    // A legal type identifier optionally with a []
    // indicating that its a generic typed array
    const generic_array_type_regex =
        self::simple_type_regex . '(\[\])?';

    // A list of one or more types delimited by the '|'
    // character
    const union_type_regex =
        self::generic_array_type_regex . '(\|' . self::generic_array_type_regex . ')*';

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
     * @var CommentParameter[]
     * A map from variable name to CommentParameters from
     * param declarations
     */
    private $parameter_map = [];

    /**
     * @var UnionType
     * A UnionType defined by a @return directive
     */
    private $return = null;

    /**
     * @var string[]
     * A list of issue types to be suppressed
     */
    private $suppress_issue_list = [];

    /**
     * A private constructor meant to ingest a parsed comment
     * docblock.
     *
     * @param bool $is_deprecated
     * Set to true if the comment contains a 'deprecated'
     * directive.
     *
     * @param Variable[] $variable_list
     *
     * @param CommentParameter[] $parameter_list
     *
     * @param UnionType $return
     *
     * @param string[] $suppress_issue_list
     * A list of tags for error type to be suppressed
     */
    private function __construct(
        bool $is_deprecated,
        array $variable_list,
        array $parameter_list,
        UnionType $return,
        array $suppress_issue_list
    ) {
        $this->is_deprecated = $is_deprecated;
        $this->variable_list = $variable_list;
        $this->parameter_list = $parameter_list;
        $this->return = $return;
        $this->suppress_issue_list = $suppress_issue_list;

        foreach ($this->parameter_list as $i => $parameter) {
            $name = $parameter->getName();
            if (!empty($name)) {
                // Add it to the named map
                $this->parameter_map[$name] = $parameter;

                // Remove it from the offset map
                unset($this->parameter_list[$i]);
            }
        }
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
        $suppress_issue_list = [];

        $lines = explode("\n", $comment);

        foreach ($lines as $line) {

            if (strpos($line, '@param') !== false) {
                $parameter_list[] =
                    self::parameterFromCommentLine($context, $line);
            } elseif (stripos($line, '@var') !== false) {
                $variable_list[] =
                    self::parameterFromCommentLine($context, $line);
            } elseif (stripos($line, '@return') !== false) {
                if (preg_match('/@return\s+(' . self::union_type_regex . '+)/', $line, $match)) {
                    $return = $match[1];
                }
            } elseif (stripos($line, '@suppress') !== false) {
                $suppress_issue_list[] =
                    self::suppressIssueFromCommentLine($line);
            }

            if (($pos=stripos($line, '@deprecated')) !== false) {
                if (preg_match('/@deprecated\b/', $line, $match)) {
                    $is_deprecated = true;
                }
            }
        }

        $return_type = UnionType::fromStringInContext(
            $return ?: '',
            $context
        );

        return new Comment(
            $is_deprecated,
            $variable_list,
            $parameter_list,
            $return_type,
            $suppress_issue_list
        );
    }

    /**
     * @param Context $context
     * The context in which the comment line appears
     *
     * @param string $line
     * An individual line of a comment
     *
     * @return CommentParameter
     * A CommentParameter associated with a line that has a var
     * or param reference.
     */
    private static function parameterFromCommentLine(
        Context $context,
        string $line
    ) {
        $match = [];
        if (preg_match('/@(param|var)\s+(' . self::union_type_regex . ')(\s+(\\$\S+))?/', $line, $match)) {
            $type = null;

            $type = $match[2];

            $variable_name =
                empty($match[7]) ? '' : trim($match[7], '$');

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

            return new CommentParameter(
                $variable_name,
                $union_type
            );
        }

        return  new CommentParameter('', new UnionType());
    }

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return string
     * An issue name to suppress
     */
    private static function suppressIssueFromCommentLine(
        string $line
    ) : string {
        if (preg_match('/@suppress\s+([^\s]+)/', $line, $match)) {
            return $match[1];
        }

        return '';
    }

    /**
     * @return bool
     * Set to true if the comment contains a 'deprecated'
     * directive.
     */
    public function isDeprecated() : bool
    {
        return $this->is_deprecated;
    }

    /**
     * @return UnionType
     * A UnionType defined by a (at)return directive
     */
    public function getReturnType() : UnionType
    {
        return $this->return;
    }

    /**
     * @return bool
     * True if this doc block contains a (at)return
     * directive specifying a type.
     */
    public function hasReturnUnionType() : bool
    {
        return !empty($this->return) && !$this->return->isEmpty();
    }

    /**
     * @return CommentParameter[]
     *
     * @suppress PhanUnreferencedMethod
     */
    public function getParameterList() : array
    {
        return $this->parameter_list;
    }

    /**
     * @return string[]
     * A set of issie names like 'PhanUnreferencedMethod' to suppress
     */
    public function getSuppressIssueList() : array
    {
        return $this->suppress_issue_list;
    }

    /**
     * @return bool
     * True if we have a parameter at the given offset
     */
    public function hasParameterWithNameOrOffset(
        string $name,
        int $offset
    ) : bool {
        if (!empty($this->parameter_map[$name])) {
            return true;
        }

        return !empty($this->parameter_list[$offset]);
    }

    /**
     * @return CommentParameter
     * The paramter at the given offset
     */
    public function getParameterWithNameOrOffset(
        string $name,
        int $offset
    ) : CommentParameter {
        if (!empty($this->parameter_map[$name])) {
            return $this->parameter_map[$name];
        }

        return $this->parameter_list[$offset];
    }

    /**
     * @return CommentParameter[]
     */
    public function getVariableList() : array
    {
        return $this->variable_list;
    }

    public function __toString() : string
    {
        $string = "/**\n";

        if ($this->is_deprecated) {
            $string  .= " * @deprecated\n";
        }

        foreach ($this->variable_list as $variable) {
            $string  .= " * @var $variable\n";
        }

        foreach ($this->parameter_list as $parameter) {
            $string  .= " * @var $parameter\n";
        }

        if ($this->return) {
            $string .= " * @return {$this->return}\n";
        }

        $string .= " */\n";

        return $string;
    }
}
