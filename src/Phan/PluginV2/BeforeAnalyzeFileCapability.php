<?php

declare(strict_types=1);

namespace Phan\PluginV2;

use ast\Node;
use Phan\CodeBase;
use Phan\Language\Context;

/**
 * Use PluginV3 instead
 */
interface BeforeAnalyzeFileCapability
{
    /**
     * This method is called before analyzing a file.
     *
     * @return void
     */
    public function beforeAnalyzeFile(
        CodeBase $code_base,
        Context $context,
        string $file_contents,
        Node $node
    );
}
