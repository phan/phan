<?php
declare(strict_types=1);
namespace phan\element;

require_once(__DIR__.'/ParameterElement.php');
require_once(__DIR__.'/TypedStructuralElement.php');

/**
 * ...
 */
class CommentElement {

    /**
     * @var bool
     */
    private $is_deprecated = false;

    /**
     *
     */
    private $var_list = [];

    /**
     *
     */
    private $param_list = [];

    /**
     *
     */
    private $return = null;

    /**
     * @var string $string;
     */
    private $string;

    /**
     *
     */
    private function __construct(
        bool $is_deprecated,
        array $var_list,
        array $param_list,
        string $return
    ) {
        $this->deprecated = $is_deprecated;
        $this->var_list = $var_list;
        $this->param_list = $param_list;
        $this->return = $return;
    }

    /**
     *
     */
    public static function none() : CommentElement {
        return new CommentElement(
            false, [], [], ''
        );
    }

    /**
     *
     */
    public static function fromString(
        string $comment
    ) : CommentElement {

        $is_deprecated = false;
        $var_list = [];
        $param_list = [];
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

                    $param_list[] = [
                        'name' => empty($match[2])?'':trim($match[2],'$'),
                        'type' => $type
                    ];
                }
            }

            if(($pos=strpos($line, '@var')) !== false) {
                if(preg_match('/@var\s+(\S+)\s*(?:(\S+))*/', $line, $match)) {
                    if(strpos($match[1],'\\')===0 && strpos($match[1],'\\',1)===false) {
                        $type = trim($match[1],'\\');
                    } else {
                        $type = $match[1];
                    }
                    $var_list[] = [
                        'name' => empty($match[2])?'':trim($match[2],'$'),
                        'type' => $type
                    ];
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

            return new CommentElement(
                $is_deprecated,
                $var_list,
                $param_list,
                $return ?: ''
            );
        }
    }

    /**
     *
     */
    public function isDeprecated() : bool {
        return $this->isDeprecated();
    }
}
