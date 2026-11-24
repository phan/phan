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

        // PHP rejects a property that can be written from more places than it can be read from:
        // `protected public(set)`, `private public(set)` and `private protected(set)`.
        // The same visibility for both is allowed (`public public(set)`, `private private(set)`).
        $isVisibilityLessRestrictiveThanSet = false;
        if ($property->isPublicSet()) {
            $isVisibilityLessRestrictiveThanSet = !$property->isPublic();
        } elseif ($property->isProtectedSet()) {
            $isVisibilityLessRestrictiveThanSet = $property->isPrivate();
        }
        if ($isVisibilityLessRestrictiveThanSet) {
            self::emitIssue(
                $code_base,
                $property->getContext(),
                'PhanPluginAsymmetricVisibilityLessRestrictive',
                "Visibility ({CODE}) of property {PROPERTY} must not be weaker than set visibility ({CODE})",
                [
                    $property->getVisibilityName(),
                    $property->getRepresentationForIssue(),
                    (string) $property->getVisibilitySetName()
                ],
                Issue::SEVERITY_CRITICAL,
                Issue::REMEDIATION_A
            );
        }
    }
}

// Every plugin needs to return an instance of itself at the
// end of the file in which it's defined.
return new AsymmetricVisibilityPlugin();
