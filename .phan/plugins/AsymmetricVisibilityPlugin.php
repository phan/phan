<?php

declare(strict_types=1);

use Phan\CodeBase;
use Phan\Issue;
use Phan\Language\Element\Property;
use Phan\PluginV3;
use Phan\PluginV3\AnalyzePropertyCapability;

/**
 * This file checks Asymmetric Visibility
 */
class AsymmetricVisibilityPlugin extends PluginV3 implements AnalyzePropertyCapability
{
    /**
     * @param CodeBase $code_base @unused-param
     * The code base in which the property exists
     *
     * @param Property $property
     * A property being analyzed
     * @override
     */
    public function analyzeProperty(
        CodeBase $code_base,
        Property $property
    ): void {
        // TODO: this condition can be removed after https://github.com/nikic/php-ast/pull/250 is merged
        if (\PHP_VERSION_ID < 80400) {
            return;
        }

        if ($property->getFQSEN() !== $property->getRealDefiningFQSEN()) {
            return;
        }

        if (
            !$property->isPublicSet() &&
            !$property->isProtectedSet() &&
            !$property->isPrivateSet()
        ) {
            return;
        }

        if (0 === count($property->getRealUnionType()->getTypeSet())) {
            self::emitIssue(
                $code_base,
                $property->getContext(),
                'PhanPluginAsymmetricVisibilityNoType',
                "Property with asymmetric visibility {PROPERTY} must have a declared type",
                [$property->getRepresentationForIssue()],
                Issue::SEVERITY_CRITICAL,
                Issue::REMEDIATION_A
            );
        }

        $isVisibilityLessRestrictiveThanSet = false;
        if ($property->isPublicSet()) {
            $isVisibilityLessRestrictiveThanSet = true;
        } else if ($property->isProtectedSet() && !$property->isPublic()) {
            $isVisibilityLessRestrictiveThanSet = true;
        } else if ($property->isPrivateSet() && $property->isPrivate()) {
            $isVisibilityLessRestrictiveThanSet = true;
        }
        if ($isVisibilityLessRestrictiveThanSet) {
            self::emitIssue(
                $code_base,
                $property->getContext(),
                'PhanPluginAsymmetricVisibilityLessRestrictive',
                "Visibility ({VISIBILITY}) of property {PROPERTY} must not be weaker than set visibility ({SET_VISIBILITY})",
                [
                    $property->getVisibilityName(),
                    $property->getRepresentationForIssue(),
                    (string) $property->getVisibilitySetName()
                ],
                Issue::SEVERITY_CRITICAL,
                Issue::REMEDIATION_A
            );
        }
        //
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new AsymmetricVisibilityPlugin();
