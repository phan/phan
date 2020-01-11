<?php

declare(strict_types=1);

namespace Phan\PluginV2;

use ast\Node;
use Phan\CodeBase;
use Phan\Language\Context;

/**
 * Plugins should move to PluginV3 to indicate that they support PhanV2, AST version 70 and its APIs
 *
 * @see \Phan\PluginV3\AfterAnalyzeFileCapability
 */
interface AfterAnalyzeFileCapability
{
    /**
     * Use PluginV3 instead.
     * @return void
     */
    public function afterAnalyzeFile(
        CodeBase $code_base,
        Context $context,
        string $file_contents,
        Node $node
    );
}
