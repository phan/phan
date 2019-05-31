<?php declare(strict_types=1);

namespace Phan\PluginV3;

use Phan\CodeBase;

/**
 * BeforeAnalyzePhaseCapability is used when you want to perform checks before analyzing a project.
 *
 * beforeAnalyzePhase is invoked immediately before forking analysis workers and before starting the analysis phase.
 *
 * @see BeforeAnalyzeCapability to run plugins **before** analyzing methods.
 * (use BeforeAnalyzePhaseCapability if you're not sure)
 */
interface BeforeAnalyzePhaseCapability
{
    /**
     * This method is called before analyzing a project and after analyzing methods.
     *
     * @param CodeBase $code_base
     * The code base of the project.
     */
    public function beforeAnalyzePhase(
        CodeBase $code_base
    ) : void;
}
