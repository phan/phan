<?php
declare(strict_types=1);
namespace Phan\Language;

require_once(__DIR__.'/../Deprecated/AST.php');

use \Phan\CodeBase;
use \Phan\Configuration;
use \Phan\Debug;
use \Phan\Deprecated;
use \Phan\Language\AST\Element;
use \Phan\Language\Element\Clazz;
use \Phan\Language\Element\Comment;
use \Phan\Language\Element\Method;
use \Phan\Language\Element\Property;
use \Phan\Language\File\FileKindVisitor;
use \Phan\Log;

class File {

    /**
     * @var CodeBase
     */
    private $code_base = null;

    /**
     * @var string
     */
    private $file = null;

    /**
     * @var \ast
     */
    private $ast = null;

    /**
     * @param string $file
     */
    public function __construct(
        CodeBase $code_base,
        string $file
    ) {
        $this->code_base = $code_base;
        $this->file = $file;
        $this->ast = \ast\parse_file($file, $version = 10);
    }

    /**
     * @return string
     * The namespace of the file
     */
    public function passOne() {
        return $this->passOneRecursive(
            $this->ast,
            (new Context($this->code_base))
                ->withFile($this->file)
                ->withLineNumberStart($this->ast->lineno ?? 0)
                ->withLineNumberEnd($this->ast->endLineno ?? 0)
        );
    }

    /**
     * @param \ast\Node $ast
     *
     * @param Context $context
     *
     * @param CodeBase $code_base
     *
     * @return string
     * The namespace of the file
     */
    public function passOneRecursive(
        \ast\Node $ast,
        Context $context
    ) : Context {
        $done = false;

        $current_clazz = $context->isInClassScope()
            ? $this->code_base->getClassByFQSEN(
                $context->getClassFQSEN()
            )
            : null;

        $context =
            (new Element($ast))->acceptKindVisitor(
                new FileKindVisitor($context)
            );

        if (!$context) {
            Debug::printNode($ast);
        }

        assert(!empty($context),
            'Context cannot be null');

        // TODO what to do with done?
        if(!$done) {
            foreach($ast->children as $child) {
                if ($child instanceof \ast\Node) {
                    $child_context =
                        $this->passOneRecursive(
                            $child,
                            $context
                        );

                    $context = $context->withNamespace(
                        $child_context->getNamespace()
                    );
                }
            }
        }

        return $context;
    }

}
