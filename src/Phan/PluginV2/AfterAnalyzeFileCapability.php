<?php declare(strict_types=1);
namespace Phan\PluginV2;

use Phan\CodeBase;
use Phan\Language\Context;
use ast\Node;

/**
 * AfterAnalyzeFileCapability is used when you want to perform checks after analyzing a file
 * NOTE: This does not run on empty files.
 */
interface AfterAnalyzeFileCapability
{
    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context
     * A context with the file name for $file_contents and the scope after analyzing $node.
     *
     * @param string $file_contents the unmodified file contents
     * @param Node $node the
     * @return void
     */
    public function afterAnalyzeFile(
        CodeBase $code_base,
        Context $context,
        string $file_contents,
        Node $node
    );
}
