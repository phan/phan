<?php declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;

/**
 * BeforeAnalyzeCapability is used when you want to perform checks before analyzing a project.
 *
 * beforeAnalyze is invoked immediately before analyzing methods, before forking analysis workers and before starting the analysis phase.
 *
 * @see BeforeAnalyzePhaseCapability to run plugins **after** analyzing methods.
 * (use BeforeAnalyzePhaseCapability if you're not sure)
 */
interface BeforeAnalyzeCapability extends \Phan\PluginV2\BeforeAnalyzeCapability
{
    /**
     * This method is called before analyzing a project and before analyzing methods.
     *
     * @param CodeBase $code_base
     * The code base of the project.
     */
    public function beforeAnalyze(
        CodeBase $code_base
    ) : void;
}
