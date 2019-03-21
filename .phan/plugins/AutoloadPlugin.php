<?php declare(strict_types=1);

use ast\Node;
use Phan\PluginV2;
use Phan\PluginV2\PluginAwarePostAnalysisVisitor;
use Phan\PluginV2\PostAnalyzeNodeCapability;

class AutoloadPlugin extends PluginV2 implements PluginV2\BeforeAnalyzeCapability
{
    public function beforeAnalyze(\Phan\CodeBase $code_base)
    {
        $i = 1;

        foreach ($code_base->getClassMapMap() as $class) {
            $i = 1;
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new AutoloadPlugin();
