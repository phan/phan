<?php declare(strict_types=1);

namespace Phan\PluginV2;

use Phan\CodeBase;

/**
 * BeforeAnalyzeCapability is used when you want to perform checks before analyzing a project.
 *
 * beforeAnalyze is invoked immediately before forking analysis workers and before starting the analysis phase.
 */
interface BeforeAnalyzeCapability
{
    /**
     * This method is called before analyzing a project.
     *
     * @param CodeBase $code_base
     * The code base of the project.
     * @return void
     */
    public function beforeAnalyze(
        CodeBase $code_base
    );
}
